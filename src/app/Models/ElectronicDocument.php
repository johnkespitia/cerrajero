<?php

namespace App\Models;

use App\Domain\ElectronicInvoicing\Enums\DocumentStatus;
use App\Domain\ElectronicInvoicing\Enums\DocumentType;
use App\Domain\ElectronicInvoicing\StateMachine\StateTransitions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class ElectronicDocument extends Model
{
    use HasFactory;

    protected $table = 'electronic_documents';

    protected $fillable = [
        'company_id',
        'resolution_id',
        'document_type',
        'reference_code',
        'dian_number',
        'cufe_cude',
        'qr_url',
        'xml_unsigned_path',
        'xml_signed_path',
        'attached_document_path',
        'pdf_path',
        'dian_track_id',
        'dian_zip_key',
        'software_security_code',
        'status',
        'environment',
        'dian_application_response_path',
        'dian_is_valid',
        'dian_status_code',
        'dian_error_messages',
        'subtotal',
        'total_taxes',
        'total',
        'currency_code',
        'issue_date',
        'payment_means_code',
        'payment_terms',
        'due_date',
        'notes',
        'source_type',
        'source_id',
        'acquirer_id',
        'references_document_id',
        'contingency',
        'contingency_reason',
        'contingency_emitted_at',
        'contingency_synced_at',
        'last_attempt_at',
        'attempts',
        'next_retry_at',
        'legacy_pt_id',
    ];

    protected $casts = [
        'dian_is_valid' => 'boolean',
        'dian_error_messages' => 'array',
        'subtotal' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'total' => 'decimal:2',
        'issue_date' => 'datetime',
        'due_date' => 'date',
        'contingency' => 'boolean',
        'contingency_emitted_at' => 'datetime',
        'contingency_synced_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'attempts' => 'integer',
        'source_id' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(CompanyFiscalProfile::class, 'company_id');
    }

    public function resolution()
    {
        return $this->belongsTo(DianResolution::class, 'resolution_id');
    }

    public function acquirer()
    {
        return $this->belongsTo(ElectronicDocumentAcquirer::class, 'acquirer_id');
    }

    public function referencesDocument()
    {
        return $this->belongsTo(ElectronicDocument::class, 'references_document_id');
    }

    public function references()
    {
        return $this->hasMany(ElectronicDocument::class, 'references_document_id');
    }

    public function events()
    {
        return $this->hasMany(ElectronicDocumentEvent::class, 'electronic_document_id');
    }

    public function isTerminal(): bool
    {
        return DocumentStatus::isTerminal((string) $this->status);
    }

    public function isInitial(): bool
    {
        return DocumentStatus::isInitial((string) $this->status);
    }

    public function transitionTo(string $next): void
    {
        $from = (string) $this->status;
        StateTransitions::assertTransition($from, $next);
        $this->status = $next;
    }

    public function setDocumentTypeAttribute($value)
    {
        DocumentType::assert((string) $value);
        $this->attributes['document_type'] = $value;
    }

    public function setStatusAttribute($value)
    {
        DocumentStatus::assert((string) $value);
        $this->attributes['status'] = $value;
    }

    public function setEnvironmentAttribute($value)
    {
        \App\Domain\ElectronicInvoicing\Enums\FiscalEnvironment::assert((string) $value);
        $this->attributes['environment'] = $value;
    }

    /**
     * Guard rail used by NC / ND. Slice de emisión completará la lógica.
     */
    public function assertCanReference(ElectronicDocument $original): void
    {
        if (!DocumentType::isReferencing((string) $this->document_type)) {
            throw new InvalidArgumentException(
                'Only NC / ND can reference another electronic document.'
            );
        }
        if ($original->company_id !== $this->company_id) {
            throw new InvalidArgumentException(
                'Cross-company references are not allowed.'
            );
        }
    }
}
