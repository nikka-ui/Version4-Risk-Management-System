<?php

namespace App\Services;

use App\Models\RiskAttachment;
use App\Models\RiskTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Phase 3 slice 8: attachment metadata over shared risk_attachments.
 * Phase 3 slice 10: optional file-byte ownership via ObjectStorageService
 * (shared MinIO bucket + key scheme with Express). Express remains the live path.
 */
class AttachmentService
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

    private const MAX_FILES_PER_TICKET = 10;

    /** @var list<string> */
    private const ALLOWED_EXT = ['pdf', 'png', 'jpg', 'jpeg'];

    /** @var list<string> */
    private const ALLOWED_MIME = ['application/pdf', 'image/png', 'image/jpeg'];

    public function __construct(
        private readonly ObjectStorageService $storage,
    ) {}
    /**
     * @return list<RiskAttachment>
     */
    public function listForTicket(string $reference): array
    {
        return RiskAttachment::query()
            ->where('ticket_ref', $reference)
            ->orderBy('uploaded_at')
            ->get()
            ->all();
    }

    public function findById(string $id): ?RiskAttachment
    {
        return RiskAttachment::query()->where('id', $id)->first();
    }

    /**
     * Idempotent register/upsert of attachment metadata (no file bytes).
     */
    public function register(string $reference, array $input = []): RiskAttachment
    {
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            $id = 'att-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3));
        }

        $originalName = trim((string) ($input['originalName'] ?? $input['name'] ?? ''));
        if ($originalName === '') {
            throw ValidationException::withMessages([
                'originalName' => ['Attachment originalName is required.'],
            ]);
        }

        $storageKey = trim((string) ($input['storageKey'] ?? ''));
        if ($storageKey === '') {
            $storageKey = "laravel/{$reference}/{$id}-".preg_replace('/[^a-zA-Z0-9._-]+/', '_', $originalName);
        }

        $mimeType = trim((string) ($input['mimeType'] ?? 'application/octet-stream')) ?: 'application/octet-stream';
        $size = (int) ($input['size'] ?? $input['sizeBytes'] ?? 0);
        $uploadedBy = trim((string) ($input['uploadedBy'] ?? '')) ?: null;
        $legacy = in_array($input['legacy'] ?? false, [true, 1, '1', 'true'], true);

        $uploadedAt = now();
        if (! empty($input['uploadedAt'])) {
            try {
                $uploadedAt = Carbon::parse((string) $input['uploadedAt']);
            } catch (\Throwable) {
                $uploadedAt = now();
            }
        }

        $attachment = RiskAttachment::query()->updateOrCreate(
            ['id' => $id],
            [
                'ticket_ref' => $reference,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size_bytes' => $size,
                'storage_key' => $storageKey,
                'uploaded_by' => $uploadedBy,
                'legacy' => $legacy,
                'uploaded_at' => $uploadedAt,
            ],
        );

        $this->syncEvidenceCount($reference);

        return $attachment->fresh();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<RiskAttachment>
     */
    public function registerMany(string $reference, array $items): array
    {
        $saved = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $saved[] = $this->register($reference, $item);
        }
        if ($saved === []) {
            $this->syncEvidenceCount($reference);
        }

        return $saved;
    }

    public function syncEvidenceCount(string $reference): int
    {
        $count = RiskAttachment::query()->where('ticket_ref', $reference)->count();

        $ticket = RiskTicket::query()->where('reference', $reference)->first();
        if ($ticket) {
            $ticket->evidence_count = $count;
            $ticket->source_updated_at = now();
            $ticket->save();
        }

        return $count;
    }

    public function deleteMetadata(string $id): bool
    {
        $attachment = $this->findById($id);
        if (! $attachment) {
            return false;
        }

        $ref = $attachment->ticket_ref;
        $attachment->delete();
        $this->syncEvidenceCount($ref);

        return true;
    }

    /**
     * Store raw file bytes to object storage AND register metadata.
     * Mirrors Express validation (pdf/png/jpg/jpeg, 20MB).
     */
    public function storeRawFile(
        string $reference,
        string $originalName,
        string $mimeType,
        string $contents,
        ?string $uploadedBy = null,
    ): RiskAttachment {
        $this->assertValid($originalName, $mimeType, strlen($contents));

        $id = 'att-'.(int) round(microtime(true) * 1000).'-'.bin2hex(random_bytes(3));
        $key = $this->storage->buildKey($reference, $id, $originalName);

        $this->storage->put($key, $contents, $mimeType ?: 'application/octet-stream');

        return $this->register($reference, [
            'id' => $id,
            'originalName' => $originalName,
            'mimeType' => $mimeType ?: 'application/octet-stream',
            'size' => strlen($contents),
            'storageKey' => $key,
            'uploadedBy' => $uploadedBy,
            'legacy' => false,
        ]);
    }

    public function storeUploadedFile(string $reference, UploadedFile $file, ?string $uploadedBy = null): RiskAttachment
    {
        return $this->storeRawFile(
            $reference,
            (string) $file->getClientOriginalName(),
            (string) ($file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream'),
            (string) file_get_contents($file->getRealPath()),
            $uploadedBy,
        );
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<RiskAttachment>
     */
    public function storeUploadedFiles(string $reference, array $files, ?string $uploadedBy = null): array
    {
        $saved = [];
        foreach (array_slice($files, 0, self::MAX_FILES_PER_TICKET) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $saved[] = $this->storeUploadedFile($reference, $file, $uploadedBy);
        }

        return $saved;
    }

    /**
     * @return resource|null
     */
    public function openReadStream(RiskAttachment $attachment)
    {
        if ($attachment->legacy && str_starts_with((string) $attachment->storage_key, 'legacy/')) {
            return null;
        }

        return $this->storage->readStream((string) $attachment->storage_key);
    }

    private function assertValid(string $originalName, string $mimeType, int $size): void
    {
        if ($size > self::MAX_FILE_BYTES) {
            throw ValidationException::withMessages([
                'file' => ["File exceeds 20MB: {$originalName}"],
            ]);
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            throw ValidationException::withMessages([
                'file' => ['Unsupported file type: '.($ext !== '' ? $ext : 'unknown')],
            ]);
        }

        if ($mimeType !== '' && ! in_array($mimeType, self::ALLOWED_MIME, true)) {
            throw ValidationException::withMessages([
                'file' => ["Unsupported MIME type: {$mimeType}"],
            ]);
        }
    }
}
