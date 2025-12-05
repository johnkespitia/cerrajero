<?php

namespace App\Jobs;

use App\Services\ReservationNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessReservationNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @param ReservationNotificationService $notificationService
     * @return void
     */
    public function handle(ReservationNotificationService $notificationService)
    {
        try {
            Log::info('Iniciando procesamiento de notificaciones automáticas de reservas');
            
            $notificationService->processPendingReminders();
            
            Log::info('Procesamiento de notificaciones automáticas completado');
        } catch (\Exception $e) {
            Log::error('Error procesando notificaciones automáticas de reservas: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
}

