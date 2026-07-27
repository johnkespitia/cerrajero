<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectronicDocumentAcquirer extends Model
{
    use HasFactory;

    protected $table = 'electronic_document_acquirers';

    protected $fillable = [
        'customer_id',
        'document_type',
        'document_number',
        'dv',
        'legal_name',
        'tax_regime_code',
        'tax_responsibilities',
        'address_line',
        'city_code_dian',
        'country_code',
        'email',
        'phone',
    ];

    protected $casts = [
        'tax_responsibilities' => 'array',
        'dv' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function electronicDocuments()
    {
        return $this->hasMany(ElectronicDocument::class, 'acquirer_id');
    }
}
