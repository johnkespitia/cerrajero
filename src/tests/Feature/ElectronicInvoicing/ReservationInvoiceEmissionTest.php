<?php

namespace Tests\Feature\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Domain\ElectronicInvoicing\Ports\SecretManagerInterface;
use App\Http\Controllers\ReservationController;
use App\Infrastructure\ElectronicInvoicing\Secrets\ArraySecretManager;
use App\Models\CompanyFiscalProfile;
use App\Models\Customer;
use App\Models\DianResolution;
use App\Models\DianSoftwareCredential;
use App\Models\ElectronicDocument;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ElectronicInvoicing\Exceptions\ReservationEmissionInvalidPayloadException;
use App\Services\ElectronicInvoicing\ReservationInvoiceEmissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReservationInvoiceEmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['electronic-invoicing.enabled' => true]);
        config(['electronic-invoicing.environment' => FiscalEnvironment::HABILITACION]);
        config(['electronic-invoicing.secrets' => ['hab/pin' => 'TEST-PIN-HAB']]);
        $this->app->instance(SecretManagerInterface::class, new ArraySecretManager([
            'hab/pin' => 'TEST-PIN-HAB',
        ]));
    }

    public function test_emit_for_reservation_creates_fev_in_ubl_built_with_source_metadata(): void
    {
        $reservation = $this->seedReservation(['calculated_price' => 450000]);
        $this->seedFiscalConfiguration();

        $service = new ReservationInvoiceEmissionService();
        $document = $service->emitForReservation($reservation, $this->validAcquirerPayload());

        $this->assertSame(DocumentType::FEV, $document->document_type);
        $this->assertSame('ubl_built', $document->status);
        $this->assertSame('reservation', $document->source_type);
        $this->assertSame($reservation->id, (int) $document->source_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{96}$/', (string) $document->cufe_cude);
        $this->assertSame('SETP990000001', $document->dian_number);

        $reservation->refresh();
        $this->assertSame($document->id, (int) $reservation->electronic_document_id);
    }

    public function test_emit_for_reservation_requires_acquirer(): void
    {
        $reservation = $this->seedReservation(['calculated_price' => 100000]);
        $this->seedFiscalConfiguration();

        $service = new ReservationInvoiceEmissionService();
        $this->expectException(ReservationEmissionInvalidPayloadException::class);
        $service->emitForReservation($reservation, null);
    }

    public function test_emit_for_reservation_requires_acquirer_required_fields(): void
    {
        $reservation = $this->seedReservation(['calculated_price' => 100000]);
        $this->seedFiscalConfiguration();

        $service = new ReservationInvoiceEmissionService();
        $this->expectException(ReservationEmissionInvalidPayloadException::class);
        $service->emitForReservation($reservation, [
            'document_number' => '800111222',
            // missing document_type and legal_name on purpose
        ]);
    }

    public function test_checkout_endpoint_emits_fev_when_acquirer_is_provided(): void
    {
        [$user, $reservation] = $this->seedCheckoutScenario();
        $this->seedFiscalConfiguration();

        $response = $this->callCheckOut($user, $reservation, ['acquirer' => $this->validAcquirerPayload()]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayHasKey('electronic_document', $body);
        $this->assertSame(DocumentType::FEV, $body['electronic_document']['document_type']);
        $this->assertSame('ubl_built', $body['electronic_document']['status']);
        $this->assertSame('SETP990000001', $body['electronic_document']['dian_number']);

        $reservation->refresh();
        $this->assertSame('checked_out', $reservation->status);
        $this->assertNotNull($reservation->electronic_document_id);
        $this->assertSame(1, ElectronicDocument::count());
    }

    public function test_checkout_endpoint_rejects_fev_without_acquirer(): void
    {
        [$user, $reservation] = $this->seedCheckoutScenario();
        $this->seedFiscalConfiguration();

        $response = $this->callCheckOut($user, $reservation, []);

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayHasKey('electronic_document_error', $body);
        $this->assertSame(
            ReservationEmissionInvalidPayloadException::CODE_MISSING_ACQUIRER,
            $body['electronic_document_error']['code']
        );

        $reservation->refresh();
        $this->assertSame('checked_in', $reservation->status, 'Checkout must not progress when EI requires acquirer.');
        $this->assertSame(0, ElectronicDocument::count());
    }

    public function test_checkout_endpoint_preserves_legacy_behaviour_when_ei_disabled(): void
    {
        config(['electronic-invoicing.enabled' => false]);
        [$user, $reservation] = $this->seedCheckoutScenario();
        // No fiscal seed needed: EI disabled must not even try.

        $response = $this->callCheckOut($user, $reservation, []);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayNotHasKey('electronic_document', $body);
        $this->assertArrayNotHasKey('electronic_document_error', $body);

        $reservation->refresh();
        $this->assertSame('checked_out', $reservation->status);
        $this->assertNull($reservation->electronic_document_id);
        $this->assertSame(0, ElectronicDocument::count());
    }

    public function test_checkout_endpoint_attaches_electronic_document_error_when_fiscal_config_missing(): void
    {
        [$user, $reservation] = $this->seedCheckoutScenario();
        // Skip fiscal seed: CompanyFiscalProfile / DianResolution missing on purpose.

        $response = $this->callCheckOut($user, $reservation, ['acquirer' => $this->validAcquirerPayload()]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertArrayHasKey('electronic_document_error', $body);
        $this->assertSame('fiscal_profile_missing', $body['electronic_document_error']['code']);
        $this->assertArrayNotHasKey('electronic_document', $body);

        $reservation->refresh();
        $this->assertSame('checked_out', $reservation->status);
        $this->assertSame(0, ElectronicDocument::count());
    }

    private function callCheckOut(User $user, Reservation $reservation, array $payload)
    {
        $request = Request::create("/api/reservations/{$reservation->id}/check-out", 'POST', $payload);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        $this->actingAs($user);

        /** @var ReservationController $controller */
        $controller = $this->app->make(ReservationController::class);
        return $controller->checkOut($request, $reservation);
    }

    private function seedReservation(array $overrides = []): Reservation
    {
        $customer = Customer::create([
            'dni' => '1010101010',
            'name' => 'Carlos',
            'last_name' => 'Huesped',
            'email' => 'carlos@huesped.local',
            'phone_number' => '3000000000',
            'active' => true,
            'customer_type' => 'person',
        ]);

        return Reservation::create(array_merge([
            'customer_id' => $customer->id,
            'reservation_type' => 'room',
            'check_in_date' => '2026-03-20',
            'check_out_date' => '2026-03-22',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'total_price' => 200000,
            'calculated_price' => 200000,
            'discount_amount' => 0,
            'final_price' => 200000,
            'status' => 'checked_in',
            'payment_status' => 'paid',
        ], $overrides));
    }

    private function seedCheckoutScenario(): array
    {
        $user = \App\Models\User::create([
            'name' => 'Recepcion',
            'email' => 'recepcion@test.local',
            'password' => bcrypt('test1234'),
        ]);
        $reservation = $this->seedReservation([
            'calculated_price' => 500000,
            'total_price' => 500000,
            'final_price' => 500000,
            'payment_status' => 'free', // bypass payment validation in the controller
            'check_in_date' => Carbon::today()->subDays(2)->toDateString(),
            'check_out_date' => Carbon::today()->toDateString(),
            'check_in_time' => Carbon::today()->subDays(2)->toDateTimeString(),
        ]);
        return [$user, $reservation];
    }

    private function seedFiscalConfiguration(): array
    {
        $company = CompanyFiscalProfile::create([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'email' => 'fiscal@campoverde.local',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);

        $resolutionFev = DianResolution::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'resolution_number' => '18760000001',
            'resolution_date' => '2026-01-01',
            'from_number' => 990000001,
            'to_number' => 990010000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2099-12-31',
            'technical_key' => 'fc8eac422eba16e22ffd8c6f',
            'current_number' => 0,
            'active' => true,
        ]);

        DianSoftwareCredential::create([
            'company_id' => $company->id,
            'environment' => FiscalEnvironment::HABILITACION,
            'software_id' => 'a4b3c2d1-e5f6-7890-1234-567890abcdef',
            'software_pin_secret_ref' => 'ref:hab/pin',
            'production_url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
            'habilitacion_url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc?wsdl',
        ]);

        return [$company, $resolutionFev];
    }

    private function validAcquirerPayload(): array
    {
        return [
            'document_type' => '31',
            'document_number' => '800111222',
            'dv' => 3,
            'legal_name' => 'Cliente B2B SAS',
            'country_code' => 'CO',
            'address_line' => 'Cra 50',
            'email' => 'b2b@cliente.local',
            'tax_regime_code' => '48',
        ];
    }
}
