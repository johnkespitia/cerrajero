<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalCertificate extends Model
{
    use HasFactory;

    protected $table = 'fiscal_certificates';

    protected $fillable = [
        'company_id',
        'environment',
        'subject_cn',
        'issuer_cn',
        'serial_number',
        'not_before',
        'not_after',
        'fingerprint_sha256',
        'storage_path',
        'password_secret_ref',
        'active',
        'loaded_at',
    ];

    protected $casts = [
        'not_before' => 'datetime',
        'not_after' => 'datetime',
        'loaded_at' => 'datetime',
        'active' => 'boolean',
    ];

    protected $hidden = [
        'storage_path',
        'password_secret_ref',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyFiscalProfile::class, 'company_id');
    }

    public function daysToExpiry(?\DateTimeImmutable $now = null): ?int
    {
        if (!$this->not_after) {
            return null;
        }
        $reference = $now ?? new \DateTimeImmutable('now');
        $diff = $reference->diff($this->not_after->toDateTimeImmutable());
        $days = (int) $diff->days;
        return $diff->invert ? -1 * $days : $days;
    }
}
