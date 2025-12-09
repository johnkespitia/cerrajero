<?php

namespace App\Providers;

use App\Services\GuardService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        
        // Verificar que PDO esté disponible y que la conexión funcione
        if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
            try {
                if (Schema::hasTable('guards'))
                {
                    $guards = config('auth.guards');
                    $guards2 = array_merge($guards,(new GuardService())->getGuards());
                    config(['auth.guards' => $guards2]);
                }
            } catch (\Exception $e) {
                // Si hay un error de conexión, continuar sin cargar guards dinámicos
                // Esto puede ocurrir durante el despliegue antes de configurar la BD
            }
        }
    }
}
