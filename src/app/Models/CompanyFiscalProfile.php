<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyFiscalProfile extends Model
{
    use HasFactory;

    protected $table = 'company_fiscal_profiles';

    protected $fillable = [
        'legal_name',
        'trade_name',
        'nit',
        'dv',
        'tax_regime_code',
        'tax_responsibilities',
        'economic_activity_code',
        'address_line',
        'city_code_dian',
        'country_code',
        'email',
        'phone',
        'environment',
        'migration_cutoff_date',
        'legacy_pt_name',
        'active',
        'activated_at',
    ];

    protected $casts = [
        'tax_responsibilities' => 'array',
        'migration_cutoff_date' => 'datetime',
        'activated_at' => 'datetime',
        'active' => 'boolean',
        'dv' => 'integer',
    ];

    public function certificates()
    {
        return $this->hasMany(FiscalCertificate::class, 'company_id');
    }

    public function softwareCredentials()
    {
        return $this->hasMany(DianSoftwareCredential::class, 'company_id');
    }

    public function resolutions()
    {
        return $this->hasMany(DianResolution::class, 'company_id');
    }

    public function electronicDocuments()
    {
        return $this->hasMany(ElectronicDocument::class, 'company_id');
    }

    public function activeCertificate(string $environment)
    {
        return $this->certificates()
            ->where('environment', $environment)
            ->where('active', true)
            ->first();
    }
}
