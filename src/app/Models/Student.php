<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_identification',
        'main_photo',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contrated_plans()
    {
        return $this->belongsToMany(ContratedPlan::class, 'students_contrated_plans', 'contrated_plan_id', 'student_id');
    }
}
