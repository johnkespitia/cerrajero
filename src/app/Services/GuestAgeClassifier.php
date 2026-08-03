<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class GuestAgeClassifier
{
    /** Edad máxima exclusiva para bebé (menor de 4 años a la fecha de referencia). */
    public const INFANT_MAX_AGE_YEARS = 4;

    public const CATEGORY_INFANT = 'infant';
    public const CATEGORY_CHILD = 'child';
    public const CATEGORY_ADULT = 'adult';

    /**
     * Resuelve flags y categoría a partir de fecha de nacimiento y marcas manuales.
     *
     * @return array{is_infant: bool, is_child: bool, age_category: string, age: int|null}
     */
    public function resolve(
        ?string $birthDate,
        bool $isInfantManual = false,
        bool $isChildManual = false,
        CarbonInterface|string|null $referenceDate = null
    ): array {
        $reference = $this->normalizeReferenceDate($referenceDate);
        $age = $this->ageInYears($birthDate, $reference);

        $isInfant = false;
        if ($age !== null) {
            $isInfant = $age < self::INFANT_MAX_AGE_YEARS;
        } elseif ($isInfantManual) {
            $isInfant = true;
        }

        $isChild = !$isInfant && $isChildManual;

        return [
            'is_infant' => $isInfant,
            'is_child' => $isChild,
            'age_category' => $this->categoryFromFlags($isInfant, $isChild),
            'age' => $age,
        ];
    }

    public function categoryFromFlags(bool $isInfant, bool $isChild): string
    {
        if ($isInfant) {
            return self::CATEGORY_INFANT;
        }
        if ($isChild) {
            return self::CATEGORY_CHILD;
        }

        return self::CATEGORY_ADULT;
    }

    /**
     * Normaliza flags de edad en un payload de huésped antes de persistir.
     *
     * @param array<string, mixed> $guestData
     * @return array<string, mixed>
     */
    public function applyToGuestPayload(array $guestData, CarbonInterface|string|null $referenceDate = null): array
    {
        $birthDate = $guestData['birth_date'] ?? null;
        if ($birthDate instanceof \DateTimeInterface) {
            $birthDate = Carbon::instance($birthDate)->format('Y-m-d');
        } elseif ($birthDate !== null && $birthDate !== '') {
            $birthDate = (string) $birthDate;
        } else {
            $birthDate = null;
        }

        $resolved = $this->resolve(
            $birthDate,
            $this->toBool($guestData['is_infant'] ?? false),
            $this->toBool($guestData['is_child'] ?? false),
            $referenceDate
        );

        $guestData['is_infant'] = $resolved['is_infant'];
        $guestData['is_child'] = $resolved['is_child'];

        return $guestData;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function ageInYears(?string $birthDate, CarbonInterface|string|null $referenceDate = null): ?int
    {
        if ($birthDate === null || trim((string) $birthDate) === '') {
            return null;
        }

        try {
            $birth = Carbon::parse($birthDate)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $reference = $this->normalizeReferenceDate($referenceDate);

        if ($birth->greaterThan($reference)) {
            return 0;
        }

        return (int) $birth->diffInYears($reference);
    }

    private function normalizeReferenceDate(CarbonInterface|string|null $referenceDate): Carbon
    {
        if ($referenceDate instanceof CarbonInterface) {
            return Carbon::instance($referenceDate)->startOfDay();
        }

        if (is_string($referenceDate) && trim($referenceDate) !== '') {
            try {
                return Carbon::parse($referenceDate)->startOfDay();
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return now()->startOfDay();
    }
}
