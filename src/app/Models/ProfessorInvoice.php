<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfessorInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'generation_time',
        'start_date',
        'end_date',
        'total_time',
        'total_value',
        'professor_id',
        'comments',
        'sent',
        'approved',
        'payed',
        'sent_date',
        'approved_date',
        'payed_date',
        'signature_img',

    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }

    public function diagnostic_class()
    {
        return $this->hasMany(DiagnosticClass::class);
    }

    public function imparted_class()
    {
        return $this->hasMany(ImpartedClass::class);
    }
}
