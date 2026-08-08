<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared with Express (docker/web) — same risk_attachments Postgres table.
 * Laravel does not own MinIO bytes in Phase 3 slice 8.
 */
class RiskAttachment extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'risk_attachments';

    protected $keyType = 'string';

    protected $primaryKey = 'id';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'ticket_ref',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_key',
        'uploaded_by',
        'legacy',
        'uploaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'legacy' => 'boolean',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * Express-shaped attachment DTO (matches attachmentRepository.rowToAttachment).
     *
     * @return array<string, mixed>
     */
    public function toExpressArray(): array
    {
        return [
            'id' => $this->id,
            'ticketRef' => $this->ticket_ref,
            'name' => $this->original_name,
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'size' => (int) $this->size_bytes,
            'storageKey' => $this->storage_key,
            'uploadedBy' => $this->uploaded_by,
            'uploadedAt' => optional($this->uploaded_at)?->toIso8601String(),
            'legacy' => (bool) $this->legacy,
        ];
    }
}
