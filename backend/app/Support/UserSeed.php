<?php

namespace App\Support;

/**
 * Built-in demo users for local/dev — mirrors docs/LOGIN.md seed accounts.
 * Used when store.json omits a role (e.g. compliance_officer) or has no users.
 * Passwords come from RMS_SEED_PASSWORD / import merge (never hard-code production secrets).
 */
final class UserSeed
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function users(string $now, string $password): array
    {
        if ($password === '') {
            return [];
        }

        $rows = [
            [
                'username' => 'sys-admin',
                'displayName' => 'System Administrator',
                'email' => 'sys-admin@rms.local',
                'role' => Roles::ADMIN,
                'employeeId' => 'EMP-001',
                'department' => 'Administration',
                'position' => 'System Administrator',
                'canManageUsers' => true,
                'builtIn' => true,
            ],
            [
                'username' => 'admin',
                'displayName' => 'Administrator',
                'email' => 'admin@rms.local',
                'role' => Roles::ADMIN,
                'employeeId' => 'EMP-002',
                'department' => 'Administration',
                'position' => 'System Administrator',
                'canManageUsers' => true,
                'builtIn' => true,
            ],
            [
                'username' => 'reporter',
                'displayName' => 'Ticket Reporter',
                'email' => 'reporter@rms.local',
                'role' => Roles::SUPERVISOR,
                'employeeId' => 'EMP-010',
                'department' => 'Information Technology',
                'position' => 'Risk Reporter',
                'canManageUsers' => false,
                'builtIn' => true,
            ],
            [
                'username' => 'dephead',
                'displayName' => 'IT Department Head',
                'email' => 'dephead@rms.local',
                'role' => Roles::DEPT_HEAD,
                'employeeId' => 'EMP-020',
                'department' => 'Information Technology',
                'position' => 'Department Head / Vice President',
                'canManageUsers' => false,
                'builtIn' => true,
            ],
            [
                'username' => 'rmo',
                'displayName' => 'Risk Management Officer',
                'email' => 'rmo@rms.local',
                'role' => Roles::RM_OFFICER,
                'employeeId' => 'EMP-030',
                'department' => 'RMO',
                'position' => 'Risk Management Officer',
                'canManageUsers' => false,
                'builtIn' => true,
            ],
            [
                'username' => 'compliance',
                'displayName' => 'Compliance Officer',
                'email' => 'compliance@rms.local',
                'role' => Roles::COMPLIANCE_OFFICER,
                'employeeId' => 'EMP-035',
                'department' => 'Internal Audit',
                'position' => 'Audit & Compliance Officer',
                'canManageUsers' => false,
                'builtIn' => true,
            ],
            [
                'username' => 'pceo',
                'displayName' => 'President / CEO',
                'email' => 'pceo@rms.local',
                'role' => Roles::PRESIDENT,
                'employeeId' => 'EMP-040',
                'department' => 'PCEO',
                'position' => 'President / CEO',
                'canManageUsers' => false,
                'builtIn' => true,
            ],
            [
                'username' => 'executive',
                'displayName' => 'Executive Committee',
                'email' => 'executive@rms.local',
                'role' => Roles::EXECUTIVE,
                'employeeId' => 'EMP-050',
                'department' => 'Administration',
                'position' => 'Executive Committee Member',
                'canManageUsers' => false,
                'builtIn' => true,
            ],
        ];

        return array_map(
            static function (array $row) use ($now, $password): array {
                $role = (string) $row['role'];

                return array_merge($row, [
                    'password' => $password,
                    'roleLabel' => Roles::label($role),
                    'active' => true,
                    'status' => 'active',
                    'deleted' => false,
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ]);
            },
            $rows,
        );
    }
}
