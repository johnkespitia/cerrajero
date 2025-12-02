<?php

namespace App\Services;

use App\Models\Reservation;
use Google_Client;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Service_Calendar_EventDateTime;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected $client;
    protected $calendarId;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Campo Verde Reservations');
        $this->client->setScopes(Google_Service_Calendar::CALENDAR);

        $credentials = env('GOOGLE_CALENDAR_CREDENTIALS');
        if ($credentials) {
            $this->client->setAuthConfig(json_decode($credentials, true));
        }

        $this->client->setAccessType('offline');
        $this->calendarId = env('GOOGLE_CALENDAR_ID');
    }

    public function createEvent(Reservation $reservation)
    {
        if (!$this->calendarId) {
            return null;
        }

        try {
            $service = new Google_Service_Calendar($this->client);

            $event = new Google_Service_Calendar_Event([
                'summary' => "Reserva #{$reservation->reservation_number} - {$reservation->customer->display_name}",
                'description' => $this->buildEventDescription($reservation),
                'start' => new Google_Service_Calendar_EventDateTime([
                    'dateTime' => $reservation->check_in_date->format('Y-m-d\T00:00:00'),
                    'timeZone' => 'America/Bogota',
                ]),
                'end' => new Google_Service_Calendar_EventDateTime([
                    'dateTime' => $reservation->check_out_date
                        ? $reservation->check_out_date->format('Y-m-d\T23:59:59')
                        : $reservation->check_in_date->format('Y-m-d\T23:59:59'),
                    'timeZone' => 'America/Bogota',
                ]),
            ]);

            $createdEvent = $service->events->insert($this->calendarId, $event);

            $reservation->update([
                'google_calendar_event_id' => $createdEvent->getId(),
                'google_calendar_link' => $createdEvent->getHtmlLink()
            ]);

            return $createdEvent;
        } catch (\Exception $e) {
            Log::error('Error creating Google Calendar event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateEvent(Reservation $reservation)
    {
        if (!$this->calendarId) {
            return null;
        }

        if (!$reservation->google_calendar_event_id) {
            return $this->createEvent($reservation);
        }

        try {
            $service = new Google_Service_Calendar($this->client);
            $event = $service->events->get($this->calendarId, $reservation->google_calendar_event_id);

            $event->setSummary("Reserva #{$reservation->reservation_number} - {$reservation->customer->display_name}");
            $event->setDescription($this->buildEventDescription($reservation));
            $event->getStart()->setDateTime($reservation->check_in_date->format('Y-m-d\T00:00:00'));

            if ($reservation->check_out_date) {
                $event->getEnd()->setDateTime($reservation->check_out_date->format('Y-m-d\T23:59:59'));
            }

            $updatedEvent = $service->events->update(
                $this->calendarId,
                $reservation->google_calendar_event_id,
                $event
            );

            $reservation->update([
                'google_calendar_link' => $updatedEvent->getHtmlLink()
            ]);

            return $updatedEvent;
        } catch (\Exception $e) {
            Log::error('Error updating Google Calendar event: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteEvent(Reservation $reservation)
    {
        if (!$this->calendarId || !$reservation->google_calendar_event_id) {
            return;
        }

        try {
            $service = new Google_Service_Calendar($this->client);
            $service->events->delete($this->calendarId, $reservation->google_calendar_event_id);

            $reservation->update([
                'google_calendar_event_id' => null,
                'google_calendar_link' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting Google Calendar event: ' . $e->getMessage());
        }
    }

    protected function buildEventDescription(Reservation $reservation)
    {
        $description = "Reserva #{$reservation->reservation_number}\n\n";

        $customer = $reservation->customer;
        if ($customer->customer_type === 'company') {
            $description .= "Cliente (Empresa): {$customer->company_name}\n";
            $description .= "NIT: {$customer->company_nit}\n";
            if ($customer->company_legal_representative) {
                $description .= "Representante Legal: {$customer->company_legal_representative}\n";
            }
        } else {
            $description .= "Cliente: {$customer->display_name}\n";
        }
        $description .= "Email: {$customer->email}\n";
        $description .= "Teléfono: {$customer->phone_number}\n\n";

        if ($reservation->room) {
            $description .= "Habitación: {$reservation->room->display_name}\n";
            if ($reservation->roomType) {
                $description .= "Tipo: {$reservation->roomType->name}\n";
            }
        } elseif ($reservation->roomType) {
            $description .= "Tipo de Habitación: {$reservation->roomType->name}\n";
        } else {
            $description .= "Tipo: Pasadía\n";
        }

        $description .= "Huéspedes: {$reservation->adults} adultos, {$reservation->children} niños, {$reservation->infants} bebés\n";

        if ($reservation->guests && $reservation->guests->count() > 0) {
            $description .= "\nHuéspedes Registrados:\n";
            foreach ($reservation->guests as $guest) {
                $description .= "- {$guest->full_name}";
                if ($guest->document_number) {
                    $description .= " ({$guest->document_type}: {$guest->document_number})";
                }
                if ($guest->is_primary_guest) {
                    $description .= " [Principal]";
                }
                if ($guest->health_insurance_name) {
                    $description .= "\n  Aseguradora: {$guest->health_insurance_name}";
                    if ($guest->health_insurance_type) {
                        $type = $guest->health_insurance_type === 'national' ? 'Nacional' : 'Internacional';
                        $description .= " ({$type})";
                    }
                }
                $description .= "\n";
            }
        }

        $description .= "\nPrecio Total: $" . number_format($reservation->total_price, 2) . "\n";

        if ($reservation->special_requests) {
            $description .= "\nSolicitudes Especiales: {$reservation->special_requests}\n";
        }

        return $description;
    }
}



