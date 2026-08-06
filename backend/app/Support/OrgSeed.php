<?php

namespace App\Support;

/**
 * Seed org data — mirrors docker/web/config/admin.js SEED_DEPARTMENTS / SEED_POSITIONS.
 * Used when store.json has no departments/positions arrays yet.
 */
final class OrgSeed
{
    /** @return list<array<string, mixed>> */
    public static function departments(string $now): array
    {
        $rows = [
            ['code' => 'ADMIN', 'name' => 'Administration', 'description' => 'Corporate administration and governance', 'status' => 'active'],
            ['code' => 'FIN', 'name' => 'Finance', 'description' => 'Finance and accounting operations', 'status' => 'active'],
            ['code' => 'OPS', 'name' => 'Operations', 'description' => 'Core business operations', 'status' => 'active'],
            ['code' => 'IT', 'name' => 'Information Technology', 'description' => 'IT infrastructure and systems', 'status' => 'active'],
            ['code' => 'HR', 'name' => 'Human Resources', 'description' => 'Human resources and talent management', 'status' => 'active'],
            ['code' => 'BD', 'name' => 'Business Development', 'description' => 'Business development and partnerships', 'status' => 'active'],
            ['code' => 'RMO', 'name' => 'RMO', 'description' => 'Risk Management Officer (RMO)', 'status' => 'active'],
            ['code' => 'PCEO', 'name' => 'PCEO', 'description' => 'President and Chief Executive Office', 'status' => 'active'],
            ['code' => 'IA', 'name' => 'Internal Audit', 'description' => 'Internal audit and assurance', 'status' => 'active'],
        ];

        return array_map(
            static fn (array $row, int $i) => array_merge($row, [
                'id' => 'dept-'.($i + 1),
                'head' => null,
                'active' => $row['status'] !== 'inactive',
                'autoApproveLowModerate' => false,
                'createdAt' => $now,
                'updatedAt' => $now,
            ]),
            $rows,
            array_keys($rows),
        );
    }

    /** @return list<array<string, mixed>> */
    public static function positions(string $now): array
    {
        $names = [
            'Risk Reporter',
            'Department Head / Vice President',
            'Risk Management Officer',
            'Audit & Compliance Officer',
            'Executive Committee Member',
            'President / CEO',
            'System Administrator',
        ];

        return array_map(
            static fn (string $name, int $i) => [
                'id' => 'pos-'.($i + 1),
                'name' => $name,
                'active' => true,
                'createdAt' => $now,
                'updatedAt' => $now,
            ],
            $names,
            array_keys($names),
        );
    }
}
