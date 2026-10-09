<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Access\AccessService;
use App\Support\Access\HasAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Filters audit entries so that labels of restricted records are never shown.
 *
 *  - Administrative subjects (users, access rules, roles, options) are shown
 *    only to users who manage them.
 *  - Entries about access-scoped records are shown only if the record still
 *    exists and is readable (super admins see everything).
 */
class ActivityFeed
{
    private const ADMIN_CAPABILITY = [
        'user' => 'users.manage',
        'access_rule' => 'access.manage',
        'role' => 'roles.manage',
        'option' => 'settings.manage',
        'document_template' => 'settings.manage',
    ];

    public function __construct(private readonly AccessService $access) {}

    /**
     * @param  Collection<int, ActivityLog>  $logs
     * @return Collection<int, ActivityLog>
     */
    public function filter(User $user, Collection $logs): Collection
    {
        if ($this->access->isSuperAdmin($user)) {
            return $logs;
        }

        $subjects = $this->loadSubjects($logs);

        return $logs->filter(function (ActivityLog $log) use ($user, $subjects) {
            if ($log->subject_type === null) {
                return $this->access->hasCapability($user, 'audit.view');
            }
            if (isset(self::ADMIN_CAPABILITY[$log->subject_type])) {
                return $this->access->hasCapability($user, self::ADMIN_CAPABILITY[$log->subject_type]);
            }

            $subject = $subjects[$log->subject_type][$log->subject_id] ?? null;
            if ($subject === null) {
                return false;
            }

            return ! $subject instanceof HasAccessScope || $this->access->canRead($user, $subject);
        })->values();
    }

    /**
     * Latest entries visible to the user.
     *
     * @return Collection<int, ActivityLog>
     */
    public function recent(User $user, int $limit = 10): Collection
    {
        $logs = ActivityLog::query()->with('user')->latest('id')->limit($limit * 20)->get();

        return $this->filter($user, $logs)->take($limit)->values();
    }

    /**
     * @param  Collection<int, ActivityLog>  $logs
     * @return array<string, array<int, Model>>
     */
    private function loadSubjects(Collection $logs): array
    {
        $result = [];
        foreach ($logs->whereNotNull('subject_type')->groupBy('subject_type') as $type => $group) {
            try {
                /** @var class-string<Model> $class */
                $class = Model::getActualClassNameForMorph((string) $type);
            } catch (\Throwable) {
                continue;
            }
            if (! class_exists($class)) {
                continue;
            }
            $result[$type] = $class::query()->whereIn('id', $group->pluck('subject_id')->filter()->unique())->get()->keyBy('id')->all();
        }

        return $result;
    }
}
