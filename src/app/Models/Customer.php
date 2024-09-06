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
        "active"
    ];

    public function kiosk_invoices()
    {
        return $this->hasMany(KioskInvoice::class, "customer_id");
    }
}
