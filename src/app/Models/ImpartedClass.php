<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpartedClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'contrated_plan_id',
        'scheduled_class',
        'professor_atendance',
        'comments',
        'class_time',
        'professor_id',

    ];

    public function students_attendance()
    {
        return $this->belongsToMany(Student::class, 'student_assistances', 'imparted_class_id', 'student_id');
    }

    public function links()
    {
        return $this->belongsToMany(Links::class, 'class_links', 'imparted_class_id', 'links_id');
    }

    public function contrated_plan()
    {
        return $this->belongsTo(ContratedPlan::class);
    }
}
