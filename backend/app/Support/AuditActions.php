<?php

namespace App\Support;

/**
 * Admin audit-log action labels — mirrors docker/web/lib/admin.js AUDIT_ACTION_LABELS.
 */
final class AuditActions
{
    /** @var array<string, string> */
    private const LABELS = [
        'user_created' => 'Created User',
        'user_updated' => 'Updated User',
        'user_deleted' => 'Deleted User',
        'user_activated' => 'Activated User',
        'user_deactivated' => 'Deactivated User',
        'password_reset' => 'Reset Password',
        'department_created' => 'Created Department',
        'department_updated' => 'Updated Department',
        'department_deleted' => 'Deleted Department',
        'position_created' => 'Created Position',
        'position_updated' => 'Updated Position',
        'position_deleted' => 'Deleted Position',
        'ticket_deleted' => 'Deleted Ticket',
        'settings_updated' => 'Updated Settings',
        'settings_reset_landing' => 'Reset Landing Page Settings',
        'settings_reset_ai' => 'Reset AI Configuration',
        'login_success' => 'Login',
        'login_failed' => 'Failed Login',
        'logout' => 'Logout',
        'system_init' => 'System Init',
    ];

    public static function label(?string $action): string
    {
        if ($action === null || $action === '') {
            return '';
        }

        return self::LABELS[$action] ?? $action;
    }
}
