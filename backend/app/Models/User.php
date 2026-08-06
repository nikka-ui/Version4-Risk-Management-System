<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'role',
        'role_label',
        'employee_id',
        'department',
        'position',
        'can_manage_users',
        'built_in',
        'active',
        'status',
        'deleted',
        'deleted_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'can_manage_users' => 'boolean',
            'built_in' => 'boolean',
            'active' => 'boolean',
            'deleted' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function isActiveAccount(): bool
    {
        return $this->active && ! $this->deleted && $this->status !== 'deleted';
    }

    /**
     * Public identity payload (mirrors Express publicUser, without secrets).
     *
     * @return array<string, mixed>
     */
    public function toIdentityArray(): array
    {
        $role = $this->role;
        $roleLabel = $this->role_label ?: Roles::label($role);

        return [
            'id' => $this->id,
            'username' => $this->username,
            'employeeId' => $this->employee_id ?? '',
            'email' => $this->email ?? '',
            'department' => $this->department ?? '',
            'position' => $this->position ?? '',
            'role' => $role,
            'roleLabel' => $roleLabel,
            'displayName' => $this->name,
            'status' => $this->status ?: ($this->active ? 'active' : 'inactive'),
            'canManageUsers' => (bool) $this->can_manage_users,
            'builtIn' => (bool) $this->built_in,
            'active' => $this->active && ! $this->deleted,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
