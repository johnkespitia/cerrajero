<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ElectronicDocumentEvent extends Model
{
    use HasFactory;

    protected $table = 'electronic_document_events';

    protected $fillable = [
        'electronic_document_id',
        'event_type',
        'payload',
        'error_code',
        'error_message',
        'actor',
        'correlation_id',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function electronicDocument()
    {
        return $this->belongsTo(ElectronicDocument::class, 'electronic_document_id');
    }
}
