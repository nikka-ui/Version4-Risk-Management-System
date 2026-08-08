<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 3 slice 9: mirror of Express store.reportLogs records.
 */
class ReportLog extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'report_logs';

    protected $keyType = 'string';

    protected $primaryKey = 'id';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'ticket_ref',
        'title',
        'submitted_by',
        'submitter_role',
        'status',
        'action',
        'detail',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Express-shaped report-log DTO (matches store.js records).
     *
     * @return array<string, mixed>
     */
    public function toExpressArray(): array
    {
        return [
            'id' => $this->id,
            'ticketRef' => $this->ticket_ref,
            'title' => $this->title,
            'submittedBy' => $this->submitted_by,
            'submitterRole' => $this->submitter_role,
            'status' => $this->status,
            'action' => $this->action,
            'detail' => $this->detail,
            'at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
