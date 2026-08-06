<?php

namespace App\Console\Commands;

use App\Models\Accomplishment;
use App\Models\RiskTicket;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Idempotent import of Express store.json riskTickets + accomplishments.
 * Does not modify store.json; Express remains source of truth for live workflow.
 */
class ImportTicketsFromStore extends Command
{
    protected $signature = 'rms:import-tickets
                            {--path= : Path to store.json (default: STORE_JSON_PATH / config)}
                            {--dry-run : Parse and report without writing}';

    protected $description = 'Import risk tickets and accomplishments from Express store.json (idempotent upsert)';

    /** Known top-level keys mapped to columns / JSON columns — remaining go to payload. */
    private const MAPPED_KEYS = [
        'id', 'reference', 'title', 'description', 'location', 'status', 'category',
        'priority', 'department', 'reporterDepartment', 'likelihood', 'impact', 'riskScore',
        'submittedBy', 'submittedByName', 'mitigationApproach', 'evidenceCount',
        'accomplishmentId', 'createdAt', 'updatedAt', 'submittedAt', 'routedAt',
        'mitigationDueAt', 'deleted', 'deletedAt', 'deletedBy', 'deletedByName',
        'deletionReason', 'fiveW1H', 'ai', 'ownership', 'actionPlan', 'personnel',
        'progressUpdates', 'reassignments', 'auditTrail', 'threadComments',
        'privateComments', 'executiveComments', 'mitigationPlanHistory', 'reopenHistory',
        'presidentPlanDecision', 'presidentFinalDecision', 'presidentDecision',
        'closure', 'finalResolution', 'rmuRecommendations', 'escalations',
        // Evidence bytes live in MinIO + risk_attachments; never persist blob on ticket.
        'evidence',
    ];

    public function handle(): int
    {
        $path = $this->option('path') ?: config('rms.store_json_path');
        $path = (string) $path;

        if ($path === '' || ! is_readable($path)) {
            $this->error("store.json not readable at: {$path}");

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error("Failed to read: {$path}");

            return self::FAILURE;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->error('Invalid store.json.');

            return self::FAILURE;
        }

        $tickets = is_array($data['riskTickets'] ?? null) ? $data['riskTickets'] : [];
        $accomplishments = is_array($data['accomplishments'] ?? null) ? $data['accomplishments'] : [];
        $dryRun = (bool) $this->option('dry-run');

        $ticketStats = $this->importTickets($tickets, $dryRun);
        $accStats = $this->importAccomplishments($accomplishments, $dryRun);

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Tickets: created={$ticketStats['created']} updated={$ticketStats['updated']} skipped={$ticketStats['skipped']}");
        $this->info("{$prefix}Accomplishments: created={$accStats['created']} updated={$accStats['updated']} skipped={$accStats['skipped']}");
        $this->line('Express store.json was not modified. Ticket UI/workflow remains on Express until USE_LARAVEL_API is enabled.');

        return self::SUCCESS;
    }

