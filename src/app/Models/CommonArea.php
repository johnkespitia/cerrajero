<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommonArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'location',
        'area_type',
        'capacity',
        'image_url',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    public function assignments()
    {
        return $this->morphMany(RoomInventoryAssignment::class, 'assignable');
    }

    public function activeAssignments()
    {
        return $this->morphMany(RoomInventoryAssignment::class, 'assignable')
                    ->where('active', true);
    }

    public function history()
    {
        return $this->morphMany(RoomInventoryHistory::class, 'assignable');
    }

    public function getDisplayNameAttribute()
    {
        return $this->name . ($this->code ? " ({$this->code})" : '');
    }

    // Relaciones para aseo y mantenimiento
    public function cleaningRecords()
    {
        return $this->morphMany(CleaningRecord::class, 'cleanable');
    }

    public function latestCleaningRecord()
    {
        return $this->morphOne(CleaningRecord::class, 'cleanable')->latestOfMany('cleaning_date');
    }

    public function maintenanceRequests()
    {
        return $this->morphMany(MaintenanceRequest::class, 'maintainable');
    }

    public function maintenanceWorks()
    {
        return $this->morphMany(MaintenanceWork::class, 'maintainable');
    }

    public function cleaningSchedule()
    {
        return $this->morphOne(CleaningSchedule::class, 'cleanable');
    }
}
