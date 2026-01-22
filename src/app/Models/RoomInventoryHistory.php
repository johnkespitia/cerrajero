<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomInventoryHistory extends Model
{
    use HasFactory;

    protected $table = 'room_inventory_history';

    protected $fillable = [
        'assignment_id',
        'assignable_type',
        'assignable_id',
        'item_id',
        'action',
        'old_assignable_type',
        'old_assignable_id',
        'new_assignable_type',
        'new_assignable_id',
        'old_status',
        'new_status',
        'old_quantity',
        'new_quantity',
        'notes',
        'user_id',
        'ip_address',
        'user_agent'
    ];

    public $timestamps = false;
    const UPDATED_AT = null;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function assignment()
    {
        return $this->belongsTo(RoomInventoryAssignment::class, 'assignment_id');
    }

    // Relación polimórfica: puede ser Room o CommonArea
    public function assignable()
    {
        return $this->morphTo();
    }

    public function item()
    {
        return $this->belongsTo(RoomInventoryItem::class, 'item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper para obtener el nombre de la ubicación anterior
    public function getOldLocationNameAttribute()
    {
        if (!$this->old_assignable_type || !$this->old_assignable_id) {
            return null;
        }

        $model = $this->old_assignable_type::find($this->old_assignable_id);
        if ($model) {
            return $model->display_name ?? "Ubicación #{$this->old_assignable_id}";
        }
        return "Ubicación #{$this->old_assignable_id}";
    }

    // Helper para obtener el nombre de la nueva ubicación
    public function getNewLocationNameAttribute()
    {
        if (!$this->new_assignable_type || !$this->new_assignable_id) {
            return null;
        }

        $model = $this->new_assignable_type::find($this->new_assignable_id);
        if ($model) {
            return $model->display_name ?? "Ubicación #{$this->new_assignable_id}";
        }
        return "Ubicación #{$this->new_assignable_id}";
    }
}
