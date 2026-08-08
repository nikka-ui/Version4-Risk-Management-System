<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 3 slice 10: object-storage (MinIO/S3) access for evidence bytes.
 *
 * Key scheme is byte-for-byte compatible with Express (docker/web/lib/attachments.js):
 *   {safeTicketRef}/{att-<ms>-<rand>}-{safeOriginalName}
 * so objects written by Express are readable by Laravel and vice versa
 * (shared bucket `rms-uploads`).
 */
class ObjectStorageService
{
    public function disk(): Filesystem
    {
        return Storage::disk('evidence');
    }

    /** Sanitize a ticket reference for use as a key prefix (mirrors Express safeTicketRef). */
    public function safeTicketRef(string $ticketRef): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $ticketRef) ?? '';
    }

    /** Sanitize an original filename (mirrors Express sanitizeFilename). */
    public function sanitizeFilename(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name === '' ? 'file' : $name));
        $clean = preg_replace('/[^a-zA-Z0-9._-]/', '_', $base) ?? '';
        $clean = substr($clean, 0, 120);

        return $clean !== '' ? $clean : 'file';
    }

    /** Build a storage key identical to Express: {ref}/{id}-{safeName}. */
    public function buildKey(string $ticketRef, string $attachmentId, string $originalName): string
    {
        return $this->safeTicketRef($ticketRef).'/'.$attachmentId.'-'.$this->sanitizeFilename($originalName);
    }

    public function put(string $key, string $contents, string $contentType = 'application/octet-stream'): void
    {
        $this->disk()->put($key, $contents, [
            'ContentType' => $contentType,
        ]);
    }

    public function exists(string $key): bool
    {
        return $this->disk()->exists($key);
    }

    public function get(string $key): ?string
    {
        return $this->exists($key) ? $this->disk()->get($key) : null;
    }

    /**
     * @return resource|null
     */
    public function readStream(string $key)
    {
        return $this->exists($key) ? $this->disk()->readStream($key) : null;
    }

    public function delete(string $key): void
    {
        if ($key === '' || str_starts_with($key, 'legacy/')) {
            return;
        }
        $this->disk()->delete($key);
    }
}
