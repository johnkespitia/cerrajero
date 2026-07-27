<?php

namespace Tests\Unit\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\AdditionalService;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocumentAcquirer;
use App\Models\Reservation;
use App\Models\ReservationAdditionalService;
use App\Models\ReservationMinibarCharge;
use App\Services\ElectronicInvoicing\Exceptions\ReservationEmissionInvalidPayloadException;
use App\Services\ElectronicInvoicing\ReservationEmissionContextBuilder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ReservationEmissionContextBuilderTest extends TestCase
{
    /** @var ReservationEmissionContextBuilder */
    private $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ReservationEmissionContextBuilder();
    }

    public function test_builds_canonical_context_with_lodging_only(): void
    {
        $reservation = $this->makeReservation([
            'calculated_price' => 600000,
            'discount_amount' => 0,
            'check_in_date' => '2026-03-20',
            'check_out_date' => '2026-03-23',
        ]);

        $context = $this->builder->buildFromReservation(
            $reservation,
            $this->company(),
            $this->resolution(),
            DocumentType::FEV,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'SETP', 'sequence' => 990000010, 'number' => 'SETP990000010', 'resolution_id' => 7],
            $this->acquirer(),
            ['clave_tecnica' => 'fc8eac422eba16e22ffd8c6f']
        );

        $this->assertSame(DocumentType::FEV, $context['document_type']);
        $this->assertSame('reservation', $context['source_meta']['source_type']);
        $this->assertSame(42, $context['source_meta']['source_id']);
        $this->assertSame(9, $context['acquirer_id']);

        $this->assertCount(1, $context['lines']);
        $first = $context['lines'][0];
        $this->assertSame('1', $first['sequence']);
        $this->assertStringContainsString('Hospedaje 3 noches', $first['description']);
        $this->assertSame('DAY', $first['unit_code']);
        $this->assertSame('3.000', $first['quantity']);
        $this->assertSame('200000.00', $first['unit_price']);
        $this->assertSame('600000.00', $first['line_total']);
        $this->assertSame('0.00', $first['tax_amount']);

        $this->assertSame('600000.00', $context['totals']['line_extension_amount']);
        $this->assertSame('600000.00', $context['totals']['payable_amount']);
        $this->assertSame([], $context['taxes']);
    }

    public function test_builds_context_with_additional_services_minibar_and_room_charges(): void
    {
        $reservation = $this->makeReservation([
            'calculated_price' => 200000,
            'discount_amount' => 0,
            'check_in_date' => '2026-03-20',
            'check_out_date' => '2026-03-22',
        ], [
            'additional_services' => [
                $this->makeAdditionalServiceLine('Desayuno', 25000, 4),
            ],
            'minibar_charges' => [
                $this->makeMinibarCharge(10, 8000, 2),
            ],
            'room_charges_total' => 35000.0,
        ]);

        $context = $this->builder->buildFromReservation(
            $reservation,
            $this->company(),
            $this->resolution(),
            DocumentType::FEV,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'SETP', 'sequence' => 990000011, 'number' => 'SETP990000011', 'resolution_id' => 7],
            $this->acquirer(),
            ['clave_tecnica' => 'fc8eac422eba16e22ffd8c6f']
        );

        // 4 lines: lodging, additional service, minibar, room charges.
        $this->assertCount(4, $context['lines']);
        $kinds = array_map(fn ($line) => $line['extra']['kind'] ?? null, $context['lines']);
        $this->assertSame(['lodging', 'additional_service', 'minibar', 'room_charges'], $kinds);

        // Totals: 200k + 100k + 16k + 35k = 351k
        $this->assertSame('351000.00', $context['totals']['payable_amount']);
        $this->assertSame('351000.00', $context['totals']['line_extension_amount']);
    }

    public function test_lodging_respects_discount_amount(): void
    {
        $reservation = $this->makeReservation([
            'calculated_price' => 300000,
            'discount_amount' => 30000,
            'check_in_date' => '2026-03-20',
            'check_out_date' => '2026-03-22',
        ]);

        $context = $this->builder->buildFromReservation(
            $reservation,
            $this->company(),
            $this->resolution(),
            DocumentType::FEV,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'SETP', 'sequence' => 990000012, 'number' => 'SETP990000012', 'resolution_id' => 7],
            $this->acquirer(),
            ['clave_tecnica' => 'fc8eac422eba16e22ffd8c6f']
        );

        // 300_000 - 30_000 = 270_000
        $this->assertSame('270000.00', $context['lines'][0]['line_total']);
        $this->assertSame('30000.00', $context['lines'][0]['extra']['discount_amount']);
        $this->assertSame('270000.00', $context['totals']['payable_amount']);
    }

    public function test_reservation_without_billable_concepts_raises_invalid_payload(): void
    {
        $reservation = $this->makeReservation([
            'calculated_price' => 0,
            'discount_amount' => 0,
            'check_in_date' => '2026-03-20',
            'check_out_date' => '2026-03-22',
        ], [
            'room_charges_total' => 0.0,
        ]);

        $this->expectException(ReservationEmissionInvalidPayloadException::class);
        $this->builder->buildFromReservation(
            $reservation,
            $this->company(),
            $this->resolution(),
            DocumentType::FEV,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'SETP', 'sequence' => 1, 'number' => 'SETP1', 'resolution_id' => 7],
            $this->acquirer(),
            ['clave_tecnica' => 'fc8eac422eba16e22ffd8c6f']
        );
    }

    public function test_payment_block_falls_back_to_credit_immediate(): void
    {
        $reservation = $this->makeReservation([
            'calculated_price' => 100000,
            'check_in_date' => '2026-03-20',
            'check_out_date' => '2026-03-21',
        ]);

        $context = $this->builder->buildFromReservation(
            $reservation,
            $this->company(),
            $this->resolution(),
            DocumentType::FEV,
            FiscalEnvironment::HABILITACION,
            ['prefix' => 'SETP', 'sequence' => 1, 'number' => 'SETP1', 'resolution_id' => 7],
            $this->acquirer(),
            ['clave_tecnica' => 'fc8eac422eba16e22ffd8c6f']
        );

        $this->assertSame('10', $context['payment']['means_code']);
        $this->assertSame('1', $context['payment']['terms_code']);
    }

    private function makeReservation(array $attributes, array $relations = []): Reservation
    {
        $reservation = new Reservation(array_merge([
            'reservation_number' => 'RES2026030042',
            'customer_id' => 1,
            'room_id' => 1,
            'reservation_type' => 'overnight',
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
        ], $attributes));
        $reservation->id = 42;
        $reservation->setRelation(
            'additionalServices',
            new Collection($relations['additional_services'] ?? [])
        );
        $reservation->setRelation(
            'minibarCharges',
            new Collection($relations['minibar_charges'] ?? [])
        );
        $reservation->setRelation('room', null);
        $reservation->setRelation('customer', null);

        // Bypass the room_charges_total accessor (querying reservation_payments)
        // by pre-setting the attribute. Unit tests run without DB tables, so
        // letting Eloquent fall back to the accessor would explode.
        $reservation->setAttribute('room_charges_total', (float) ($relations['room_charges_total'] ?? 0));
        $reservation->check_out_time = Carbon::create(2026, 3, 22, 12, 0, 0);
        return $reservation;
    }

    private function makeAdditionalServiceLine(string $name, float $unitPrice, int $quantity): ReservationAdditionalService
    {
        $service = new ReservationAdditionalService([
            'reservation_id' => 42,
            'additional_service_id' => 11,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'guests_count' => 2,
            'total' => $unitPrice * $quantity,
        ]);
        $service->setRelation('additionalService', new AdditionalService(['name' => $name, 'price' => $unitPrice]));
        return $service;
    }

    private function makeMinibarCharge(int $productId, float $unitPrice, int $quantity): ReservationMinibarCharge
    {
        $charge = new ReservationMinibarCharge([
            'reservation_id' => 42,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
        ]);
        $charge->total = $unitPrice * $quantity;
        return $charge;
    }

    private function company(): CompanyFiscalProfile
    {
        $company = new CompanyFiscalProfile([
            'legal_name' => 'Campo Verde S.A.S.',
            'trade_name' => 'Campo Verde',
            'nit' => '900123456',
            'dv' => 1,
            'tax_regime_code' => '48',
            'address_line' => 'Km 5',
            'city_code_dian' => '63190',
            'country_code' => 'CO',
            'environment' => FiscalEnvironment::HABILITACION,
            'active' => true,
        ]);
        $company->id = 1;
        return $company;
    }

    private function resolution(array $overrides = []): DianResolution
    {
        $resolution = new DianResolution(array_merge([
            'company_id' => 1,
            'environment' => FiscalEnvironment::HABILITACION,
            'document_type' => DocumentType::FEV,
            'prefix' => 'SETP',
            'from_number' => 990000001,
            'to_number' => 990010000,
            'current_number' => 0,
            'active' => true,
        ], $overrides));
        $resolution->id = 7;
        return $resolution;
    }

    private function acquirer(): ElectronicDocumentAcquirer
    {
        $acquirer = new ElectronicDocumentAcquirer([
            'document_type' => '31',
            'document_number' => '800111222',
            'dv' => 3,
            'legal_name' => 'Cliente B2B SAS',
            'country_code' => 'CO',
        ]);
        $acquirer->id = 9;
        return $acquirer;
    }
}
