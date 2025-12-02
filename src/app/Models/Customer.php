<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        "dni",
        "name",
        "last_name",
        "email",
        "phone_number",
        "active",
        "customer_type", // 'person' o 'company'
        "company_name",
        "company_nit",
        "company_legal_representative",
        "company_address"
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function kiosk_invoices()
    {
        return $this->hasMany(KioskInvoice::class, "customer_id");
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function getDisplayNameAttribute()
    {
        if ($this->customer_type === 'company') {
            return $this->company_name ?? "Empresa #{$this->id}";
        }
        return "{$this->name} {$this->last_name}";
    }
}
