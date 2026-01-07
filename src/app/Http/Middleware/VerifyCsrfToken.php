<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Las rutas API están excluidas porque usamos autenticación por tokens Bearer,
     * no por cookies/sesiones. El CSRF solo es necesario para formularios tradicionales.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',
    ];
}
