<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Ports\DianSoapClientInterface;
use App\Models\User;
use App\Services\ElectronicInvoicing\Habilitacion\InMemoryTestSetReportRepository;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseDescriptor;
use App\Services\ElectronicInvoicing\Habilitacion\TestCaseEmitterInterface;
use App\Services\ElectronicInvoicing\Habilitacion\TestSetReportRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsElectronicInvoicingPermissions;
use Tests\Fixtures\ElectronicInvoicing\FakeDianSoapClient;
use Tests\TestCase;

class HabilitacionControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsElectronicInvoicingPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TestCaseEmitterInterface::class, $this->stubEmitter());
        $this->app->instance(TestSetReportRepository::class, new InMemoryTestSetReportRepository());
        $this->app->instance(DianSoapClientInterface::class, new FakeDianSoapClient());
        config()->set('electronic-invoicing.test_set_id', 'cfg-test-set-id');
    }

    public function test_run_test_set_returns_report_for_admin_user(): void
    {
        $this->actingAsAdmin();
        $soap = $this->app->make(DianSoapClientInterface::class);
        // 10 canonical cases -> 10 scripted accepted responses.
        for ($i = 0; $i < 20; $i++) {
            $soap->script('sendTestSetAsync', ['result' => ['IsValid' => 'true', 'StatusCode' => '00']]);
        }

        $response = $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set');
        $response->assertStatus(200)
            ->assertJsonPath('report.environment', 'habilitacion');
        $this->assertGreaterThan(0, $response->json('report.total'));
        $this->assertSame($response->json('report.accepted'), $response->json('report.total'));
    }

    public function test_run_test_set_returns_422_when_no_test_set_id(): void
    {
        $this->actingAsAdmin();
        config()->set('electronic-invoicing.test_set_id', '');

        $response = $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set');
        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'habilitacion_missing_test_set_id');
    }

    public function test_run_test_set_accepts_inline_fixtures(): void
    {
        $this->actingAsAdmin();
        $soap = $this->app->make(DianSoapClientInterface::class);
        $soap->script('sendTestSetAsync', ['result' => ['IsValid' => 'true', 'StatusCode' => '00']]);

        $response = $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set', [
            'fixtures' => [
                ['code' => 'OPS-01', 'category' => 'fev', 'description' => 'manual fixture'],
            ],
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('report.total', 1)
            ->assertJsonPath('report.cases.0.code', 'OPS-01');
    }

    public function test_run_test_set_requires_admin_permission(): void
    {
        $user = $this->makeUser();
        $this->grantElectronicInvoicingPermissions($user, []);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set')->assertStatus(401);
    }

    public function test_latest_report_returns_null_when_empty(): void
    {
        $this->actingAsAdmin();
        $response = $this->getJson('/api/electronic-invoicing/habilitacion/latest-report');
        $response->assertStatus(200)
            ->assertJsonPath('report', null);
    }

    public function test_latest_report_returns_persisted_payload(): void
    {
        $this->actingAsAdmin();
        $soap = $this->app->make(DianSoapClientInterface::class);
        $soap->script('sendTestSetAsync', ['result' => ['IsValid' => 'true', 'StatusCode' => '00']]);
        $this->postJson('/api/electronic-invoicing/habilitacion/run-test-set', [
            'fixtures' => [['code' => 'LAT-01', 'category' => 'fev']],
        ])->assertStatus(200);

        $response = $this->getJson('/api/electronic-invoicing/habilitacion/latest-report');
        $response->assertStatus(200)
            ->assertJsonPath('report.cases.0.code', 'LAT-01');
    }

    private function actingAsAdmin(): void
    {
        $user = $this->makeUser();
        $this->grantElectronicInvoicingPermissions($user, ['electronic_invoicing.admin']);
        $this->actingAs($user, 'sanctum');
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'admin',
            'email' => 'hab-' . bin2hex(random_bytes(3)) . '@test.local',
            'password' => bcrypt('pwd'),
        ]);
    }

    private function stubEmitter(): TestCaseEmitterInterface
    {
        return new class implements TestCaseEmitterInterface {
            public function emit(TestCaseDescriptor $case): array
            {
                return [
                    'file_name' => $case->code . '.xml',
                    'signed_xml' => '<Invoice><cbc:ID>' . $case->code . '</cbc:ID></Invoice>',
                    'dian_number' => $case->code,
                ];
            }
        };
    }
}
