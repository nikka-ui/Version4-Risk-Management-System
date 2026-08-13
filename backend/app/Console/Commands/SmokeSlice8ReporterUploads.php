<?php

namespace App\Console\Commands;

use App\Http\Controllers\SupervisorTicketFormController;
use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 8 slice 1: smoke Ticket Reporter create/edit multipart uploads.
 */
class SmokeSlice8ReporterUploads extends Command
{
    protected $signature = 'rms:smoke-slice8-reporter-uploads';

    protected $description = 'Smoke Laravel reporter create/edit multipart POSTs';

    public function handle(SupervisorTicketFormController $forms): int
    {
        Storage::fake('evidence');

        $reporter = User::query()->create([
            'username' => 'smoke_rupl_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Reporter Uploads',
            'email' => 'smoke_rupl_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeRupl1!',
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
            'position' => 'Ticket Reporter',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ref = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));

        Auth::login($reporter);
        try {
            $missing = $forms->storePreview($this->postRequest('/supervisor/tickets/new/preview', [
                'referenceOverride' => $ref,
                'title' => 'Smoke upload '.$ref,
                'location' => 'HQ',
                'what' => 'Smoke what',
                'why' => 'Smoke why',
                'where' => 'Smoke where',
                'when' => 'Smoke when',
                'who' => 'Smoke who',
                'how' => 'Smoke how',
            ]));
            if (! str_contains($missing->getTargetUrl(), 'error=') || RiskTicket::query()->where('reference', $ref)->exists()) {
                $this->error('reporter create without evidence did not fail');

                return self::FAILURE;
            }
            $this->info('reporter create without evidence rejected OK');

            $created = $forms->storePreview($this->postRequest(
                '/supervisor/tickets/new/preview',
                [
                    'referenceOverride' => $ref,
                    'title' => 'Smoke upload '.$ref,
                    'location' => 'HQ',
                    'what' => 'Smoke what',
                    'why' => 'Smoke why',
                    'where' => 'Smoke where',
                    'when' => 'Smoke when',
                    'who' => 'Smoke who',
                    'how' => 'Smoke how',
                ],
                [UploadedFile::fake()->create('smoke.pdf', 12, 'application/pdf')],
            ));
            $ticket = RiskTicket::query()->where('reference', $ref)->first();
            if (
                ! $ticket
                || $ticket->status !== 'draft'
                || (int) $ticket->evidence_count < 1
                || ! str_contains($created->getTargetUrl(), 'flash=preview_generated')
            ) {
                $this->error('reporter create upload did not persist');

                return self::FAILURE;
            }
            $this->info('reporter create upload OK');

            $updated = $forms->updateEdit($this->postRequest(
                '/supervisor/tickets/'.$ref.'/edit',
                [
                    'title' => 'Smoke upload revised '.$ref,
                    'location' => 'HQ',
                    'what' => 'Smoke what revised',
                    'why' => 'Smoke why',
                    'where' => 'Smoke where',
                    'when' => 'Smoke when',
                    'who' => 'Smoke who',
                    'how' => 'Smoke how',
                ],
                [UploadedFile::fake()->create('smoke-edit.png', 8, 'image/png')],
            ), $ref);
            $ticket->refresh();
            if (
                $ticket->title !== 'Smoke upload revised '.$ref
                || (int) $ticket->evidence_count < 2
                || ! str_contains($updated->getTargetUrl(), 'flash=evidence_uploaded')
            ) {
                $this->error('reporter edit upload did not persist');

                return self::FAILURE;
            }
            $this->info('reporter edit upload OK');
        } finally {
            Auth::logout();
            RiskAttachment::query()->where('ticket_ref', $ref)->delete();
            RiskTicket::query()->where('reference', $ref)->delete();
            $reporter->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<UploadedFile>  $files
     */
    private function postRequest(string $uri, array $input = [], array $files = []): Request
    {
        $request = Request::create($uri, 'POST', $input, [], $files !== [] ? ['attachments' => $files] : []);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }
}
