<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'starting_date',
        'starting_time',
        'candidate_name',
        'candidate_email',
        'class_duration',
        'class_closed',
        'comments',
        'hourly_fee',
        'candidate_attended',
        'professor_id'
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }
}
