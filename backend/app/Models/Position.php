<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'external_id',
        'name',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Payload shape mirrors Express store.json position records.
     *
     * @return array<string, mixed>
     */
    public function toExpressArray(): array
    {
        return [
            'id' => $this->external_id,
            'name' => $this->name,
            'active' => $this->active,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
