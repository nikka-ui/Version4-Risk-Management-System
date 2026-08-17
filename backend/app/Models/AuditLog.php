<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Phase 10 slice 1: admin audit log row (Postgres SoT for list/export/dashboard).
 */
class AuditLog extends Model
{
    public $incrementing = false;

    protected $table = 'audit_logs';

    protected $keyType = 'string';

    protected $primaryKey = 'id';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'occurred_at',
        'username',
        'role',
        'role_label',
        'action',
        'module',
        'description',
        'ip',
        'device',
        'browser',
        'target_user',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Shape expected by Blade audit views (legacy store.json keys).
     *
     * @return array<string, mixed>
     */
    public function toStoreArray(): array
    {
        /** @var Carbon|null $at */
        $at = $this->occurred_at;

        return [
            'id' => $this->id,
            'at' => $at?->toIso8601String() ?? '',
            'username' => $this->username,
            'role' => $this->role,
            'roleLabel' => $this->role_label ?: $this->role,
            'action' => $this->action,
            'module' => $this->module,
            'description' => $this->description,
            'ip' => $this->ip,
            'device' => $this->device,
            'browser' => $this->browser,
            'targetUser' => $this->target_user,
        ];
    }
}
