<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CleaningSchedule extends Model
{
    use HasFactory;

    protected $table = 'cleaning_schedule';

    protected $fillable = [
        'cleanable_type', 'cleanable_id', 'cleaning_type', 'frequency_days',
        'assigned_to', 'time_preference', 'day_of_week',
        'active', 'last_cleaned_date', 'next_cleaning_date', 'notes'
    ];
    
    protected $casts = [
        'frequency_days' => 'integer',
        'day_of_week' => 'integer',
        'active' => 'boolean',
        'last_cleaned_date' => 'date',
        'next_cleaning_date' => 'date',
        'time_preference' => 'datetime'
    ];
    
    public function cleanable()
    {
        return $this->morphTo();
    }
    
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    
    public function calculateNextCleaningDate()
    {
        $baseDate = $this->last_cleaned_date ?? Carbon::today();
        $this->next_cleaning_date = $baseDate->copy()->addDays($this->frequency_days);
        $this->save();
    }
}
