<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 3 slice 9: mirror of Express store.notifications records.
 */
class Notification extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'notifications';

    protected $keyType = 'string';

    protected $primaryKey = 'id';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'recipient_username',
        'recipient_role',
        'type',
        'title',
        'message',
        'ticket_ref',
        'href',
        'from_username',
        'from_name',
        'from_role',
        'read_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Express-shaped notification DTO (matches store.js records).
     *
     * @return array<string, mixed>
     */
    public function toExpressArray(): array
    {
        return [
            'id' => $this->id,
            'recipientUsername' => $this->recipient_username,
            'recipientRole' => $this->recipient_role,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'ticketRef' => $this->ticket_ref,
            'href' => $this->href,
            'fromUsername' => $this->from_username,
            'fromName' => $this->from_name,
            'fromRole' => $this->from_role,
            'read' => $this->read_at !== null,
            'at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
