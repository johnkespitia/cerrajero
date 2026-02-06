<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\OrderPayment;
use App\Models\ReservationPayment;
use App\Models\ReservationMealConsumption;
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
     * Monto a cobrar/cargar por la parte adicional de la orden (ya registrada en consumos).
     */
    public function getAdditionalAmountForOrder(Order $order): float
    {
        $totalQuantity = (int) $order->orderItems()->sum('quantity');
        if ($totalQuantity < 1) {
            $totalQuantity = 1;
        }
        $additionalQuantity = (int) ReservationMealConsumption::where('order_id', $order->id)
            ->where('is_additional', true)
            ->sum('quantity_consumed');
        if ($totalQuantity > 0 && $additionalQuantity > 0) {
            return round((float) $order->price * ($additionalQuantity / $totalQuantity), 2);
        }
        return (float) $order->price;
    }

    /**
     * Cargar orden a habitación (reserva).
     * Solo se carga el monto correspondiente a la parte ADICIONAL (no incluida en el plan).
     */
    public function chargeToRoom(Order $order, Reservation $reservation): ReservationPayment
    {
        $amount = $this->getAdditionalAmountForOrder($order);

        $reservationPayment = ReservationPayment::create([
            'reservation_id' => $reservation->id,
            'amount' => $amount,
            'concept' => 'Consumo en restaurante',
            'payment_type_id' => null,
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
