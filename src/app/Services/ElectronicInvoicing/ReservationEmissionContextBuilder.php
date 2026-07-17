<?php

namespace App\Services\ElectronicInvoicing;

use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment;
use App\Models\CompanyFiscalProfile;
use App\Models\DianResolution;
use App\Models\ElectronicDocumentAcquirer;
use App\Models\Reservation;
use App\Services\ElectronicInvoicing\Exceptions\ReservationEmissionInvalidPayloadException;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Adapter that turns a checked-out Reservation into the canonical emission
 * context expected by DocumentAssembler / DocumentEmitter.
 *
 * Responsibilities:
 *  - Build line items from the reservation breakdown:
 *      1. Hospedaje (rate * nights) consolidated as a single line. The
 *         number of nights is taken from Reservation::$nights accessor.
 *      2. ReservationAdditionalService rows (when present).
 *      3. ReservationMinibarCharge rows (when present).
 *      4. A consolidated "Cargos a habitación" line covering the
 *         `room_charges_total` (kiosk-to-room charges already paid against
 *         the reservation balance).
 *  - Aggregate totals at document level.
 *  - Fill numbering, signing, payment and source_meta segments.
 *
 * Deuda explicita: las tablas de servicios adicionales/minibar todavia no
 * persisten un snapshot fiscal por linea (`tax_id` / `tax_rate`). El builder
 * marca `tax_amount=0` para esos rubros y deja la traza en cada `extra`.
 * Cuando el slice de snapshot fiscal para reservas exista, este builder
 * debera leer las columnas `fiscal_*` analogas a `KioskInvoiceDetail`.
 */
final class ReservationEmissionContextBuilder
{
    public const TAX_CODE_IVA = '01';
    public const TAX_NAME_IVA = 'IVA';
    public const DEFAULT_UNIT_MEASURE = 'NIU';
    public const UNIT_MEASURE_NIGHT = 'DAY';

    /**
     * @param array{
     *     prefix: string,
     *     sequence: int|string,
     *     number: string,
     *     resolution_id?: int
     * } $numbering
     * @param array{
     *     clave_tecnica?: string,
     *     pin?: string,
     *     software_id?: string,
     * } $signing
     */
    public function buildFromReservation(
        Reservation $reservation,
        CompanyFiscalProfile $company,
        DianResolution $resolution,
        string $documentType,
        string $environment,
        array $numbering,
        ElectronicDocumentAcquirer $acquirer,
        array $signing,
        ?DateTimeInterface $issuedAt = null
    ): array {
        DocumentType::assert($documentType);
        FiscalEnvironment::assert($environment);

        $reservation->loadMissing(['additionalServices.additionalService', 'minibarCharges', 'room', 'customer']);

        $lines = [];
        $sequence = 1;

        $lodgingLine = $this->buildLodgingLine($reservation, $sequence);
        if ($lodgingLine !== null) {
            $lines[] = $lodgingLine;
            $sequence++;
        }

        foreach ($this->buildAdditionalServiceLines($reservation, $sequence) as $line) {
            $lines[] = $line;
            $sequence++;
        }

        foreach ($this->buildMinibarLines($reservation, $sequence) as $line) {
            $lines[] = $line;
            $sequence++;
        }

        $roomChargesLine = $this->buildRoomChargesLine($reservation, $sequence);
        if ($roomChargesLine !== null) {
            $lines[] = $roomChargesLine;
            $sequence++;
        }

        if ($lines === []) {
            throw ReservationEmissionInvalidPayloadException::invalidLines();
        }

        $totalBase = 0.0;
        $totalTax = 0.0;
        foreach ($lines as $line) {
            $totalBase += (float) ($line['taxable_amount'] ?? $line['line_total']);
            $totalTax += (float) ($line['tax_amount'] ?? 0);
        }
        $totalGross = $totalBase + $totalTax;

        $issuedAt = $issuedAt ?: ($reservation->check_out_time ?? $reservation->updated_at ?? Carbon::now());

        $context = [
            'company' => $company,
            'document_type' => $documentType,
            'environment' => $environment,
            'numbering' => $numbering,
            'acquirer' => $acquirer,
            'acquirer_id' => (int) $acquirer->id,
            'issued_at' => $issuedAt,
            'currency' => 'COP',
            'lines' => $lines,
            'totals' => [
                'line_extension_amount' => $this->money($totalBase),
                'tax_exclusive_amount' => $this->money($totalBase),
                'tax_inclusive_amount' => $this->money($totalBase + $totalTax),
                'payable_amount' => $this->money($totalGross),
            ],
            'taxes' => $this->buildTaxBucket($lines),
            'payment' => $this->paymentBlock($reservation),
            'cufe_signing' => $signing,
            'software_credential' => $this->softwareCredentialBlock($signing),
            'source_meta' => [
                'source_type' => 'reservation',
                'source_id' => (int) $reservation->id,
            ],
            'notes' => $this->notesBlock($reservation),
        ];

        return $context;
    }

