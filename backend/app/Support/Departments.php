<?php

namespace App\Support;

/**
 * Department name matching — mirrors docker/web/config/tickets.js DEPARTMENT_ALIASES.
 */
final class Departments
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'it' => ['it', 'information technology', 'i.t.', 'it department'],
        'finance' => [
            'finance', 'finance/accounting', 'accounting', 'finance and accounting',
            'finance & accounting', 'fin', 'finance department',
        ],
        'hr' => ['hr', 'hrms', 'human resources', 'human resource management'],
        'operations' => ['operations', 'ops', 'operation'],
        'admin' => ['admin', 'administration', 'administrative', 'support admin', 'admin support'],
        'internal_audit' => ['internal audit', 'ia', 'audit'],
        'treasury' => ['treasury'],
        'corp_plan' => ['corp plan', 'corporate planning', 'planning'],
        'corp_sec' => ['corp sec', 'corporate secretary', 'governance'],
        'mmcd' => ['mmcd', 'maintenance', 'facilities'],
        'rmo' => [
            'rmo', 'risk management office', 'risk management', 'risk management unit',
            'risk governance office', 'rmu',
        ],
        'business_dev' => ['business development', 'bd', 'business dev'],
        'pceo' => ['pceo', 'president and chief executive office', 'office of the president'],
    ];

    public static function canonical(?string $name): string
    {
        $key = strtolower(trim((string) $name));
        if ($key === '') {
            return '';
        }
        $key = preg_replace('/\s+departments?$/i', '', $key) ?? $key;
        $key = preg_replace('/\s+depts?$/i', '', $key) ?? $key;
        $key = trim($key);

        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($key, $aliases, true)) {
                return $canonical;
            }
        }

        return $key;
    }

    public static function match(?string $a, ?string $b): bool
    {
        $ca = self::canonical($a);
        $cb = self::canonical($b);

        return $ca !== '' && $ca === $cb;
    }

    public static function riskLevelId(?array $ai, ?int $likelihood, ?int $impact): string
    {
        if (is_array($ai['riskLevel'] ?? null) && isset($ai['riskLevel']['id'])) {
            return (string) $ai['riskLevel']['id'];
        }
        $sev = (int) ($ai['severity'] ?? 0);
        if ($sev < 1 && $likelihood && $impact) {
            $sev = (int) round(($likelihood + $impact) / 2);
        }
        if ($sev < 1) {
            $sev = 2;
        }

        return match (true) {
            $sev >= 5 => 'critical',
            $sev >= 4 => 'high',
            $sev >= 3 => 'moderate',
            default => 'low',
        };
    }

    public static function requiresPresidentApproval(?array $ai, ?int $likelihood, ?int $impact): bool
    {
        return in_array(self::riskLevelId($ai, $likelihood, $impact), ['high', 'critical'], true);
    }
}
