<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DianSoftwareCredential extends Model
{
    use HasFactory;

    protected $table = 'dian_software_credentials';

    protected $fillable = [
        'company_id',
        'environment',
        'software_id',
        'software_pin_secret_ref',
        'test_set_id',
        'production_url',
        'habilitacion_url',
    ];

    protected $hidden = [
        'software_pin_secret_ref',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyFiscalProfile::class, 'company_id');
    }
}
