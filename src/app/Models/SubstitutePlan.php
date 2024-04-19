<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubstitutePlan extends Model
{
    protected $fillable = [
        'professor_id',
        'contrated_plan_id',
        'start_date',
        'end_date',
    ];

    use HasFactory;

    public function contrated_plan()
    {
        return $this->belongsTo(ContratedPlan::class);
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }
}