    private function buildLodgingLine(Reservation $reservation, int $sequence): ?array
    {
        $breakdown = $reservation->price_breakdown ?? [];
        $lodgingNet = (float) ($reservation->calculated_price ?? $reservation->total_price ?? 0);
        $discount = (float) ($breakdown['discount'] ?? 0);
        if ($lodgingNet <= 0) {
            return null;
        }
        $nights = max(1, (int) $reservation->nights);
        $unitPrice = round($lodgingNet / $nights, 2);

        $description = $this->describeLodging($reservation, $nights);

        return [
            'sequence' => (string) $sequence,
            'description' => $description,
            'quantity' => $this->quantity($nights),
            'unit_code' => self::UNIT_MEASURE_NIGHT,
            'unit_price' => $this->money($unitPrice),
            'line_total' => $this->money($lodgingNet),
            'taxable_amount' => $this->money($lodgingNet),
            'tax_amount' => $this->money(0),
            'extra' => [
                'kind' => 'lodging',
                'reservation_number' => (string) ($reservation->reservation_number ?? ''),
                'check_in_date' => optional($reservation->check_in_date)->format('Y-m-d'),
                'check_out_date' => optional($reservation->check_out_date)->format('Y-m-d'),
                'discount_amount' => $this->money($discount),
            ],
        ];
    }

    private function buildAdditionalServiceLines(Reservation $reservation, int $startSequence): array
    {
        $lines = [];
        $sequence = $startSequence;
        foreach ($reservation->additionalServices as $service) {
            $total = (float) ($service->total ?? 0);
            if ($total <= 0) {
                continue;
            }
            $itemQty = max(1, (int) ($service->quantity ?? 1));
            $serviceDays = max(1.0, (float) ($service->service_days ?? 1));
            $quantity = max(1, (int) round($itemQty * $serviceDays));
            $unitPrice = (float) ($service->unit_price ?? round($total / max(1, $quantity), 2));
            $name = $service->additionalService->name ?? ('Servicio adicional #' . $service->additional_service_id);

            $lines[] = [
                'sequence' => (string) $sequence,
                'description' => (string) $name,
                'quantity' => $this->quantity($quantity),
                'unit_code' => self::DEFAULT_UNIT_MEASURE,
                'unit_price' => $this->money($unitPrice),
                'line_total' => $this->money($total),
                'taxable_amount' => $this->money($total),
                'tax_amount' => $this->money(0),
                'extra' => [
                    'kind' => 'additional_service',
                    'additional_service_id' => (int) $service->additional_service_id,
                    'item_quantity' => $itemQty,
                    'service_days' => $serviceDays,
                ],
            ];
            $sequence++;
        }
        return $lines;
    }

    private function buildMinibarLines(Reservation $reservation, int $startSequence): array
    {
        $lines = [];
        $sequence = $startSequence;
        foreach ($reservation->minibarCharges as $charge) {
            $total = (float) ($charge->total ?? 0);
            if ($total <= 0) {
                continue;
            }
            $quantity = max(1, (int) ($charge->quantity ?? 1));
            $unitPrice = (float) ($charge->unit_price ?? round($total / max(1, $quantity), 2));
            $description = 'Minibar #' . ($charge->product_id ?? 'consumo');

            $lines[] = [
                'sequence' => (string) $sequence,
                'description' => $description,
                'quantity' => $this->quantity($quantity),
                'unit_code' => self::DEFAULT_UNIT_MEASURE,
                'unit_price' => $this->money($unitPrice),
                'line_total' => $this->money($total),
                'taxable_amount' => $this->money($total),
                'tax_amount' => $this->money(0),
                'extra' => [
                    'kind' => 'minibar',
                    'product_id' => $charge->product_id !== null ? (int) $charge->product_id : null,
                ],
            ];
            $sequence++;
        }
        return $lines;
    }

