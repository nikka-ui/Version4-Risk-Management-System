<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'name',
        'code',
        'description',
        'head',
        'status',
        'active',
        'auto_approve_low_moderate',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'auto_approve_low_moderate' => 'boolean',
        ];
    }

    /**
     * Payload shape mirrors Express store.json department records.
     *
     * @return array<string, mixed>
     */
    public function toExpressArray(): array
    {
        return [
            'id' => $this->external_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description ?? '',
            'head' => $this->head,
            'status' => $this->status,
            'active' => $this->active,
            'autoApproveLowModerate' => $this->auto_approve_low_moderate,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
