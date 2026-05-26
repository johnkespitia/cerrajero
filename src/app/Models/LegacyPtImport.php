<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyPtImport extends Model
{
    use HasFactory;

    protected $table = 'legacy_pt_imports';

    protected $fillable = [
        'company_id',
        'source_pt_name',
        'bundle_path',
        'bundle_hash_sha256',
        'status',
        'total_documents',
        'consistent_count',
        'inconsistent_count',
        'missing_count',
        'report',
        'imported_by',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'report' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_documents' => 'integer',
        'consistent_count' => 'integer',
        'inconsistent_count' => 'integer',
        'missing_count' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyFiscalProfile::class, 'company_id');
    }

    public function importer()
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
