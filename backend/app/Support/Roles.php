<?php

namespace App\Support;

/**
 * Canonical role registry — mirrors docker/web/config/roles.js 1:1.
 * Express remains the live RBAC source; this copy is for Laravel identity only.
 */
final class Roles
{
    public const SUPERVISOR = 'supervisor';

    public const DEPT_HEAD = 'dept_head';

    public const RM_OFFICER = 'rm_officer';

    public const EXECUTIVE = 'executive';

    public const PRESIDENT = 'president';

    public const ADMIN = 'admin';

    public const EMPLOYEE = 'employee';

    /** @var array<string, array{id: string, label: string, path: string, assignable: bool}> */
    public const DEFINITIONS = [
        self::SUPERVISOR => [
            'id' => self::SUPERVISOR,
            'label' => 'Ticket Reporter',
            'path' => '/supervisor',
            'assignable' => true,
        ],
        self::DEPT_HEAD => [
            'id' => self::DEPT_HEAD,
            'label' => 'Department Head / Vice President',
            'path' => '/dept',
            'assignable' => true,
        ],
        self::RM_OFFICER => [
            'id' => self::RM_OFFICER,
            'label' => 'Risk Management Officer (RMO)',
            'path' => '/officer',
            'assignable' => true,
        ],
        self::EXECUTIVE => [
            'id' => self::EXECUTIVE,
            'label' => 'Executive Committee',
            'path' => '/executive',
            'assignable' => true,
        ],
        self::PRESIDENT => [
            'id' => self::PRESIDENT,
            'label' => 'President',
            'path' => '/president',
            'assignable' => true,
        ],
        self::ADMIN => [
            'id' => self::ADMIN,
            'label' => 'System Administrator',
            'path' => '/admin',
            'assignable' => true,
        ],
        self::EMPLOYEE => [
            'id' => self::EMPLOYEE,
            'label' => 'Employee',
            'path' => '/dashboard',
            'assignable' => false,
        ],
    ];

    public static function label(?string $roleId): string
    {
        if ($roleId === null || $roleId === '') {
            return '';
        }

        return self::DEFINITIONS[$roleId]['label'] ?? $roleId;
    }

    public static function isKnown(?string $roleId): bool
    {
        return $roleId !== null && isset(self::DEFINITIONS[$roleId]);
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::DEFINITIONS);
    }
}
