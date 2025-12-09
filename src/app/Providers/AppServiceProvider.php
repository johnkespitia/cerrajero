<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Verificar que PDO esté disponible antes de configurar Schema
        if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
            try {
                Schema::defaultStringLength(125);
            } catch (\Exception $e) {
                // Si hay un error de conexión, continuar sin configurar
                // Esto puede ocurrir durante el despliegue antes de configurar la BD
            }
        }
    }
}
