<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintainable_type', 'maintainable_id', 'reported_by', 'reported_date', 'reported_time',
        'issue_type', 'priority', 'title', 'description',
        'location_detail', 'related_inventory_item_id', 'status', 'assigned_to', 'assigned_date',
        'estimated_cost', 'estimated_duration_hours',
        'completed_date', 'completed_time', 'resolution_notes'
    ];
    
    protected $casts = [
        'reported_date' => 'date',
        'reported_time' => 'datetime',
        'assigned_date' => 'date',
        'completed_date' => 'date',
        'completed_time' => 'datetime',
        'estimated_cost' => 'decimal:2',
        'estimated_duration_hours' => 'decimal:2'
    ];
    
    public function maintainable()
    {
        return $this->morphTo();
    }
    
    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
    
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    
    public function maintenanceWorks()
    {
        return $this->hasMany(MaintenanceWork::class, 'maintenance_request_id');
    }
    
    public function relatedInventoryItem()
    {
        return $this->belongsTo(RoomInventoryItem::class, 'related_inventory_item_id');
    }
}
