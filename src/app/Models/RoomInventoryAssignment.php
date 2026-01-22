<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomInventoryAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignable_type',
        'assignable_id',
        'item_id',
        'quantity',
        'status',
        'condition_notes',
        'assigned_at',
        'assigned_by',
        'last_checked_at',
        'last_checked_by',
        'repair_date',
        'repair_notes',
        'maintenance_warranty_expires_at',
        'repaired_by',
        'active'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'repair_date' => 'date',
        'maintenance_warranty_expires_at' => 'date',
        'active' => 'boolean'
    ];

    // Relación polimórfica: puede ser Room o CommonArea
    public function assignable()
    {
        return $this->morphTo();
    }

    public function item()
    {
        return $this->belongsTo(RoomInventoryItem::class, 'item_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function lastCheckedBy()
    {
        return $this->belongsTo(User::class, 'last_checked_by');
    }

    public function repairedBy()
    {
        return $this->belongsTo(User::class, 'repaired_by');
    }

    public function history()
    {
        return $this->hasMany(RoomInventoryHistory::class, 'assignment_id');
    }

    // Helper para obtener el nombre de la ubicación
    public function getLocationNameAttribute()
    {
        if ($this->assignable_type === Room::class) {
            return $this->assignable->display_name ?? "Habitación #{$this->assignable_id}";
        } elseif ($this->assignable_type === CommonArea::class) {
            return $this->assignable->display_name ?? "Zona Común #{$this->assignable_id}";
        }
        return "Ubicación #{$this->assignable_id}";
    }
}
