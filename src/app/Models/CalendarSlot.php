<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarSlot extends Model
{
    use HasFactory;
    protected $fillable = ['professor_id', 'date_slot', 'start_time', 'ebd_time', 'available'];

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }
}
