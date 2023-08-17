<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Professor extends Model
{
    use HasFactory;

    protected $fillable = [
        'legal_identification',
        'hourly_fee',
        'main_photo',
        'brief_resume',
        'cv_url',
        'user_id',
    ];
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'professor_skills');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
