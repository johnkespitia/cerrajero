<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Services\ReservationCancellationService;
use Mockery;
use Tests\TestCase;

class ReservationCancellationServiceStaffErrorTest extends TestCase
{
    private ReservationCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReservationCancellationService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_process_staff_error_void_does_not_mark_refunded_and_keeps_payment_status(): void
    {
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->payment_status = 'paid';
        $reservation->status = 'confirmed';

        $reservation->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return $data['status'] === 'cancelled'
                    && $data['cancellation_kind'] === 'staff_error'
                    && $data['cancellation_reason'] === 'Reserva duplicada por error'
                    && (float) $data['refund_amount'] === 0.0
                    && (float) $data['penalty_amount'] === 0.0
                    && !array_key_exists('payment_status', $data);
            }))
            ->andReturnUsing(function (array $data) use ($reservation) {
                foreach ($data as $key => $value) {
                    $reservation->{$key} = $value;
                }
                return true;
            });

        $result = $this->service->processStaffErrorVoid(
            $reservation,
            'Reserva duplicada por error'
        );

        $this->assertSame('cancelled', $result->status);
        $this->assertSame('staff_error', $result->cancellation_kind);
        $this->assertSame('paid', $result->payment_status);
        $this->assertEquals(0, $result->refund_amount);
        $this->assertEquals(0, $result->penalty_amount);
    }

    public function test_process_cancellation_sets_customer_kind(): void
    {
        $reservation = Mockery::mock(Reservation::class)->makePartial();
        $reservation->payment_status = 'pending';

        $service = Mockery::mock(ReservationCancellationService::class)
            ->makePartial();

        $service->shouldReceive('calculateRefund')
            ->once()
            ->with($reservation)
            ->andReturn([
                'refund_amount' => 0,
                'penalty_amount' => 0,
                'can_refund' => false,
                'policy' => null,
            ]);

        $reservation->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $data) {
                return ($data['cancellation_kind'] ?? null) === 'customer'
                    && ($data['cancellation_reason'] ?? null) === 'Cliente canceló'
                    && (float) ($data['refund_amount'] ?? -1) === 0.0;
            }))
            ->andReturn(true);

        $result = $service->processCancellation($reservation, 'Cliente canceló');

        $this->assertIsArray($result);
        $this->assertSame(0, $result['refund_amount']);
    }
}
