<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes audit history entries. Secrets are never recorded.
 */
class Audit
{
    /** Attributes that are never written to the audit log. */
    public const NEVER_LOG = ['password', 'remember_token', 'updated_at', 'created_at'];

    private static bool $enabled = true;

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(string $action, ?Model $subject = null, array $changes = [], ?string $label = null): void
    {
        if (! self::$enabled) {
            return;
        }

        ActivityLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'subject_label' => mb_substr($label ?? self::labelFor($subject), 0, 255),
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }

    public static function labelFor(?Model $subject): string
    {
        if ($subject === null) {
            return '';
        }

        if (method_exists($subject, 'auditLabel')) {
            return (string) $subject->auditLabel();
        }

        return class_basename($subject).' #'.$subject->getKey();
    }
}
