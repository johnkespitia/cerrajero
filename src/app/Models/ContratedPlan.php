<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContratedPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'starting_date',
        'expiration_date',
        'short_description',
        'classes',
        'taked_classes',
        'professor_id',
        'plan_extra_details'
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'students_contrated_plans', 'student_id', 'contrated_plan_id');
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }
}
