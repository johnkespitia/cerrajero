<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
    ];

    public function professors()
    {
        return $this->belongsToMany(Professor::class, 'professor_skills');
    }
}
