<?php

namespace App\Support;

/**
 * Phase 11 slice 5: PHP fallback mirrors Express taxonomy (same contract as ai-service classify).
 */
final class DraftAiTaxonomy
{
    /** @var array<string, string> */
    private const CATEGORY_LABELS = [
        'operational' => 'Operational',
        'financial' => 'Financial',
        'compliance' => 'Compliance',
        'strategic' => 'Strategic',
        'reputational' => 'Reputational',
        'environmental' => 'Environmental Risk',
    ];

    /** @var array<string, string> */
    private const PRIORITY_LABELS = [
        'urgent' => 'Urgent',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    /** @var array<string, string> */
    private const LARAVEL_DEPT_MAP = [
        'IT' => 'Information Technology',
        'Finance/Accounting' => 'Finance',
        'HRMS' => 'Human Resources',
        'Admin' => 'Administration',
        'Corp Plan' => 'Business Development',
        'Corp Sec' => 'Administration',
        'MMCD' => 'Operations',
        'Treasury' => 'Finance',
    ];

    /** @var list<string> */
    private const IT_SIGNALS = [
        'server room', 'server rack', 'data center', 'datacenter', 'network room', 'network outage',
        'server outage', 'server failure', 'server down', 'switch failure', 'firewall', 'cyber attack',
        'cybersecurity', 'ransomware', 'malware', 'phishing', 'data breach', 'database', 'vpn',
        'email server', 'application crash', 'it infrastructure', 'helpdesk', 'endpoint', 'workstation',
    ];

    /** @var list<string> */
    private const IMPACT_KEYWORDS = [
        'breach', 'fraud', 'shutdown', 'injury', 'penalt', 'sanction', 'lawsuit', 'leak', 'outage',
        'major', 'spill', 'contamination',
    ];

    /** @var list<string> */
    private const LIKELIHOOD_KEYWORDS = [
        'often', 'frequent', 'recurr', 'pattern', 'may', 'could', 'lack of', 'weak', 'previous', 'history',
    ];

    /** @var list<string> */
    private const REPORTER_BLOCKLIST = [
        'information technology', 'it department', 'operations', 'finance', 'administration',
        'human resources', 'hrms', 'internal audit', 'treasury', 'corp plan', 'corp sec',
        'risk management office', 'rmo', 'pceo',
    ];

    /**
     * @param  array{title?: string, location?: string, description?: string, fiveW1H?: array<string, string>, evidenceCount?: int}  $input
     * @return array<string, mixed>
     */
    public static function analyze(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));
        $w = self::fiveW1H($input);
        $incident = self::incidentText($title, $w);
        $supplemental = mb_strtolower(trim(implode(' ', array_filter([$title, $location, $w['when'] ?? '']))));

        $impactHits = self::countHits(self::IMPACT_KEYWORDS, $incident);
        $likelihoodHits = self::countHits(self::LIKELIHOOD_KEYWORDS, $incident);
        $lenBoost = (int) floor(mb_strlen($incident) / 450);
        $base = 2;
        $likelihood = self::clampInt($base + $lenBoost + (int) round($likelihoodHits * 1.2), 1, 5);
        $impact = self::clampInt($base + $lenBoost + (int) round($impactHits * 1.3), 1, 5);
        $severity = self::clampInt((int) round(($likelihood + $impact) / 2), 1, 5);
        $riskLevel = self::riskLevelFromSeverity($severity);

        $category = self::detectCategory($incident);
        $expressDept = self::detectDepartment($title, $w, $category);
        $department = self::mapLaravelDepartment($expressDept);
        $priority = self::determinePriority($riskLevel, $severity);
        $mitigation = self::suggestedMitigation($category, $riskLevel, $w);

        $evidenceCount = (int) ($input['evidenceCount'] ?? 0);
        $confidence = max(
            0.5,
            min(
                0.98,
                0.72
                + ($evidenceCount >= 1 ? 0.1 : 0)
                + (mb_strlen($incident) + mb_strlen($supplemental) > 180 ? 0.08 : 0)
                + (mb_strlen($w['what'] ?? '') > 40 ? 0.06 : 0)
                + ($department !== '' ? 0.04 : 0),
            ),
        );

