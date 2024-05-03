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
        'plan_extra_details',
        'hourly_fee',
        'estimated_class_duration'
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'students_contrated_plans', 'contrated_plan_id', 'student_id');
    }

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'contrated_plans_tags', 'contrated_plan_id', 'tag_id');
    }

    public function imparted_classes()
    {
        return $this->hasMany(ImpartedClass::class);
    }

    public function substitutes()
    {
        return $this->hasMany(SubstitutePlan::class);
    }
}
