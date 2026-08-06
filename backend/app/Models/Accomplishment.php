<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accomplishment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'ticket_ref',
        'ticket_title',
        'summary',
        'outcomes',
        'submitted_by',
        'submitted_by_name',
        'submitted_at',
        'evidence',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'evidence' => 'array',
            'payload' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RiskTicket::class, 'ticket_ref', 'reference');
    }

    /**
     * @return array<string, mixed>
     */
    public function toExpressArray(): array
    {
        $base = [
            'id' => $this->external_id,
            'ticketRef' => $this->ticket_ref,
            'ticketTitle' => $this->ticket_title ?? '',
            'summary' => $this->summary ?? '',
            'outcomes' => $this->outcomes ?? '',
            'submittedBy' => $this->submitted_by,
            'submittedByName' => $this->submitted_by_name,
            'submittedAt' => optional($this->submitted_at)?->toIso8601String(),
            'evidence' => $this->evidence ?? [],
        ];

        $payload = is_array($this->payload) ? $this->payload : [];

        return array_merge($payload, $base);
    }
}
