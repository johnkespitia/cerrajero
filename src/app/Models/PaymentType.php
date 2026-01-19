<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "active",
        "credit",
        "calculator"
    ];

    protected $casts = [
        'active' => 'boolean',
        'credit' => 'boolean',
        'calculator' => 'boolean',
    ];
}
