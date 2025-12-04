<?php

namespace App\Models;

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
        'free_reservation_reason',
        'free_reservation_reference',
        'special_requests',
        'cancellation_reason',
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
        // Campos para reservas agrupadas (múltiples habitaciones)
        'parent_reservation_id',
        'is_group_reservation',
        'room_sequence'
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
        'email_sent' => 'boolean',
        'email_sent_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
        'check_in_reminder_sent' => 'boolean',
        'check_in_reminder_sent_at' => 'datetime',
        'manual_price_override' => 'boolean',
        'early_check_in' => 'boolean',
        'late_check_out' => 'boolean',
        'price_breakdown' => 'array',
    ];

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

    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }

    public function getRemainingBalanceAttribute()
    {
        return max(0, $this->final_price ?? $this->total_price - $this->total_paid);
    }
}
