<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('reservation_number')->unique();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade'); // Cliente (puede ser empresa)
            $table->foreignId('room_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('room_type_id')->nullable()->constrained()->onDelete('set null'); // Tipo de habitación seleccionado
            $table->enum('reservation_type', ['room', 'day_pass'])->default('room');
            $table->foreignId('parent_reservation_id')->nullable()->constrained('reservations')->onDelete('cascade'); // Para agrupar reservas relacionadas (múltiples habitaciones)
            $table->boolean('is_group_reservation')->default(false); // Indica si es parte de un grupo de reservas
            $table->integer('room_sequence')->nullable(); // Orden de la habitación en el grupo (1, 2, 3...)
            $table->date('check_in_date');
            $table->date('check_out_date')->nullable();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);
            $table->integer('infants')->default(0);
            $table->decimal('total_price', 10, 2);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'free', 'refunded'])->default('pending');
            $table->text('free_reservation_reason')->nullable(); // Razón por la cual es gratis
            $table->string('free_reservation_reference')->nullable(); // Referencia/autorización para reserva gratis
            $table->text('special_requests')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('google_calendar_event_id')->nullable();
            $table->string('google_calendar_link')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Campos de seguimiento de marketing y origen de la reserva
            $table->enum('contact_channel', [
                'whatsapp', 
                'facebook', 
                'instagram', 
                'email', 
                'phone', 
                'website', 
                'walk_in', 
                'other'
            ])->nullable(); // Canal por el cual se contactó
            $table->enum('referral_source', [
                'social_media', 
                'google_search', 
                'recommendation', 
                'previous_guest', 
                'travel_agency', 
                'booking_platform', 
                'advertisement', 
                'other'
            ])->nullable(); // Cómo se enteró de nosotros
            $table->string('social_media_platform')->nullable(); // Plataforma específica (si aplica)
            $table->string('campaign_name')->nullable(); // Nombre de la campaña de marketing
            $table->string('tracking_code')->nullable(); // Código de seguimiento (UTM, código promocional, etc.)
            $table->text('marketing_notes')->nullable(); // Notas adicionales sobre el origen
            
            $table->timestamps();
            
            $table->index('customer_id');
            $table->index('room_id');
            $table->index('room_type_id'); // Índice para filtrar por tipo de habitación
            $table->index('reservation_type');
            $table->index('check_in_date');
            $table->index('check_out_date');
            $table->index('status');
            $table->index('reservation_number');
            $table->index('parent_reservation_id'); // Para buscar reservas relacionadas
            $table->index('is_group_reservation');
            // Índices para seguimiento de marketing
            $table->index('contact_channel');
            $table->index('referral_source');
            $table->index('campaign_name');
            $table->index('tracking_code');
            // Índice compuesto para búsquedas por tipo y fechas
            $table->index(['room_type_id', 'check_in_date', 'check_out_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reservations');
    }
}
