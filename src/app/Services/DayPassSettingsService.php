<?php

namespace App\Services;

use App\Models\ReservationSetting;

class DayPassSettingsService
{
    public const KEY_CAPACITY = 'day_pass_default_capacity';

    public const KEY_ADULT_PRICE = 'day_pass_default_adult_price';

    public const KEY_CHILD_PRICE = 'day_pass_default_child_price';

    public function defaults(): array
    {
        return [
            'default_capacity' => ReservationSetting::getInt(
                self::KEY_CAPACITY,
                (int) config('day_pass.default_capacity', 600)
            ),
            'default_adult_price' => ReservationSetting::getFloat(
                self::KEY_ADULT_PRICE,
                (float) config('day_pass.default_adult_price', 20000)
            ),
            'default_child_price' => ReservationSetting::getFloat(
                self::KEY_CHILD_PRICE,
                (float) config('day_pass.default_child_price', 20000)
            ),
        ];
    }

    public function update(array $data): array
    {
        $descriptions = [
            self::KEY_CAPACITY => 'Aforo máximo por defecto para pasadía',
            self::KEY_ADULT_PRICE => 'Precio por defecto por adulto en pasadía (COP)',
            self::KEY_CHILD_PRICE => 'Precio por defecto por niño en pasadía (COP)',
        ];

        ReservationSetting::set(
            self::KEY_CAPACITY,
            (string) (int) $data['default_capacity'],
            $descriptions[self::KEY_CAPACITY]
        );

        ReservationSetting::set(
            self::KEY_ADULT_PRICE,
            (string) (float) $data['default_adult_price'],
            $descriptions[self::KEY_ADULT_PRICE]
        );

        ReservationSetting::set(
            self::KEY_CHILD_PRICE,
            (string) (float) $data['default_child_price'],
            $descriptions[self::KEY_CHILD_PRICE]
        );

        return $this->defaults();
    }
}
