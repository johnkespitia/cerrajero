<?php

namespace App\Models;

use App\Services\CourtesyGuestDiscountCalculator;
use App\Services\ReservationPriceCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_number',
        'customer_id',
        'room_id',
        'room_type_id', // Tipo de habitación seleccionado
        'reservation_type',
        'check_in_date',
        'check_out_date',
        'check_in_time',
        'check_out_time',
        'adults',
        'children',
        'infants',
        'courtesy_guests',
        'extra_beds',
        'total_price',
        'calculated_price',
        'manual_price_override',
        'price_breakdown',
        'promotion_code',
        'discount_amount',
        'final_price',
        'deposit_amount',
        'status',
        'payment_status',
        'web_payment_mode',
        'web_payment_receipt_path',
        'web_payment_receipt_url',
        'web_payment_receipt_uploaded_at',
        'free_reservation_reason',
        'free_reservation_reference',
        'special_requests',
        'cancellation_reason',
        'cancellation_kind',
        'cancellation_policy_id',
        'cancellation_deadline',
        'refund_amount',
        'penalty_amount',
        'google_calendar_event_id',
        'google_calendar_link',
        'email_sent',
        'email_sent_at',
        'reminder_sent',
        'reminder_sent_at',
        'check_in_reminder_sent',
        'check_in_reminder_sent_at',
        'early_check_in',
        'late_check_out',
        'early_check_in_fee',
        'late_check_out_fee',
        'scheduled_check_in_time',
        'scheduled_check_out_time',
        'created_by',
        // Campos de seguimiento de marketing
        'contact_channel',
        'referral_source',
        'social_media_platform',
        'campaign_name',
        'tracking_code',
        'marketing_notes',
        // Portal público de huéspedes (OTP)
        'guest_portal_token',
        'guest_portal_otp_hash',
        'guest_portal_otp_expires_at',
        'guest_portal_otp_attempts',
        'guest_portal_session_hash',
        'guest_portal_session_expires_at',
        'guest_portal_enabled_at',
        // Campos para reservas agrupadas (múltiples habitaciones)
        'parent_reservation_id',
        'is_group_reservation',
        'room_sequence',
        // Campos fiscales (slice electronic-invoicing-dian-reservations-backend)
        'electronic_document_id',
        'fiscal_payment_means_code',
        'fiscal_payment_terms',
        'fiscal_due_date',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'scheduled_check_in_time' => 'datetime',
        'scheduled_check_out_time' => 'datetime',
        'total_price' => 'decimal:2',
        'calculated_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'early_check_in_fee' => 'decimal:2',
        'late_check_out_fee' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'cancellation_deadline' => 'datetime',
        'email_sent' => 'boolean',
        'email_sent_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'check_in_reminder_sent' => 'boolean',
        'check_in_reminder_sent_at' => 'datetime',
        'web_payment_receipt_uploaded_at' => 'datetime',
        'manual_price_override' => 'boolean',
        'courtesy_guests' => 'integer',
        'early_check_in' => 'boolean',
        'late_check_out' => 'boolean',
        'price_breakdown' => 'array',
        'fiscal_due_date' => 'date',
        'guest_portal_otp_expires_at' => 'datetime',
        'guest_portal_otp_attempts' => 'integer',
        'guest_portal_session_expires_at' => 'datetime',
        'guest_portal_enabled_at' => 'datetime',
    ];

    protected $hidden = [
        'guest_portal_otp_hash',
        'guest_portal_session_hash',
    ];

    public function electronicDocument()
    {
        return $this->belongsTo(ElectronicDocument::class, 'electronic_document_id');
    }

    /**
     * Atributos calculados incluidos en la serialización JSON (API).
     */
    protected $appends = ['room_charges_total'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($reservation) {
            if (empty($reservation->reservation_number)) {
                $reservation->reservation_number = self::generateReservationNumber();
            }
        });
    }

    public static function generateReservationNumber()
    {
        $prefix = 'RES';
        $year = date('Y');
        $month = date('m');
        $lastReservation = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $number = $lastReservation ? (int) substr($lastReservation->reservation_number, -6) + 1 : 1;
        
        return $prefix . $year . $month . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function guests()
    {
        return $this->hasMany(ReservationGuest::class);
    }

    public function primaryGuest()
    {
        return $this->hasOne(ReservationGuest::class)->where('is_primary_guest', true);
    }

    public function parentReservation()
    {
        return $this->belongsTo(Reservation::class, 'parent_reservation_id');
    }

    public function childReservations()
    {
        return $this->hasMany(Reservation::class, 'parent_reservation_id')->orderBy('room_sequence');
    }

    public function allGroupReservations()
    {
        if ($this->parent_reservation_id) {
            // Si es una reserva hija, retorna todas las del grupo incluyendo la padre
            return Reservation::where('id', $this->parent_reservation_id)
                ->orWhere('parent_reservation_id', $this->parent_reservation_id)
                ->orderBy('room_sequence')
                ->get();
        } else {
            // Si es la reserva padre, retorna todas las hijas
            return $this->childReservations;
        }
    }

    public function getTotalGroupGuestsAttribute()
    {
        if ($this->is_group_reservation || $this->parent_reservation_id) {
            $allReservations = $this->allGroupReservations();
            return $allReservations->sum(function($res) {
                return $res->adults + $res->children + $res->infants;
            });
        }
        return $this->adults + $this->children + $this->infants;
    }

    public function getTotalGroupPriceAttribute()
    {
        if ($this->is_group_reservation || $this->parent_reservation_id) {
            $allReservations = $this->allGroupReservations();
            return $allReservations->sum('total_price');
        }
        return $this->total_price;
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTotalGuestsAttribute()
    {
        return $this->adults + $this->children + $this->infants;
    }

    public function getNightsAttribute()
    {
        if (!$this->check_out_date) {
            return 1; // Para pasadía
        }
        return $this->check_in_date->diffInDays($this->check_out_date);
    }

    public function payments()
    {
        return $this->hasMany(ReservationPayment::class);
    }

    public function audits()
    {
        return $this->hasMany(ReservationAudit::class)->orderBy('created_at', 'desc');
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promotion_code', 'code');
    }

    public function cancellationPolicy()
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    /**
     * Total pagado: solo pagos reales (payment_type_id no nulo).
     * Los cargos a habitación (payment_type_id null) no cuentan como pagado.
     */
    public function getTotalPaidAttribute()
    {
        return $this->payments()->whereNotNull('payment_type_id')->sum('amount');
    }

    /**
     * Total de cargos a habitación (restaurante, etc.): pagos con payment_type_id null.
     */
    public function getRoomChargesTotalAttribute(): float
    {
        return (float) $this->payments()->whereNull('payment_type_id')->sum('amount');
    }

    public function getRemainingBalanceAttribute()
    {
        return max(0, ($this->final_price ?? $this->total_price) - $this->total_paid);
    }

    public function kioskInvoices()
    {
        return $this->hasMany(KioskInvoice::class, 'reservation_id');
    }

    public function additionalServices()
    {
        return $this->hasMany(ReservationAdditionalService::class, 'reservation_id')
            ->with('additionalService');
    }

    public function mealConsumptions()
    {
        return $this->hasMany(ReservationMealConsumption::class, 'reservation_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'reservation_id');
    }

    /**
     * Total de servicios adicionales de la reserva.
     */
    public function getAdditionalServicesTotalAttribute(): float
    {
        return (float) $this->additionalServices()->sum('total');
    }

    public function minibarInventory()
    {
        return $this->hasMany(RoomMinibarInventory::class, 'reservation_id');
    }

    public function minibarCharges()
    {
        return $this->hasMany(ReservationMinibarCharge::class, 'reservation_id');
    }

    /**
     * Total de cargos del minibar
     */
    public function getMinibarChargesTotalAttribute(): float
    {
        return (float) $this->minibarCharges()->sum('total');
    }

    /**
     * Recalcula final_price incluyendo alojamiento (calculated_price ya incluye descuentos) + servicios adicionales + cargos del minibar + cargos a habitación (restaurante), menos cortesías.
     */
    public function recomputeFinalPrice(): void
    {
        $base = $this->getLodgingBaseForFinalPrice();
        $additionalTotal = $this->additional_services_total;
        $minibarTotal = $this->minibar_charges_total;
        $roomChargesTotal = $this->room_charges_total;

        $courtesy = app(CourtesyGuestDiscountCalculator::class)->calculate($this);
        $courtesyDiscount = (float) ($courtesy['total'] ?? 0);

        $breakdown = $this->price_breakdown ?? [];
        $breakdown['courtesy_guests'] = $courtesy['courtesy_guests'] ?? 0;
        $breakdown['courtesy_discount'] = $courtesyDiscount;
        $breakdown['courtesy_per_guest'] = $courtesy['per_guest_total'] ?? 0;
        $breakdown['courtesy_lodging_per_guest'] = $courtesy['lodging_per_guest'] ?? 0;
        $breakdown['courtesy_services_per_guest'] = $courtesy['services_per_guest'] ?? 0;
        $this->price_breakdown = $breakdown;

        $this->final_price = round(max(0, $base + $additionalTotal + $minibarTotal + $roomChargesTotal - $courtesyDiscount), 2);
        $this->saveQuietly();
    }

    /**
     * Base de hospedaje para el total final (incluye habitaciones hijas en reservas de grupo).
     */
    public function getEffectiveLodgingPrice(): float
    {
        if ($this->manual_price_override) {
            $gross = (float) ($this->total_price ?? 0);
            $breakdown = $this->price_breakdown ?? [];
            $discount = (float) ($breakdown['discount'] ?? 0);

            // Si el breakdown no trae descuento pero hay cupón/descuento manual, recalcular neto.
            if (
                $discount <= 0 &&
                (!empty($this->promotion_code) || (float) ($this->discount_amount ?? 0) > 0)
            ) {
                $result = app(ReservationPriceCalculator::class)->calculatePrice($this, false);
                return (float) ($result['calculated_price'] ?? max(0, $gross));
            }

            return max(0.0, round($gross - $discount, 2));
        }

        return (float) ($this->calculated_price ?? $this->total_price ?? 0);
    }

    public function getLodgingBaseForFinalPrice(): float
    {
        if ($this->parent_reservation_id) {
            return $this->getEffectiveLodgingPrice();
        }

        $base = $this->getEffectiveLodgingPrice();

        if ($this->is_group_reservation) {
            $this->loadMissing('childReservations');
            foreach ($this->childReservations as $child) {
                $base += $child->getEffectiveLodgingPrice();
            }
        }

        return $base;
    }

    /**
     * Obtener facturas del kiosko pendientes de pago (con credit = true y payed = false)
     */
    public function getPendingKioskInvoicesAttribute()
    {
        return $this->kioskInvoices()
            ->whereHas('payment_type', function($query) {
                $query->where('credit', true);
            })
            ->where('payed', false)
            ->whereNull('cancelled_at')
            ->with(['payment_type', 'details.kiosk_unit.product'])
            ->get();
    }

    /**
     * Calcular el total pendiente de facturas del kiosko
     */
    public function getTotalPendingKioskInvoicesAttribute()
    {
        return $this->pendingKioskInvoices->sum(function($invoice) {
            return $invoice->details->sum(function($detail) {
                return $detail->price ?? 0;
            });
        });
    }

    /**
     * Calcular el saldo total pendiente (reserva + facturas kiosko)
     */
    public function getTotalPendingBalanceAttribute()
    {
        $reservationBalance = $this->remaining_balance;
        $kioskBalance = $this->total_pending_kiosk_invoices;
        return $reservationBalance + $kioskBalance;
    }

    /**
     * Obtener consumo de alimentación por tipo y fecha
     */
    public function getMealConsumptionByType(string $mealType, $date = null): int
    {
        $query = $this->mealConsumptions()->where('meal_type', $mealType);
        
        if ($date) {
            $query->whereDate('consumption_date', $date);
        }
        
        return $query->sum('quantity_consumed');
    }

    /**
     * Obtener cantidad de comidas incluidas por tipo (total estadía).
     * Enlace con servicios adicionales: solo se cuentan servicios con is_food_service=true
     * y (meal_type = $mealType o meal_type null = "alimentación completa" aplica a los 3 tipos).
     */
    public function getIncludedMealQuantity(string $mealType): int
    {
        return $this->additionalServices()
            ->whereHas('additionalService', function($query) use ($mealType) {
                $query->where('is_food_service', true)
                      ->where(function($q) use ($mealType) {
                          $q->where('meal_type', $mealType)
                            ->orWhereNull('meal_type'); // Alimentación completa = aplica a desayuno, almuerzo y cena
                      });
            })
            ->get()
            ->sum(function($ras) {
                $days = (float) ($ras->service_days ?? 1);
                return (int) $ras->quantity * $days;
            });
    }

    /**
     * Obtener cantidad de comidas incluidas por tipo para un día concreto.
     * Si la fecha no está dentro de [check_in_date, check_out_date], devuelve 0.
     * Mismo enlace: servicios con meal_type = tipo o meal_type null (completa).
     */
    public function getIncludedMealQuantityPerDay(string $mealType, $date): int
    {
        $date = Carbon::parse($date)->startOfDay();
        $checkIn = $this->check_in_date->startOfDay();
        $checkOut = $this->check_out_date ? $this->check_out_date->startOfDay() : $checkIn;

        if ($date->lt($checkIn) || $date->gt($checkOut)) {
            return 0;
        }

        return $this->additionalServices()
            ->whereHas('additionalService', function($query) use ($mealType) {
                $query->where('is_food_service', true)
                      ->where(function($q) use ($mealType) {
                          $q->where('meal_type', $mealType)
                            ->orWhereNull('meal_type'); // Alimentación completa = aplica a los 3 tipos
                      });
            })
            ->get()
            ->sum(function($ras) {
                return (int) $ras->quantity;
            });
    }

    /**
     * Verificar si puede consumir comida incluida.
     * Con $date: lógica por día (incluidos ese día vs consumidos ese día).
     * Sin $date: lógica total estadía (comportamiento anterior).
     */
    public function canConsumeIncludedMeal(string $mealType, $date = null): bool
    {
        if ($date !== null) {
            $included = $this->getIncludedMealQuantityPerDay($mealType, $date);
            $consumed = $this->getMealConsumptionByType($mealType, $date);
            return $consumed < $included;
        }
        $included = $this->getIncludedMealQuantity($mealType);
        $consumed = $this->getMealConsumptionByType($mealType, $date);
        return $consumed < $included;
    }

    /**
     * Obtener cantidad restante de comidas incluidas.
     * Con $date: lógica por día. Sin $date: total estadía.
     */
    public function getRemainingIncludedMeals(string $mealType, $date = null): int
    {
        if ($date !== null) {
            $included = $this->getIncludedMealQuantityPerDay($mealType, $date);
            $consumed = $this->getMealConsumptionByType($mealType, $date);
            return max(0, $included - $consumed);
        }
        $included = $this->getIncludedMealQuantity($mealType);
        $consumed = $this->getMealConsumptionByType($mealType, $date);
        return max(0, $included - $consumed);
    }
}
