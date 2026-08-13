<?php

namespace App\Console\Commands;

use App\Http\Controllers\DeptTicketDetailController;
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
 * Phase 8 slice 3: smoke Department Head document multipart POSTs.
 */
class SmokeSlice8DeptDocuments extends Command
{
    protected $signature = 'rms:smoke-slice8-dept-documents';

    protected $description = 'Smoke Laravel dept document upload POSTs';

    public function handle(DeptTicketDetailController $controller): int
    {
        Storage::fake('evidence');

        $head = User::query()->create([
            'username' => 'smoke_ddoc_'.bin2hex(random_bytes(2)),
            'name' => 'Smoke Dept Documents',
            'email' => 'smoke_ddoc_'.bin2hex(random_bytes(2)).'@rms.local',
            'password' => 'SmokeDdoc1!',
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
            'position' => 'Department Head',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ref = 'RISK-SMOKE-'.strtoupper(bin2hex(random_bytes(3)));
        RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke dept docs '.$ref,
            'description' => 'Smoke',
            'location' => 'HQ',
            'status' => 'in_progress',
            'submitted_by' => 'reporter.smoke',
            'department' => 'Information Technology',
            'evidence_count' => 0,
            'deleted' => false,
            'ownership' => [
                'state' => 'accepted',
                'ownerUsername' => $head->username,
                'ownerName' => $head->name,
                'ownerDepartment' => 'Information Technology',
            ],
        ]);

        Auth::login($head);
        try {
            $uploaded = $controller->uploadDocuments($this->postRequest(
                '/dept/tickets/'.$ref.'/documents',
                [UploadedFile::fake()->create('dept-doc.pdf', 10, 'application/pdf')],
            ), $ref);
            $ticket = RiskTicket::query()->where('reference', $ref)->first();
            if (
                ! $ticket
                || (int) $ticket->evidence_count < 1
                || ! str_contains($uploaded->getTargetUrl(), 'flash=documents_uploaded_dept')
            ) {
                $this->error('dept documents upload did not persist');

                return self::FAILURE;
            }
            $this->info('dept documents upload OK');
        } finally {
            Auth::logout();
            RiskAttachment::query()->where('ticket_ref', $ref)->delete();
            RiskTicket::query()->where('reference', $ref)->delete();
            $head->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function postRequest(string $uri, array $files = []): Request
    {
        $request = Request::create($uri, 'POST', [], [], $files !== [] ? ['attachments' => $files] : []);
        $request->setUserResolver(fn () => Auth::user());

        return $request;
    }
}