    private function buildRoomChargesLine(Reservation $reservation, int $sequence): ?array
    {
        $amount = $this->roomChargesTotal($reservation);
        if ($amount <= 0) {
            return null;
        }
        return [
            'sequence' => (string) $sequence,
            'description' => 'Cargos a habitación (restaurante/kiosko)',
            'quantity' => $this->quantity(1),
            'unit_code' => self::DEFAULT_UNIT_MEASURE,
            'unit_price' => $this->money($amount),
            'line_total' => $this->money($amount),
            'taxable_amount' => $this->money($amount),
            'tax_amount' => $this->money(0),
            'extra' => [
                'kind' => 'room_charges',
            ],
        ];
    }

    private function buildTaxBucket(array $lines): array
    {
        $bucket = [];
        foreach ($lines as $line) {
            if (empty($line['tax_amount']) || (float) $line['tax_amount'] <= 0) {
                continue;
            }
            $code = (string) ($line['tax_scheme_code'] ?? self::TAX_CODE_IVA);
            $percent = (string) ($line['tax_percent'] ?? '0.00');
            $key = $code . ':' . $percent;
            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'code' => $code,
                    'name' => $line['tax_scheme_name'] ?? self::TAX_NAME_IVA,
                    'percent' => $percent,
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                ];
            }
            $bucket[$key]['taxable_amount'] += (float) $line['taxable_amount'];
            $bucket[$key]['tax_amount'] += (float) $line['tax_amount'];
        }
        $out = [];
        foreach ($bucket as $entry) {
            $entry['taxable_amount'] = $this->money($entry['taxable_amount']);
            $entry['tax_amount'] = $this->money($entry['tax_amount']);
            $out[] = $entry;
        }
        return $out;
    }

    private function describeLodging(Reservation $reservation, int $nights): string
    {
        $roomLabel = '';
        if ($reservation->room && !empty($reservation->room->name)) {
            $roomLabel = ' habitación ' . $reservation->room->name;
        } elseif ($reservation->room_id) {
            $roomLabel = ' habitación #' . $reservation->room_id;
        }
        if ($reservation->reservation_type === 'day_pass') {
            return 'Pasadía' . $roomLabel;
        }
        return sprintf('Hospedaje %d noche%s%s', $nights, $nights === 1 ? '' : 's', $roomLabel);
    }

    private function paymentBlock(Reservation $reservation): array
    {
        return [
            'means_code' => (string) ($reservation->fiscal_payment_means_code ?: '10'),
            'terms_code' => (string) ($reservation->fiscal_payment_terms ?: '1'),
        ];
    }

    private function softwareCredentialBlock(array $signing): ?array
    {
        if (empty($signing['software_id']) || empty($signing['pin'])) {
            return null;
        }
        return [
            'software_id' => (string) $signing['software_id'],
            'pin' => (string) $signing['pin'],
        ];
    }

    private function notesBlock(Reservation $reservation): ?string
    {
        if (!empty($reservation->reservation_number)) {
            return 'Reservation number=' . $reservation->reservation_number;
        }
        return null;
    }

    /**
     * Prefer a pre-set raw attribute over the accessor so unit tests can
     * inject `room_charges_total` without hitting the reservation_payments
     * table. In production the accessor is still consulted.
     */
    private function roomChargesTotal(Reservation $reservation): float
    {
        $attrs = $reservation->getAttributes();
        if (array_key_exists('room_charges_total', $attrs)) {
            return (float) $attrs['room_charges_total'];
        }
        return (float) ($reservation->room_charges_total ?? 0);
    }

    private function money($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function quantity($value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
