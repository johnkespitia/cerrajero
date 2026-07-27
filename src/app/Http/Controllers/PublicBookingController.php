<?php

namespace App\Http\Controllers;

use App\Services\PublicBookingService;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function __construct(
        protected PublicBookingService $publicBookingService
    ) {
    }

    public function config()
    {
        return response()->json($this->publicBookingService->getConfig());
    }

    public function roomTypes()
    {
        return response()->json($this->publicBookingService->getRoomTypes());
    }

    public function plans()
    {
        return response()->json($this->publicBookingService->getPlans());
    }

    public function availability(Request $request)
    {
        return $this->publicBookingService->checkAvailability($request);
    }

    public function calendar(Request $request)
    {
        return $this->publicBookingService->getMonthlyCalendar($request);
    }

    public function store(Request $request)
    {
        return $this->publicBookingService->createReservation($request);
    }

    public function uploadPaymentReceipt(Request $request, int $reservation)
    {
        return $this->publicBookingService->uploadPaymentReceipt($request, $reservation);
    }
}
