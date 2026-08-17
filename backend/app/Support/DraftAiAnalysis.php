<?php

namespace App\Support;

/**
 * PHP fallback stub for draft/submit AI analysis.
 * Phase 11 slice 5: mirrors Express taxonomy when ai-service is unreachable.
 */
final class DraftAiAnalysis
{
    /**
     * @param  array{title?: string, location?: string, fiveW1H?: array<string, string>, evidenceCount?: int}  $input
     * @return array<string, mixed>
     */
    public static function analyze(array $input): array
    {
        return DraftAiTaxonomy::analyze($input);
    }
}