        $what = $w['what'] !== '' ? $w['what'] : 'the reported incident';
        $why = $w['why'] ?? '';
        $cause = $why !== '' ? "cause: {$why}" : 'see report for details';
        $summary = sprintf(
            'AI analysis: "%s" — %s (%s). Classified as %s with %s severity (likelihood %d/5, impact %d/5). Responsible department assigned from risk title and incident details — not from your reporting unit: %s with %s priority.',
            $title !== '' ? $title : 'Untitled',
            $what,
            $cause,
            self::CATEGORY_LABELS[$category] ?? $category,
            $riskLevel['label'],
            $likelihood,
            $impact,
            $department,
            self::PRIORITY_LABELS[$priority] ?? $priority,
        );

        return [
            'summary' => $summary,
            'likelihood' => $likelihood,
            'impact' => $impact,
            'riskCategory' => $category,
            'severity' => $severity,
            'riskLevel' => $riskLevel,
            'responsibleDepartment' => $department,
            'priority' => $priority,
            'priorityLabel' => self::PRIORITY_LABELS[$priority] ?? ucfirst($priority),
            'suggestedMitigation' => $mitigation,
            'confidence' => round($confidence, 2),
            'manualReviewRequired' => $confidence < 0.75,
            'routingBasis' => 'title_and_incident_details',
            'routingFieldsUsed' => ['title', 'what', 'why', 'where', 'how'],
            'processedAt' => now()->toIso8601String(),
            'engine' => 'taxonomy-v1',
            'mode' => 'taxonomy',
        ];
    }

    /** @param  array<string, mixed>  $input
     * @return array{what: string, why: string, where: string, when: string, who: string, how: string}
     */
    private static function fiveW1H(array $input): array
    {
        $raw = is_array($input['fiveW1H'] ?? null) ? $input['fiveW1H'] : [];
        $what = trim((string) ($raw['what'] ?? $input['description'] ?? ''));

        return [
            'what' => $what,
            'why' => trim((string) ($raw['why'] ?? '')),
            'where' => trim((string) ($raw['where'] ?? '')),
            'when' => trim((string) ($raw['when'] ?? '')),
            'who' => trim((string) ($raw['who'] ?? '')),
            'how' => trim((string) ($raw['how'] ?? '')),
        ];
    }

    /** @param  array{what: string, why: string, where: string, when: string, who: string, how: string}  $w */
    private static function incidentText(string $title, array $w): string
    {
        $corpus = [
            'title' => self::stripReporterOrg($title),
            'what' => self::stripReporterOrg($w['what']),
            'why' => self::stripReporterOrg($w['why']),
            'where' => self::stripReporterOrg($w['where']),
            'how' => self::stripReporterOrg($w['how']),
        ];

        return mb_strtolower(trim(implode(' ', array_filter($corpus))));
    }

    private static function stripReporterOrg(string $text): string
    {
        $s = $text;
        foreach (self::REPORTER_BLOCKLIST as $label) {
            $s = preg_replace('/'.preg_quote($label, '/').'/iu', ' ', $s) ?? $s;
        }

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    private static function hasItSignals(string $text): bool
    {
        foreach (self::IT_SIGNALS as $term) {
            if (str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    private static function detectCategory(string $text): string
    {
        if (self::hasItSignals($text)) {
            return 'operational';
        }

        $checks = [
            'environmental' => ['environment', 'environmental', 'pollution', 'spill', 'emission', 'waste', 'hazardous', 'contamination', 'ecosystem', 'climate'],
            'compliance' => ['audit finding', 'compliance breach', 'compliance violation', 'noncompliance', 'non-compliance', 'regulatory breach', 'regulatory violation', 'penalt', 'sanction', 'iso 31000', 'policy violation'],
            'financial' => ['finance', 'financial', 'invoice', 'payment', 'budget', 'tax', 'revenue', 'fraud', 'accounting error', 'ledger', 'accounts payable'],
            'reputational' => ['reputation', 'reputational', 'brand damage', 'public relations', 'media coverage', 'negative publicity', 'customer trust', 'lawsuit', 'scandal', 'social media backlash'],
            'strategic' => ['strategy', 'strategic', 'market share', 'competitor', 'competitors', 'growth', 'roadmap'],
        ];

        foreach ($checks as $category => $terms) {
            foreach ($terms as $term) {
                if (str_contains($text, $term)) {
                    return $category;
                }
            }
        }

        return 'operational';
    }

    /** @param  array{what: string, why: string, where: string, when: string, who: string, how: string}  $w */
    private static function detectDepartment(string $title, array $w, string $category): string
    {
        $corpus = [
            'title' => self::stripReporterOrg($title),
            'what' => self::stripReporterOrg($w['what']),
            'why' => self::stripReporterOrg($w['why']),
            'how' => self::stripReporterOrg($w['how']),
            'where' => self::stripReporterOrg($w['where']),
        ];
        $blob = implode(' ', $corpus);

        if (self::hasItSignals($blob)) {
            return 'IT';
        }

        $keywords = [
            'IT' => ['server room', 'network outage', 'data center', 'cyber', 'malware', 'ransomware', 'firewall', 'database'],
            'Finance/Accounting' => ['finance', 'financial', 'invoice', 'budget', 'fraud', 'accounting', 'ledger', 'tax'],
            'HRMS' => ['human resources', 'payroll', 'harassment', 'hiring', 'termination', 'workplace'],
            'Internal Audit' => ['audit finding', 'compliance', 'policy violation', 'internal control', 'regulatory'],
            'Administration' => ['maintenance', 'building', 'facility', 'hvac', 'plumbing', 'janitorial'],
            'Operations' => ['operational', 'production', 'supply chain', 'logistics', 'warehouse'],
        ];
        $weights = ['title' => 4, 'what' => 5, 'why' => 3, 'how' => 2, 'where' => 2];
        $bestDept = null;
        $bestScore = 0;
        foreach ($keywords as $dept => $terms) {
            $score = 0;
            foreach ($weights as $field => $weight) {
                $fieldText = mb_strtolower($corpus[$field] ?? '');
                foreach ($terms as $term) {
                    if ($fieldText !== '' && str_contains($fieldText, $term)) {
                        $score += $weight;
                    }
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestDept = $dept;
            }
        }
        if ($bestDept !== null && $bestScore > 0) {
            return $bestDept;
        }

        return match ($category) {
            'environmental' => 'Administration',
            'financial' => 'Finance/Accounting',
            'compliance' => 'Internal Audit',
            'reputational' => 'Corp Sec',
            'strategic' => 'Corp Plan',
            default => 'Operations',
        };
    }

    private static function mapLaravelDepartment(string $dept): string
    {
        return self::LARAVEL_DEPT_MAP[$dept] ?? $dept;
    }

    /** @param  array{id: string, label: string}  $riskLevel */
    private static function determinePriority(array $riskLevel, int $severity): string
    {
        $level = $riskLevel['id'] ?? 'low';
        if ($level === 'critical' || $severity >= 5) {
            return 'urgent';
        }
        if ($level === 'high' || $severity >= 4) {
            return 'high';
        }
        if ($level === 'moderate' || $severity >= 3) {
            return 'medium';
        }

        return 'low';
    }

    /** @param  array{what: string, why: string, where: string, when: string, who: string, how: string}  $w
     * @param  array{id: string, label: string}  $riskLevel
     */
    private static function suggestedMitigation(string $category, array $riskLevel, array $w): string
    {
        $what = $w['what'] !== '' ? $w['what'] : 'the reported incident';
        $levelLabel = $riskLevel['label'] ?? 'Moderate';
        $templates = [
            'environmental' => "Contain and assess environmental impact from {$what}. Notify relevant authorities if required, document the incident site, and implement immediate containment measures.",
            'financial' => "Secure affected financial records and transactions related to {$what}. Initiate reconciliation review and escalate to Finance leadership for control assessment.",
            'compliance' => "Document the compliance gap identified in {$what}. Review applicable policies/regulations and prepare a corrective action plan with accountable owners.",
            'reputational' => "Prepare a stakeholder communication plan regarding {$what}. Coordinate with Corporate Secretary and limit further reputational exposure.",
            'strategic' => "Assess strategic implications of {$what} on organizational objectives. Convene planning stakeholders to evaluate impact and response options.",
            'operational' => "Stabilize operations affected by {$what}. Implement interim controls, assign an incident owner, and monitor until permanent corrective actions are in place.",
        ];
        $base = $templates[$category] ?? $templates['operational'];

        return "{$base} Given the {$levelLabel} risk level, prioritize actions within 48–72 hours and report progress to the Risk Management Unit.";
    }

    /** @return array{id: string, label: string} */
    private static function riskLevelFromSeverity(int $severity): array
    {
        if ($severity <= 2) {
            return ['id' => 'low', 'label' => 'Low'];
        }
        if ($severity === 3) {
            return ['id' => 'moderate', 'label' => 'Moderate'];
        }
        if ($severity === 4) {
            return ['id' => 'high', 'label' => 'High'];
        }

        return ['id' => 'critical', 'label' => 'Extreme/Critical'];
    }

    /** @param  list<string>  $keywords */
    private static function countHits(array $keywords, string $text): int
    {
        $n = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                $n++;
            }
        }

        return $n;
    }

    private static function clampInt(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
