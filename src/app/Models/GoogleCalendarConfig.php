<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleCalendarConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'credentials_json',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'active',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * Obtener la configuración activa
     */
    public static function getActive()
    {
        return self::where('active', true)->first();
    }

    /**
     * Verificar si el token está expirado
     */
    public function isTokenExpired()
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return now()->greaterThan($this->token_expires_at);
    }

    /**
     * Obtener credenciales como array
     */
    public function getCredentialsArray()
    {
        if (!$this->credentials_json) {
            return null;
        }

        return json_decode($this->credentials_json, true);
    }
}

