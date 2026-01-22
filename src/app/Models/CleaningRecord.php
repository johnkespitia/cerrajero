<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleaningRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'cleanable_type', 'cleanable_id', 'reservation_id', 'cleaned_by', 'cleaning_date', 'cleaning_time',
        'cleaning_type', 'status', 'duration_minutes', 'observations',
        'issues_found', 'items_missing', 'quality_score',
        'supervisor_checked', 'supervisor_id', 'supervisor_notes',
        'next_cleaning_due'
    ];
    
    protected $casts = [
        'cleaning_date' => 'date',
        'cleaning_time' => 'datetime',
        'supervisor_checked' => 'boolean',
        'next_cleaning_due' => 'date',
        'quality_score' => 'integer',
        'duration_minutes' => 'integer'
    ];
    
    public function cleanable()
    {
        return $this->morphTo();
    }
    
    public function cleanedBy()
    {
        return $this->belongsTo(User::class, 'cleaned_by');
    }
    
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
    
    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
