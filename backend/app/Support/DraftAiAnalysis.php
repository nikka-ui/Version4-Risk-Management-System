<?php

namespace App\Support;

/**
 * Lightweight draft-time AI stub mirroring Express generateAiAnalysisFromReport
 * enough for create/update drafts. Full routing parity comes with later slices.
 */
final class DraftAiAnalysis
{
    /**
     * @param  array{title?: string, location?: string, fiveW1H?: array<string, string>, evidenceCount?: int}  $input
     * @return array<string, mixed>
     */
    public static function analyze(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));
        $w = is_array($input['fiveW1H'] ?? null) ? $input['fiveW1H'] : [];
        $what = trim((string) ($w['what'] ?? ''));
        $why = trim((string) ($w['why'] ?? ''));
        $where = trim((string) ($w['where'] ?? ''));
        $how = trim((string) ($w['how'] ?? ''));
        $incidentText = mb_strtolower(implode(' ', array_filter([$title, $what, $why, $where, $how, $location])));

        $impactKeywords = ['breach', 'fraud', 'shutdown', 'injury', 'penalt', 'sanction', 'lawsuit', 'leak', 'outage', 'major', 'spill'];
        $likelihoodKeywords = ['often', 'frequent', 'recurr', 'pattern', 'may', 'could', 'lack of', 'weak', 'previous', 'history'];

        $impactHits = self::countHits($impactKeywords, $incidentText);
        $likelihoodHits = self::countHits($likelihoodKeywords, $incidentText);
        $lenBoost = (int) floor(mb_strlen($incidentText) / 450);
        $base = 2;

        $likelihood = self::clamp($base + $lenBoost + (int) round($likelihoodHits * 1.2), 1, 5);
        $impact = self::clamp($base + $lenBoost + (int) round($impactHits * 1.3), 1, 5);
        $severity = self::clamp((int) round(($likelihood + $impact) / 2), 1, 5);
        $riskLevel = self::riskLevelFromSeverity($severity);
        $riskCategory = self::detectCategory($incidentText);
        $department = self::detectDepartment($incidentText, $riskCategory);
        $priority = $severity >= 5 ? 'critical' : ($severity >= 4 ? 'high' : ($severity >= 3 ? 'medium' : 'low'));
        $evidenceCount = (int) ($input['evidenceCount'] ?? 0);

        $confidence = min(0.98, 0.72 + ($evidenceCount >= 1 ? 0.1 : 0) + (mb_strlen($incidentText) > 180 ? 0.08 : 0));

        return [
            'summary' => sprintf(
                'AI analysis: "%s" — %s. Classified as %s with %s severity (likelihood %d/5, impact %d/5). Suggested department: %s.',
                $title !== '' ? $title : 'Untitled',
                $what !== '' ? $what : 'the reported incident',
                $riskCategory,
                $riskLevel['label'],
                $likelihood,
                $impact,
                $department,
            ),
            'likelihood' => $likelihood,
            'impact' => $impact,
            'riskCategory' => $riskCategory,
            'severity' => $severity,
            'riskLevel' => $riskLevel,
            'responsibleDepartment' => $department,
            'priority' => $priority,
            'priorityLabel' => ucfirst($priority),
            'suggestedMitigation' => 'Review controls and document corrective actions with the responsible department.',
            'confidence' => round($confidence, 2),
            'manualReviewRequired' => $confidence < 0.75,
            'routingBasis' => 'title_and_incident_details',
            'routingFieldsUsed' => ['title', 'what', 'why', 'where', 'how'],
            'processedAt' => now()->toIso8601String(),
        ];
    }

    /** @param  list<string>  $keywords */
    private static function countHits(array $keywords, string $text): int
    {
        $n = 0;
        foreach ($keywords as $k) {
            if (str_contains($text, $k)) {
                $n++;
            }
        }

        return $n;
    }

    private static function clamp(int $n, int $min, int $max): int
    {
        return max($min, min($max, $n));
    }

    /** @return array{id: string, label: string} */
    private static function riskLevelFromSeverity(int $severity): array
    {
        return match (true) {
            $severity >= 5 => ['id' => 'critical', 'label' => 'Critical'],
            $severity >= 4 => ['id' => 'high', 'label' => 'High'],
            $severity >= 3 => ['id' => 'moderate', 'label' => 'Moderate'],
            default => ['id' => 'low', 'label' => 'Low'],
        };
    }

    private static function detectCategory(string $text): string
    {
        if (str_contains($text, 'cyber') || str_contains($text, 'data') || str_contains($text, 'system') || str_contains($text, 'network')) {
            return 'technological';
        }
        if (str_contains($text, 'finance') || str_contains($text, 'fraud') || str_contains($text, 'budget')) {
            return 'financial';
        }
        if (str_contains($text, 'legal') || str_contains($text, 'compliance') || str_contains($text, 'regulat')) {
            return 'compliance';
        }
        if (str_contains($text, 'staff') || str_contains($text, 'hr') || str_contains($text, 'employee')) {
            return 'people';
        }

        return 'operational';
    }

    private static function detectDepartment(string $text, string $category): string
    {
        if (str_contains($text, 'it') || str_contains($text, 'system') || $category === 'technological') {
            return 'Information Technology';
        }
        if ($category === 'financial' || str_contains($text, 'finance')) {
            return 'Finance';
        }
        if ($category === 'people' || str_contains($text, 'hr')) {
            return 'Human Resources';
        }

        return 'Operations';
    }
}
