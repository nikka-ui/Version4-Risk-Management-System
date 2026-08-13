<?php

namespace App\Services;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\DraftAiAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 2: draft CRUD against Postgres only.
 * Express/store.json remains the live browser workflow until USE_LARAVEL_API is enabled.
 * File bytes / MinIO stay on Express — this service accepts evidence metadata/count only.
 */
class DraftTicketService
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function create(User $user, array $input): RiskTicket
    {
        $fields = $this->validatedFields($input);
        $evidenceCount = $this->resolveEvidenceCount($input);

        return DB::transaction(function () use ($user, $fields, $evidenceCount, $input) {
            $reference = trim((string) ($input['reference'] ?? ''));
            if ($reference === '') {
                $reference = $this->allocateReference();
            } elseif (RiskTicket::query()->where('reference', $reference)->exists()) {
                throw ValidationException::withMessages([
                    'reference' => ['A ticket with that reference already exists.'],
                ]);
            }

            $ai = DraftAiAnalysis::analyze([
                'title' => $fields['title'],
                'location' => $fields['location'],
                'fiveW1H' => $fields['five_w1h'],
                'evidenceCount' => $evidenceCount,
            ]);

            $now = now();

            return RiskTicket::query()->create([
                'external_id' => 'tkt-'.(int) round(microtime(true) * 1000),
                'reference' => $reference,
                'title' => $fields['title'],
                'description' => $fields['description'],
                'location' => $fields['location'],
                'status' => 'draft',
                'category' => $ai['riskCategory'],
                'priority' => null,
                'department' => null,
                'reporter_department' => $fields['reporter_department']
                    ?? ($user->department ?: null),
                'likelihood' => $ai['likelihood'],
                'impact' => $ai['impact'],
                'risk_score' => $ai['likelihood'] * $ai['impact'],
                'submitted_by' => $user->username,
                'submitted_by_name' => $user->name,
                'mitigation_approach' => $fields['mitigation_approach'],
                'evidence_count' => $evidenceCount,
                'source_created_at' => $now,
                'source_updated_at' => $now,
                'five_w1h' => $fields['five_w1h'],
                'ai' => $ai,
                'ownership' => null,
                'personnel' => [],
                'progress_updates' => [],
                'reassignments' => [],
                'audit_trail' => [],
                'thread_comments' => [],
                'private_comments' => [],
                'executive_comments' => [],
                'deleted' => false,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(RiskTicket $ticket, User $user, array $input): RiskTicket
    {
        $this->assertOwnerDraft($ticket, $user);

        return $this->applyFieldUpdate($ticket, $input);
    }

    /**
     * Phase 8 slice 1: update draft or returned / ownership_rejected reports.
     *
     * @param  array<string, mixed>  $input
     */
    public function updateEditable(RiskTicket $ticket, User $user, array $input): RiskTicket
    {
        $this->assertOwnerEditable($ticket, $user);

        return $this->applyFieldUpdate($ticket, $input);
    }

    public function deleteDraft(RiskTicket $ticket, User $user): string
    {
        $this->assertOwnerDraft($ticket, $user);
        $reference = $ticket->reference;
        $ticket->delete();

        return $reference;
    }

    public function findOwnedDraft(string $reference, User $user): ?RiskTicket
    {
        return RiskTicket::query()
            ->where('reference', $reference)
            ->where('submitted_by', $user->username)
            ->where('deleted', false)
            ->first();
    }

    /** Next RISK-YYYY-##### reference without persisting (Phase 5 slice 13 forms). */
    public function peekNextReference(): string
    {
        return $this->allocateReference();
    }

    private function allocateReference(): string
    {
        $year = (int) now()->year;
        $prefix = "RISK-{$year}-";

        $refs = RiskTicket::query()
            ->where('reference', 'like', $prefix.'%')
            ->pluck('reference');

        $max = 0;
        foreach ($refs as $ref) {
            $seq = (int) substr((string) $ref, strlen($prefix));
            if ($seq > $max) {
                $max = $seq;
            }
        }

        return $prefix.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{title: string, description: string, location: string, mitigation_approach: ?string, reporter_department: ?string, five_w1h: array<string, string>}
     */
    private function validatedFields(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => ['Risk title is required.'],
            ]);
        }

        $fiveW1H = is_array($input['fiveW1H'] ?? null) ? $input['fiveW1H'] : [];
        $five = [
            'what' => trim((string) ($input['what'] ?? $fiveW1H['what'] ?? '')),
            'why' => trim((string) ($input['why'] ?? $fiveW1H['why'] ?? '')),
            'where' => trim((string) ($input['where'] ?? $fiveW1H['where'] ?? '')),
            'when' => trim((string) ($input['when'] ?? $fiveW1H['when'] ?? '')),
            'who' => trim((string) ($input['who'] ?? $fiveW1H['who'] ?? '')),
            'how' => trim((string) ($input['how'] ?? $fiveW1H['how'] ?? '')),
        ];

        foreach ($five as $key => $value) {
            if ($value === '') {
                throw ValidationException::withMessages([
                    $key => ['All Incident Details fields are required (What, Why, Where, When, Who, How).'],
                ]);
            }
        }

        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            $description = implode("\n", array_values($five));
        }

        return [
            'title' => $title,
            'description' => $description,
            'location' => trim((string) ($input['location'] ?? '')),
            'mitigation_approach' => ($m = trim((string) ($input['mitigationApproach'] ?? $input['mitigation_approach'] ?? ''))) !== '' ? $m : null,
            'reporter_department' => ($d = trim((string) ($input['reporterDepartment'] ?? $input['reporter_department'] ?? ''))) !== '' ? $d : null,
            'five_w1h' => $five,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveEvidenceCount(array $input, ?int $fallback = null): int
    {
        if (isset($input['evidenceCount'])) {
            $count = (int) $input['evidenceCount'];
        } elseif (isset($input['evidence']) && is_array($input['evidence'])) {
            $count = count($input['evidence']);
        } elseif ($fallback !== null) {
            $count = $fallback;
        } else {
            $count = 0;
        }

        if ($count < 1) {
            throw ValidationException::withMessages([
                'evidenceCount' => ['At least one evidence file is required.'],
            ]);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyFieldUpdate(RiskTicket $ticket, array $input): RiskTicket
    {
        $fields = $this->validatedFields($input);
        $evidenceCount = $this->resolveEvidenceCount($input, $ticket->evidence_count);

        $ai = DraftAiAnalysis::analyze([
            'title' => $fields['title'],
            'location' => $fields['location'],
            'fiveW1H' => $fields['five_w1h'],
            'evidenceCount' => $evidenceCount,
        ]);

        $ticket->fill([
            'title' => $fields['title'],
            'description' => $fields['description'],
            'location' => $fields['location'],
            'mitigation_approach' => $fields['mitigation_approach'],
            'five_w1h' => $fields['five_w1h'],
            'category' => $ai['riskCategory'],
            'likelihood' => $ai['likelihood'],
            'impact' => $ai['impact'],
            'risk_score' => $ai['likelihood'] * $ai['impact'],
            'ai' => $ai,
            'evidence_count' => $evidenceCount,
            'source_updated_at' => now(),
        ]);
        $ticket->save();

        return $ticket->fresh();
    }

    private function assertOwnerDraft(RiskTicket $ticket, User $user): void
    {
        if ($ticket->deleted || $ticket->submitted_by !== $user->username) {
            abort(404, 'Ticket not found.');
        }

        if ($ticket->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft tickets can be edited or deleted from this API.'],
            ]);
        }
    }

    private function assertOwnerEditable(RiskTicket $ticket, User $user): void
    {
        if ($ticket->deleted || $ticket->submitted_by !== $user->username) {
            abort(404, 'Ticket not found.');
        }

        if ($ticket->status !== 'draft' && ! in_array($ticket->status, ['returned', 'ownership_rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => ['This ticket can no longer be edited.'],
            ]);
        }
    }
}
