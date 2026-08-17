<?php

namespace App\Services;

/**
 * Phase 9 slice 5: apply Laravel ticket dual-write onto Express store.json.
 */
class StoreJsonTicketMirror
{
    public function __construct(private readonly StoreJsonFile $file) {}

    /**
     * @param  array<string, mixed>  $record
     * @return array{error?: string, ticket?: array<string, mixed>}
     */
    public function upsert(array $record): array
    {
        $reference = trim((string) ($record['reference'] ?? ''));
        if ($reference === '') {
            return ['error' => 'Ticket not found.'];
        }

        return $this->file->mutate(function (array $data) use ($record, $reference): array {
            if (! isset($data['riskTickets']) || ! is_array($data['riskTickets'])) {
                $data['riskTickets'] = [];
            }
            $idx = $this->indexOf($data['riskTickets'], $reference);
            if ($idx === null) {
                $data['riskTickets'][] = [
                    'reference' => $reference,
                    'id' => $record['id'] ?? $reference,
                ];
                $idx = count($data['riskTickets']) - 1;
            }
            foreach ($record as $key => $value) {
                if ($key === 'reference' || $value === null) {
                    continue;
                }
                $data['riskTickets'][$idx][$key] = $value;
            }
            $data['riskTickets'][$idx]['updatedAt'] = $record['updatedAt'] ?? now()->toIso8601String();

            return [['ticket' => $data['riskTickets'][$idx]], $data];
        });
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     * @return array{error?: string, ticket?: array<string, mixed>}
     */
    public function softDelete(array $record, ?array $audit = null, ?array $notification = null): array
    {
        $reference = trim((string) ($record['reference'] ?? ''));
        if ($reference === '') {
            return ['error' => 'Ticket not found.'];
        }
        $deletionReason = trim((string) ($record['deletionReason'] ?? $record['reason'] ?? ''));
        if ($deletionReason === '') {
            return ['error' => 'A reason for deletion is required.'];
        }

        return $this->file->mutate(function (array $data) use ($record, $reference, $deletionReason, $audit, $notification): array {
            if (! isset($data['riskTickets']) || ! is_array($data['riskTickets'])) {
                return [['error' => 'Ticket not found.'], $data];
            }
            $idx = $this->indexOf($data['riskTickets'], $reference);
            if ($idx === null) {
                return [['error' => 'Ticket not found.'], $data];
            }
            $ticket = $data['riskTickets'][$idx];
            if (! empty($ticket['deleted'])) {
                return [['error' => 'Ticket is already deleted.'], $data];
            }
            $now = now()->toIso8601String();
            $ticket['deleted'] = true;
            $ticket['deletedAt'] = $record['deletedAt'] ?? $now;
            $ticket['deletedBy'] = trim((string) ($record['deletedBy'] ?? ''));
            $ticket['deletedByName'] = (string) ($record['deletedByName'] ?? $ticket['deletedBy']);
            $ticket['deletionReason'] = $deletionReason;
            $ticket['updatedAt'] = $now;
            $data['riskTickets'][$idx] = $ticket;

            if (! isset($data['deletedTicketLogs']) || ! is_array($data['deletedTicketLogs'])) {
                $data['deletedTicketLogs'] = [];
            }
            array_unshift($data['deletedTicketLogs'], [
                'id' => 'dtl-'.now()->getTimestampMs(),
                'at' => $now,
                'ticketRef' => $ticket['reference'] ?? $reference,
                'title' => $ticket['title'] ?? '',
                'deletedBy' => $ticket['deletedBy'],
                'reason' => $deletionReason,
            ]);
            $data['deletedTicketLogs'] = array_slice($data['deletedTicketLogs'], 0, 200);

            if (is_array($audit)) {
                $data = $this->pushAudit($data, $audit);
            }
            if (is_array($notification)) {
                $data = $this->pushNotification($data, $notification);
            }

            return [['ticket' => $ticket], $data];
        });
    }

    /**
     * @return array{error?: string, reference: string}
     */
    public function deleteDraft(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return ['error' => 'Ticket not found.', 'reference' => ''];
        }

        return $this->file->mutate(function (array $data) use ($reference): array {
            if (! isset($data['riskTickets']) || ! is_array($data['riskTickets'])) {
                return [['reference' => $reference], $data];
            }
            $idx = $this->indexOf($data['riskTickets'], $reference);
            if ($idx === null) {
                return [['reference' => $reference], $data];
            }
            $ticket = $data['riskTickets'][$idx];
            $status = (string) ($ticket['status'] ?? '');
            if ($status !== '' && $status !== 'draft') {
                return [['error' => 'Only draft tickets can be deleted.', 'reference' => $reference], $data];
            }
            array_splice($data['riskTickets'], $idx, 1);

            return [['reference' => (string) ($ticket['reference'] ?? $reference)], $data];
        });
    }

    /**
     * @param  array<int, mixed>  $tickets
     */
    private function indexOf(array $tickets, string $reference): ?int
    {
        foreach ($tickets as $i => $ticket) {
            if (is_array($ticket) && ($ticket['reference'] ?? '') === $reference) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $audit
     * @return array<string, mixed>
     */
    private function pushAudit(array $data, array $audit): array
    {
        $entry = array_merge([
            'id' => 'alog-'.now()->getTimestampMs(),
            'at' => now()->toIso8601String(),
        ], $audit);
        try {
            app(AuditLogService::class)->record($entry);
        } catch (\Throwable $e) {
            logger()->warning('audit_logs write failed: '.$e->getMessage());
        }
        if (! isset($data['auditLogs']) || ! is_array($data['auditLogs'])) {
            $data['auditLogs'] = [];
        }
        $data['auditLogs'][] = $entry;
        if (count($data['auditLogs']) > 1000) {
            $data['auditLogs'] = array_slice($data['auditLogs'], -1000);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $notification
     * @return array<string, mixed>
     */
    private function pushNotification(array $data, array $notification): array
    {
        if (! isset($data['notifications']) || ! is_array($data['notifications'])) {
            $data['notifications'] = [];
        }
        array_unshift($data['notifications'], array_merge([
            'id' => 'n-'.now()->getTimestampMs(),
            'at' => now()->toIso8601String(),
            'read' => false,
        ], $notification));

        return $data;
    }
}
