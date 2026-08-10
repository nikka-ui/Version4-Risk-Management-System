<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\ReportLogService;
use App\Support\Roles;
use Illuminate\Console\Command;

/**
 * Smoke-test notification + report-log mirror APIs (Postgres only; no Express writes).
 */
class SmokeSlice9Notifications extends Command
{
    protected $signature = 'rms:smoke-slice9 {--reporter=admin}';

    protected $description = 'Smoke notification/report-log mirror in Laravel (does not touch Express store.json)';

    public function handle(NotificationService $notifications, ReportLogService $reportLogs): int
    {
        $reporterName = (string) $this->option('reporter');
        $reporter = User::query()->where('username', $reporterName)->first();
        if (! $reporter) {
            $this->error("Reporter not found: {$reporterName}");

            return self::FAILURE;
        }

        $direct = $notifications->create([
            'recipientUsername' => $reporter->username,
            'type' => 'smoke',
            'title' => 'Slice9 direct notification',
            'message' => 'Hello from smoke test.',
            'ticketRef' => 'RISK-SMOKE-9',
        ]);
        $this->info("created notification {$direct->id}");

        $roleNote = $notifications->create([
            'recipientRole' => Roles::RM_OFFICER,
            'type' => 'smoke_role',
            'title' => 'Slice9 role notification',
            'message' => 'Role-wide test.',
        ]);
        $this->info("created role notification {$roleNote->id}");

        $listed = $notifications->listForUser($reporter, 50);
        $this->info('listed for reporter count='.count($listed));
        $this->info('unread='.$notifications->unreadCount($reporter));

        $notifications->markRead($reporter, $direct->id);
        $updated = $notifications->markAllRead($reporter);
        $this->info("markAllRead updated={$updated}");

        $log = $reportLogs->append([
            'ticketRef' => 'RISK-SMOKE-9',
            'title' => 'Slice9 smoke ticket',
            'submittedBy' => $reporter->username,
            'submitterRole' => $reporter->role,
            'status' => 'Submitted',
            'action' => 'smoke',
        ]);
        $this->info("appended report log {$log->id}");
        $this->info('report logs count='.count($reportLogs->list(50)));

        \App\Models\Notification::query()->whereIn('id', [$direct->id, $roleNote->id])->delete();
        \App\Models\ReportLog::query()->where('id', $log->id)->delete();
        $this->info('cleaned up smoke rows');
        $this->line('Express store.json was not modified. USE_LARAVEL_API defaults ON (Phase 5); Express UI remains browser entry.');

        return self::SUCCESS;
    }
}
