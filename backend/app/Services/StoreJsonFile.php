<?php

namespace App\Services;

/**
 * Locked read/write of Express store.json (Phase 9 slice 5 ticket mirror).
 */
class StoreJsonFile
{
    /**
     * @param  callable(array<string, mixed>): array{0: mixed, 1: array<string, mixed>}  $mutator
     */
    public function mutate(callable $mutator): mixed
    {
        $path = (string) config('rms.store_json_path', '');
        if ($path === '' || ! is_file($path)) {
            throw new \RuntimeException('store.json is not available');
        }
        if (! is_writable($path)) {
            throw new \RuntimeException('store.json is not writable');
        }

        $fh = fopen($path, 'c+');
        if ($fh === false) {
            throw new \RuntimeException('store.json could not be opened');
        }

        try {
            if (! flock($fh, LOCK_EX)) {
                throw new \RuntimeException('store.json lock failed');
            }
            rewind($fh);
            $raw = stream_get_contents($fh);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (! is_array($data)) {
                $data = [];
            }
            [$result, $data] = $mutator($data);
            if (! is_array($data)) {
                throw new \RuntimeException('store.json mutator must return the store array');
            }
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new \RuntimeException('store.json encode failed');
            }
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, $encoded."\n");
            fflush($fh);

            return $result;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
