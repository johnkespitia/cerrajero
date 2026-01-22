<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class MaintenanceWork extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_request_id', 'maintainable_type', 'maintainable_id', 'supplier_id',
        'work_type', 'work_date', 'work_start_time', 'work_end_time',
        'description', 'materials_used', 'labor_cost', 'materials_cost',
        'total_cost', 'warranty_start_date', 'warranty_end_date',
        'warranty_months', 'warranty_terms', 'invoice_number',
        'invoice_date', 'invoice_file_url', 'status', 'quality_rating',
        'notes', 'performed_by'
    ];
    
    protected $casts = [
        'work_date' => 'date',
        'work_start_time' => 'datetime',
        'work_end_time' => 'datetime',
        'labor_cost' => 'decimal:2',
        'materials_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
        'invoice_date' => 'date',
        'warranty_months' => 'integer',
        'quality_rating' => 'integer'
    ];
    
    public function maintainable()
    {
        return $this->morphTo();
    }
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    
    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }
    
    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
    
    public function isWarrantyActive()
    {
        if (!$this->warranty_end_date) {
            return false;
        }
        return Carbon::now()->lte($this->warranty_end_date);
    }
    
    public function getWarrantyDaysRemainingAttribute()
    {
        if (!$this->warranty_end_date) {
            return null;
        }
        $days = Carbon::now()->diffInDays($this->warranty_end_date, false);
        return $days > 0 ? $days : 0;
    }
}