    /**
     * @param  list<mixed>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importTickets(array $rows, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $externalId = trim((string) ($row['id'] ?? ''));
            $reference = trim((string) ($row['reference'] ?? ''));
            if ($externalId === '' || $reference === '') {
                $skipped++;
                continue;
            }

            $attributes = $this->mapTicketAttributes($row);

            if ($dryRun) {
                RiskTicket::query()->where('reference', $reference)->exists() ? $updated++ : $created++;
                continue;
            }

            $ticket = RiskTicket::query()->where('reference', $reference)->first()
                ?? RiskTicket::query()->where('external_id', $externalId)->first();

            if ($ticket) {
                $ticket->fill($attributes);
                $ticket->external_id = $externalId;
                $ticket->reference = $reference;
                $ticket->save();
                $updated++;
            } else {
                RiskTicket::query()->create(array_merge($attributes, [
                    'external_id' => $externalId,
                    'reference' => $reference,
                ]));
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapTicketAttributes(array $row): array
    {
        $payload = [];
        foreach ($row as $key => $value) {
            if (! in_array($key, self::MAPPED_KEYS, true)) {
                $payload[$key] = $value;
            }
        }

        return [
            'title' => $this->nullableString($row['title'] ?? null),
            'description' => $this->nullableString($row['description'] ?? null),
            'location' => $this->nullableString($row['location'] ?? null),
            'status' => (string) ($row['status'] ?? 'draft'),
            'category' => $this->nullableString($row['category'] ?? null),
            'priority' => $this->nullableString($row['priority'] ?? null),
            'department' => $this->nullableString($row['department'] ?? null),
            'reporter_department' => $this->nullableString($row['reporterDepartment'] ?? null),
            'likelihood' => $this->nullableInt($row['likelihood'] ?? null),
            'impact' => $this->nullableInt($row['impact'] ?? null),
            'risk_score' => $this->nullableInt($row['riskScore'] ?? null),
            'submitted_by' => $this->nullableString($row['submittedBy'] ?? null),
            'submitted_by_name' => $this->nullableString($row['submittedByName'] ?? null),
            'mitigation_approach' => $this->nullableString($row['mitigationApproach'] ?? null),
            'evidence_count' => (int) ($row['evidenceCount'] ?? (is_array($row['evidence'] ?? null) ? count($row['evidence']) : 0)),
            'accomplishment_external_id' => $this->nullableString($row['accomplishmentId'] ?? null),
            'source_created_at' => $this->parseTimestamp($row['createdAt'] ?? null),
            'source_updated_at' => $this->parseTimestamp($row['updatedAt'] ?? null),
            'submitted_at' => $this->parseTimestamp($row['submittedAt'] ?? null),
            'routed_at' => $this->parseTimestamp($row['routedAt'] ?? null),
            'mitigation_due_at' => $this->parseTimestamp($row['mitigationDueAt'] ?? null),
            'deleted' => ($row['deleted'] ?? false) === true,
            'deleted_at' => $this->parseTimestamp($row['deletedAt'] ?? null),
            'deleted_by' => $this->nullableString($row['deletedBy'] ?? null),
            'deleted_by_name' => $this->nullableString($row['deletedByName'] ?? null),
            'deletion_reason' => $this->nullableString($row['deletionReason'] ?? null),
            'five_w1h' => $this->asArrayOrNull($row['fiveW1H'] ?? null),
            'ai' => $this->asArrayOrNull($row['ai'] ?? null),
            'ownership' => $this->asArrayOrNull($row['ownership'] ?? null),
            'action_plan' => $this->asArrayOrNull($row['actionPlan'] ?? null),
            'personnel' => $this->asArrayOrNull($row['personnel'] ?? null),
            'progress_updates' => $this->asArrayOrNull($row['progressUpdates'] ?? null),
            'reassignments' => $this->asArrayOrNull($row['reassignments'] ?? null),
            'audit_trail' => $this->asArrayOrNull($row['auditTrail'] ?? null),
            'thread_comments' => $this->asArrayOrNull($row['threadComments'] ?? null),
            'private_comments' => $this->asArrayOrNull($row['privateComments'] ?? null),
            'executive_comments' => $this->asArrayOrNull($row['executiveComments'] ?? null),
            'mitigation_plan_history' => $this->asArrayOrNull($row['mitigationPlanHistory'] ?? null),
            'reopen_history' => $this->asArrayOrNull($row['reopenHistory'] ?? null),
            'president_plan_decision' => $this->asArrayOrNull($row['presidentPlanDecision'] ?? null),
            'president_final_decision' => $this->asArrayOrNull($row['presidentFinalDecision'] ?? null),
            'president_decision' => $this->asArrayOrNull($row['presidentDecision'] ?? null),
            'closure' => $this->asArrayOrNull($row['closure'] ?? null),
            'final_resolution' => $this->asArrayOrNull($row['finalResolution'] ?? null),
            'rmu_recommendations' => $this->asArrayOrNull($row['rmuRecommendations'] ?? null),
            'escalations' => $this->asArrayOrNull($row['escalations'] ?? null),
            'payload' => $payload === [] ? null : $payload,
        ];
    }

    /**
     * @param  list<mixed>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importAccomplishments(array $rows, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $externalId = trim((string) ($row['id'] ?? ''));
            $ticketRef = trim((string) ($row['ticketRef'] ?? ''));
            if ($externalId === '' || $ticketRef === '') {
                $skipped++;
                continue;
            }

            $known = ['id', 'ticketRef', 'ticketTitle', 'summary', 'outcomes', 'submittedBy', 'submittedByName', 'submittedAt', 'evidence'];
            $payload = [];
            foreach ($row as $key => $value) {
                if (! in_array($key, $known, true)) {
                    $payload[$key] = $value;
                }
            }

            $attributes = [
                'ticket_ref' => $ticketRef,
                'ticket_title' => $this->nullableString($row['ticketTitle'] ?? null),
                'summary' => $this->nullableString($row['summary'] ?? null),
                'outcomes' => $this->nullableString($row['outcomes'] ?? null),
                'submitted_by' => $this->nullableString($row['submittedBy'] ?? null),
                'submitted_by_name' => $this->nullableString($row['submittedByName'] ?? null),
                'submitted_at' => $this->parseTimestamp($row['submittedAt'] ?? null),
                'evidence' => $this->asArrayOrNull($row['evidence'] ?? null),
                'payload' => $payload === [] ? null : $payload,
            ];

            if ($dryRun) {
                Accomplishment::query()->where('external_id', $externalId)->exists() ? $updated++ : $created++;
                continue;
            }

            $acc = Accomplishment::query()->where('external_id', $externalId)->first();
            if ($acc) {
                $acc->fill($attributes);
                $acc->save();
                $updated++;
            } else {
                Accomplishment::query()->create(array_merge($attributes, [
                    'external_id' => $externalId,
                ]));
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /** @return array<string, mixed>|list<mixed>|null */
    private function asArrayOrNull(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
