<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value, ?string $description = null): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );
    }

    public static function getJson(string $key, array $default = []): array
    {
        $raw = self::get($key);

        if ($raw === null || $raw === '') {
            return $default;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function setJson(string $key, array $value, ?string $description = null): self
    {
        return self::set($key, json_encode($value, JSON_UNESCAPED_UNICODE), $description);
    }
}
