<?php

namespace App\Services;

use App\Models\AiAnalysisResult;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\DraftAiAnalysis;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Phase 11 slice 1–6: call ai-service /classify; fall back to taxonomy PHP stub; persist each run.
 */
class AiAnalysisService
{
    /**
     * @param  array{title?: string, location?: string, fiveW1H?: array<string, string>, evidenceCount?: int}  $input
     * @return array<string, mixed>
     */
    public function analyze(array $input, ?string $ticketReference = null): array
    {
        $remote = $this->classifyRemote($input);
        if (is_array($remote)) {
            $this->persist($input, $remote, $ticketReference);

            return $remote;
        }

        $local = DraftAiAnalysis::analyze($input);
        $local['source'] = 'php-stub';
        $this->persist($input, $local, $ticketReference);

        return $local;
    }

    /**
     * @param  array{title?: string, location?: string, fiveW1H?: array<string, string>, evidenceCount?: int}  $input
     * @return array<string, mixed>|null
     */
    public function classifyRemote(array $input): ?array
    {
        $base = rtrim((string) config('rms.ai_service_url', ''), '/');
        if ($base === '') {
            return null;
        }

        $timeout = max(1, (int) config('rms.ai_service_timeout', 3));

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->connectTimeout(min(2, $timeout))
                ->post($base.'/classify', [
                    'title' => $input['title'] ?? '',
                    'location' => $input['location'] ?? '',
                    'fiveW1H' => $input['fiveW1H'] ?? [],
                    'evidenceCount' => (int) ($input['evidenceCount'] ?? 0),
                ]);
        } catch (Throwable $e) {
            logger()->warning('ai-service classify failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            logger()->warning('ai-service classify HTTP '.$response->status());

            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['riskCategory'], $data['likelihood'], $data['impact'])) {
            logger()->warning('ai-service classify returned unexpected payload');

            return null;
        }

        if (! isset($data['source'])) {
            $data['source'] = 'ai-service';
        }

        return $data;
    }

    /**
     * @param  array{title?: string, location?: string, fiveW1H?: array<string, string>, evidenceCount?: int}  $input
     * @return array{summary: string, confidence?: float, source?: string}|null
     */
    public function summarizeRemote(array $input): ?array
    {
        $base = rtrim((string) config('rms.ai_service_url', ''), '/');
        if ($base === '') {
            return null;
        }

        $timeout = max(1, (int) config('rms.ai_service_timeout', 3));

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->connectTimeout(min(2, $timeout))
                ->post($base.'/summarize', [
                    'title' => $input['title'] ?? '',
                    'location' => $input['location'] ?? '',
                    'fiveW1H' => $input['fiveW1H'] ?? [],
                    'evidenceCount' => (int) ($input['evidenceCount'] ?? 0),
                ]);
        } catch (Throwable $e) {
            logger()->warning('ai-service summarize failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['summary'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return list<AiAnalysisResult>
     */
    public function listForTicket(string $reference, int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        return AiAnalysisResult::query()
            ->where('ticket_reference', $reference)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Phase 11 slice 6: re-run classify, persist history, refresh live ticket.ai (workflow unchanged).
     *
     * @return array<string, mixed>
     */
    public function reclassifyTicket(RiskTicket $ticket, User $actor): array
    {
        if ($ticket->deleted) {
            abort(404, 'Ticket not found.');
        }

        $five = is_array($ticket->five_w1h) ? $ticket->five_w1h : [];
        $ai = $this->analyze([
            'title' => (string) $ticket->title,
            'location' => (string) $ticket->location,
            'fiveW1H' => $five,
            'evidenceCount' => (int) $ticket->evidence_count,
        ], (string) $ticket->reference);

        $now = now();
        $audit = is_array($ticket->audit_trail) ? $ticket->audit_trail : [];
        $audit[] = [
            'id' => 'aud-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3)),
            'at' => $now->toIso8601String(),
            'action' => 'AI reclassified ticket',
            'detail' => sprintf(
                '%s · %s · %d%% confidence',
                $ai['riskCategory'] ?? 'operational',
                is_array($ai['riskLevel'] ?? null) ? ($ai['riskLevel']['label'] ?? 'Risk') : 'Risk',
                (int) round(((float) ($ai['confidence'] ?? 0.7)) * 100),
            ),
            'actorUsername' => $actor->username,
            'actorName' => $actor->name ?: $actor->username,
            'actorRole' => $actor->role ?: 'admin',
        ];

        $likelihood = (int) ($ai['likelihood'] ?? $ticket->likelihood ?? 0);
        $impact = (int) ($ai['impact'] ?? $ticket->impact ?? 0);
        $ticket->fill([
            'category' => (string) ($ai['riskCategory'] ?? $ticket->category),
            'likelihood' => $likelihood,
            'impact' => $impact,
            'risk_score' => $likelihood * $impact,
            'ai' => $ai,
            'audit_trail' => $audit,
            'source_updated_at' => $now,
        ]);
        $ticket->save();

        return $ai;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $result
     */
    private function persist(array $input, array $result, ?string $ticketReference): void
    {
        try {
            AiAnalysisResult::query()->create([
                'ticket_reference' => $ticketReference !== null && $ticketReference !== ''
                    ? $ticketReference
                    : null,
                'source' => (string) ($result['source'] ?? 'unknown'),
                'risk_category' => isset($result['riskCategory']) ? (string) $result['riskCategory'] : null,
                'likelihood' => isset($result['likelihood']) ? (int) $result['likelihood'] : null,
                'impact' => isset($result['impact']) ? (int) $result['impact'] : null,
                'severity' => isset($result['severity']) ? (int) $result['severity'] : null,
                'confidence' => isset($result['confidence']) ? (float) $result['confidence'] : null,
                'responsible_department' => isset($result['responsibleDepartment'])
                    ? (string) $result['responsibleDepartment']
                    : null,
                'priority' => isset($result['priority']) ? (string) $result['priority'] : null,
                'input' => [
                    'title' => $input['title'] ?? '',
                    'location' => $input['location'] ?? '',
                    'fiveW1H' => $input['fiveW1H'] ?? [],
                    'evidenceCount' => (int) ($input['evidenceCount'] ?? 0),
                ],
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            logger()->warning('ai_analysis_results persist failed: '.$e->getMessage());
        }
    }
}
