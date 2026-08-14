<?php

namespace App\Services;

/**
 * Phase 9 slice 6: apply Laravel org dual-write onto Express store.json.
 */
class StoreJsonOrgMirror
{
    public function __construct(private readonly StoreJsonFile $file) {}

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     * @return array{error?: string}
     */
    public function applyDepartment(string $op, array $record, ?array $audit = null, ?array $notification = null): array
    {
        return $this->file->mutate(function (array $data) use ($op, $record, $audit, $notification): array {
            if ($op === 'upsert') {
                [$result, $data] = $this->upsertDepartment($data, $record);
            } elseif ($op === 'delete') {
                [$result, $data] = $this->deleteDepartment($data, $record);
            } else {
                $result = ['error' => 'Unknown op.'];
            }
            if (empty($result['error'])) {
                $data = $this->withLogs($data, $audit, $notification, null);
            }

            return [$result, $data];
        });
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $audit
     * @return array{error?: string}
     */
    public function applyPosition(string $op, array $record, ?array $audit = null): array
    {
        return $this->file->mutate(function (array $data) use ($op, $record, $audit): array {
            if ($op === 'upsert') {
                [$result, $data] = $this->upsertPosition($data, $record);
            } elseif ($op === 'delete') {
                [$result, $data] = $this->deletePosition($data, $record);
            } else {
                $result = ['error' => 'Unknown op.'];
            }
            if (empty($result['error'])) {
                $data = $this->withLogs($data, $audit, null, null);
            }

            return [$result, $data];
        });
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     * @param  array<string, mixed>|null  $credential
     * @return array{error?: string}
     */
    public function applyUser(string $op, array $record, ?array $audit = null, ?array $notification = null, ?array $credential = null): array
    {
        return $this->file->mutate(function (array $data) use ($op, $record, $audit, $notification, $credential): array {
            if ($op === 'upsert') {
                [$result, $data] = $this->upsertUser($data, $record);
            } elseif ($op === 'delete') {
                [$result, $data] = $this->deleteUser($data, $record);
            } else {
                $result = ['error' => 'Unknown op.'];
            }
            if (empty($result['error'])) {
                $data = $this->withLogs($data, $audit, $notification, $credential);
            }

            return [$result, $data];
        });
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $audit
     * @return array{error?: string}
     */
    public function applySettings(array $settings, ?array $audit = null): array
    {
        return $this->file->mutate(function (array $data) use ($settings, $audit): array {
            if (! isset($data['systemSettings']) || ! is_array($data['systemSettings'])) {
                $data['systemSettings'] = [];
            }
            foreach ($settings as $key => $value) {
                $data['systemSettings'][$key] = $value;
            }
            $data = $this->withLogs($data, $audit, null, null);

            return [[], $data];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $record
     * @return array{0: array{error?: string}, 1: array<string, mixed>}
     */
    private function upsertDepartment(array $data, array $record): array
    {
        $id = trim((string) ($record['id'] ?? ''));
        $name = trim((string) ($record['name'] ?? ''));
        $code = strtoupper(trim((string) ($record['code'] ?? '')));
        if ($id === '' || $name === '' || $code === '') {
            return [['error' => 'Department id, name, and code are required.'], $data];
        }
        if (! isset($data['departments']) || ! is_array($data['departments'])) {
            $data['departments'] = [];
        }
        $idx = $this->indexById($data['departments'], $id);
        $now = now()->toIso8601String();
        if ($idx === null) {
            $data['departments'][] = [
                'id' => $id,
                'createdAt' => $record['createdAt'] ?? $now,
                'autoApproveLowModerate' => false,
            ];
            $idx = count($data['departments']) - 1;
        }
        $status = ($record['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $data['departments'][$idx]['name'] = $name;
        $data['departments'][$idx]['code'] = $code;
        $data['departments'][$idx]['description'] = trim((string) ($record['description'] ?? ''));
        $data['departments'][$idx]['head'] = ! empty($record['head']) ? trim((string) $record['head']) : null;
        $data['departments'][$idx]['status'] = $status;
        $data['departments'][$idx]['active'] = ($record['active'] ?? true) !== false && $status !== 'inactive';
        $data['departments'][$idx]['autoApproveLowModerate'] = (bool) ($record['autoApproveLowModerate'] ?? false);
        $data['departments'][$idx]['updatedAt'] = $record['updatedAt'] ?? $now;

        return [[], $data];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $record
     * @return array{0: array{error?: string}, 1: array<string, mixed>}
     */
    private function deleteDepartment(array $data, array $record): array
    {
        $id = trim((string) ($record['id'] ?? ''));
        if ($id === '' || ! isset($data['departments']) || ! is_array($data['departments'])) {
            return [['error' => 'Department not found.'], $data];
        }
        $idx = $this->indexById($data['departments'], $id);
        if ($idx === null || ($data['departments'][$idx]['active'] ?? true) === false) {
            return [['error' => 'Department not found.'], $data];
        }
        $data['departments'][$idx]['active'] = false;
        $data['departments'][$idx]['status'] = 'inactive';
        $data['departments'][$idx]['updatedAt'] = now()->toIso8601String();

        return [[], $data];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $record
     * @return array{0: array{error?: string}, 1: array<string, mixed>}
     */
    private function upsertPosition(array $data, array $record): array
    {
        $id = trim((string) ($record['id'] ?? ''));
        $name = trim((string) ($record['name'] ?? ''));
        if ($id === '' || $name === '') {
            return [['error' => 'Position id and name are required.'], $data];
        }
        if (! isset($data['positions']) || ! is_array($data['positions'])) {
            $data['positions'] = [];
        }
        $idx = $this->indexById($data['positions'], $id);
        $now = now()->toIso8601String();
        if ($idx === null) {
            $data['positions'][] = [
                'id' => $id,
                'createdAt' => $record['createdAt'] ?? $now,
                'active' => true,
            ];
            $idx = count($data['positions']) - 1;
        }
        $data['positions'][$idx]['name'] = $name;
        $data['positions'][$idx]['active'] = ($record['active'] ?? true) !== false;
        $data['positions'][$idx]['updatedAt'] = $record['updatedAt'] ?? $now;

        return [[], $data];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $record
     * @return array{0: array{error?: string}, 1: array<string, mixed>}
     */
    private function deletePosition(array $data, array $record): array
    {
        $id = trim((string) ($record['id'] ?? ''));
        if ($id === '' || ! isset($data['positions']) || ! is_array($data['positions'])) {
            return [['error' => 'Position not found.'], $data];
        }
        $idx = $this->indexById($data['positions'], $id);
        if ($idx === null || ($data['positions'][$idx]['active'] ?? true) === false) {
            return [['error' => 'Position not found.'], $data];
        }
        $data['positions'][$idx]['active'] = false;
        $data['positions'][$idx]['updatedAt'] = now()->toIso8601String();

        return [[], $data];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $record
     * @return array{0: array{error?: string}, 1: array<string, mixed>}
     */
    private function upsertUser(array $data, array $record): array
    {
        $username = strtolower(trim((string) ($record['username'] ?? '')));
        if ($username === '') {
            return [['error' => 'Username is required.'], $data];
        }
        if (! isset($data['users']) || ! is_array($data['users'])) {
            $data['users'] = [];
        }
        $idx = null;
        foreach ($data['users'] as $i => $user) {
            if (is_array($user) && strtolower((string) ($user['username'] ?? '')) === $username) {
                $idx = (int) $i;
                break;
            }
        }
        $now = now()->toIso8601String();
        if ($idx === null) {
            $data['users'][] = [
                'username' => $username,
                'createdAt' => $record['createdAt'] ?? $now,
                'builtIn' => (bool) ($record['builtIn'] ?? false),
            ];
            $idx = count($data['users']) - 1;
        }
        $user = $data['users'][$idx];
        if (isset($record['password']) && $record['password'] !== '') {
            $user['password'] = (string) $record['password'];
        }
        if (array_key_exists('displayName', $record)) {
            $user['displayName'] = trim((string) ($record['displayName'] ?: $username));
        }
        if (array_key_exists('email', $record)) {
            $user['email'] = strtolower(trim((string) ($record['email'] ?: $username.'@rms.local')));
        }
        if (array_key_exists('employeeId', $record)) {
            $user['employeeId'] = trim((string) ($record['employeeId'] ?? ''));
        }
        if (array_key_exists('department', $record)) {
            $user['department'] = trim((string) ($record['department'] ?? ''));
        }
        if (array_key_exists('position', $record)) {
            $user['position'] = trim((string) ($record['position'] ?? ''));
        }
        if (! empty($record['role'])) {
            $user['role'] = (string) $record['role'];
            $user['roleLabel'] = (string) ($record['roleLabel'] ?? $record['role']);
            $user['canManageUsers'] = (bool) ($record['canManageUsers'] ?? false) || $record['role'] === 'admin';
        } elseif (array_key_exists('roleLabel', $record)) {
            $user['roleLabel'] = (string) $record['roleLabel'];
        }
        if (array_key_exists('canManageUsers', $record)) {
            $user['canManageUsers'] = (bool) $record['canManageUsers'];
        }
        if (array_key_exists('builtIn', $record)) {
            $user['builtIn'] = (bool) $record['builtIn'];
        }
        if (($record['deleted'] ?? null) === true) {
            $user['deleted'] = true;
            $user['deletedAt'] = $now;
            $user['active'] = false;
            $user['status'] = 'deleted';
        } else {
            if (array_key_exists('active', $record)) {
                $user['active'] = (bool) $record['active'];
            }
            if (array_key_exists('status', $record)) {
                $user['status'] = (string) $record['status'];
            }
            if (($record['deleted'] ?? null) === false) {
                $user['deleted'] = false;
                $user['deletedAt'] = null;
            }
        }
        $user['updatedAt'] = $record['updatedAt'] ?? $now;
        $data['users'][$idx] = $user;

        return [[], $data];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $record
     * @return array{0: array{error?: string}, 1: array<string, mixed>}
     */
    private function deleteUser(array $data, array $record): array
    {
        $username = strtolower(trim((string) ($record['username'] ?? '')));
        if ($username === '' || ! isset($data['users']) || ! is_array($data['users'])) {
            return [['error' => 'User not found.'], $data];
        }
        foreach ($data['users'] as $i => $user) {
            if (! is_array($user) || strtolower((string) ($user['username'] ?? '')) !== $username) {
                continue;
            }
            if (! empty($user['deleted'])) {
                return [['error' => 'User not found.'], $data];
            }
            if (! empty($user['builtIn'])) {
                return [['error' => 'Built-in accounts cannot be deleted.'], $data];
            }
            if (($user['username'] ?? '') === 'admin') {
                return [['error' => 'The administrator account cannot be deleted.'], $data];
            }
            $now = now()->toIso8601String();
            $user['deleted'] = true;
            $user['deletedAt'] = $now;
            $user['active'] = false;
            $user['status'] = 'deleted';
            $user['updatedAt'] = $now;
            $data['users'][$i] = $user;

            return [[], $data];
        }

        return [['error' => 'User not found.'], $data];
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function indexById(array $rows, string $id): ?int
    {
        foreach ($rows as $i => $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $id) {
                return (int) $i;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     * @param  array<string, mixed>|null  $credential
     * @return array<string, mixed>
     */
    private function withLogs(array $data, ?array $audit, ?array $notification, ?array $credential): array
    {
        $now = now()->toIso8601String();
        if (is_array($audit)) {
            if (! isset($data['auditLogs']) || ! is_array($data['auditLogs'])) {
                $data['auditLogs'] = [];
            }
            $data['auditLogs'][] = array_merge([
                'id' => 'alog-'.now()->getTimestampMs(),
                'at' => $now,
            ], $audit);
            if (count($data['auditLogs']) > 1000) {
                $data['auditLogs'] = array_slice($data['auditLogs'], -1000);
            }
        }
        if (is_array($notification)) {
            if (! isset($data['notifications']) || ! is_array($data['notifications'])) {
                $data['notifications'] = [];
            }
            array_unshift($data['notifications'], array_merge([
                'id' => 'n-'.now()->getTimestampMs(),
                'at' => $now,
                'read' => false,
            ], $notification));
        }
        if (is_array($credential)) {
            if (! isset($data['credentialLogs']) || ! is_array($data['credentialLogs'])) {
                $data['credentialLogs'] = [];
            }
            $data['credentialLogs'][] = array_merge([
                'id' => 'clog-'.now()->getTimestampMs(),
                'at' => $now,
            ], $credential);
            if (count($data['credentialLogs']) > 500) {
                $data['credentialLogs'] = array_slice($data['credentialLogs'], -500);
            }
        }

        return $data;
    }
}
