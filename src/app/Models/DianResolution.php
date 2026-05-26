<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DianResolution extends Model
{
    use HasFactory;

    protected $table = 'dian_resolutions';

    protected $fillable = [
        'company_id',
        'environment',
        'document_type',
        'prefix',
        'resolution_number',
        'resolution_date',
        'from_number',
        'to_number',
        'valid_from',
        'valid_to',
        'technical_key',
        'current_number',
        'active',
    ];

    protected $casts = [
        'resolution_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'from_number' => 'integer',
        'to_number' => 'integer',
        'current_number' => 'integer',
        'active' => 'boolean',
    ];

    protected $hidden = [
        'technical_key',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyFiscalProfile::class, 'company_id');
    }

    public function electronicDocuments()
    {
        return $this->hasMany(ElectronicDocument::class, 'resolution_id');
    }

    public function isExhausted(): bool
    {
        return $this->current_number >= $this->to_number;
    }

    public function remaining(): int
    {
        return max(0, $this->to_number - max($this->current_number, $this->from_number - 1));
    }
}
