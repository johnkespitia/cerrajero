<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\OrderPayment;
use App\Models\ReservationPayment;
use App\Models\PaymentType;

class OrderPaymentService
{
    /**
     * Procesar pago directo de una orden
     */
    public function processPayment(
        Order $order,
        PaymentType $paymentType,
        float $amount,
        string $reference = null
    ): OrderPayment {
        $orderPayment = OrderPayment::create([
            'order_id' => $order->id,
            'payment_type_id' => $paymentType->id,
            'amount' => $amount,
            'payment_reference' => $reference,
            'created_by' => auth()->id(),
        ]);

        // Marcar orden como pagada
        $order->update([
            'paid' => true,
            'payment_type_id' => $paymentType->id,
        ]);

        return $orderPayment;
    }

    /**
     * Cargar orden a habitación (reserva)
     */
    public function chargeToRoom(Order $order, Reservation $reservation): ReservationPayment
    {
        // Crear pago en la reserva
        $reservationPayment = ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => $order->price,
            'concept' => 'Consumo en restaurante',
            'payment_type_id' => null, // No tiene método de pago inmediato
            'payment_reference' => "Orden #{$order->id}",
            'notes' => "Orden de restaurante - {$order->meal_type}",
            'created_by' => auth()->id(),
        ]);

        // Actualizar precio final de la reserva
        $reservation->recomputeFinalPrice();

        // Marcar orden como cargada a habitación
        $order->update([
            'charge_to_room' => true,
            'paid' => true, // Se considera "pagada" porque se cargó a la habitación
        ]);

        return $reservationPayment;
    }
}
