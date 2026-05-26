<?php

namespace App\Models;

use App\Domain\ElectronicInvoicing\Enums\RadianEventCode;
use App\Domain\ElectronicInvoicing\Enums\RadianEventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Persistent RADIAN event emitted against a parent ElectronicDocument.
 *
 * Each row tracks a single SendEventUpdateStatus call: its CUDE, signed
 * XML location, DIAN trackId and the resulting ApplicationResponse.
 * Lightweight bitacora entries continue living on `electronic_document_events`
 * via `radian_event_emitted` / `radian_event_synced` types.
 */
class DianEvent extends Model
{
    use HasFactory;

    protected $table = 'dian_events';

    protected $fillable = [
        'electronic_document_id',
        'event_code',
        'status',
        'cude',
        'xml_signed_path',
        'dian_track_id',
        'dian_status_code',
        'dian_is_valid',
        'dian_error_messages',
        'dian_application_response_path',
        'actor',
        'correlation_id',
        'emitted_at',
        'sent_at',
        'resolved_at',
    ];

    protected $casts = [
        'dian_is_valid' => 'boolean',
        'dian_error_messages' => 'array',
        'emitted_at' => 'datetime',
        'sent_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function setEventCodeAttribute($value): void
    {
        RadianEventCode::assert((string) $value);
        $this->attributes['event_code'] = (string) $value;
    }

    public function setStatusAttribute($value): void
    {
        if (! in_array((string) $value, RadianEventStatus::ALL, true)) {
            throw new InvalidArgumentException("Unknown RADIAN event status [{$value}].");
        }
        $this->attributes['status'] = (string) $value;
    }

    public function electronicDocument(): BelongsTo
    {
        return $this->belongsTo(ElectronicDocument::class, 'electronic_document_id');
    }
}
