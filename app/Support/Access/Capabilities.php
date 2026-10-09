<?php

namespace App\Support\Access;

use App\Models\Role;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission catalogue ("what a user may do"), per feature module and action.
 * Scoped access rules and scoped role assignments decide "where". Both must allow an action.
 */
class Capabilities
{
    public const ADMIN_GROUP = 'Administration';

    /** Feature module of each access-scoped record type (morph alias). */
    public const FEATURE_BY_TYPE = [
        'product' => 'products',
        'application' => 'applications',
        'repository' => 'repositories',
        'module' => 'modules',
        'dependency' => 'dependencies',
        'database' => 'registries',
        'api' => 'registries',
        'figma' => 'registries',
        'environment' => 'registries',
        'deployment' => 'registries',
        'release' => 'registries',
        'integration' => 'registries',
        'document' => 'docs',
        'task' => 'tasks',
        'clarification' => 'clarifications',
    ];

    /**
     * Permissions that only count application-wide (global role or a global role assignment),
     * never from a role assigned on a product, repository or module.
     */
    public const GLOBAL_ONLY = ['products.create', 'users.manage', 'access.manage', 'roles.manage', 'settings.manage', 'audit.view'];

    /** Actions that need write access to the scope (all others only need read access). */
    private const WRITE_ACTIONS = ['create', 'update', 'archive', 'delete', 'assign', 'resolve'];

    /** Actions that only need read access even though they change something. */
    private const READ_LEVEL_PERMISSIONS = ['tasks.create', 'tasks.update_assigned', 'tasks.comment'];

    /**
     * Permissions replaced by the per-module catalogue; existing role assignments are converted by the seeder.
     *
     * @var array<string, list<string>>
     */
    public const LEGACY = [
        'inventory.write' => [
            'products.update', 'products.archive',
            'applications.create', 'applications.update', 'applications.archive',
            'repositories.create', 'repositories.update', 'repositories.archive',
            'modules.create', 'modules.update', 'modules.archive',
            'dependencies.create', 'dependencies.update', 'dependencies.delete',
            'registries.create', 'registries.update', 'registries.delete',
            'clarifications.resolve',
        ],
        'docs.write' => ['docs.create', 'docs.update', 'docs.archive'],
        'tasks.manage' => ['tasks.update', 'tasks.assign'],
    ];

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> key => [group, name, description]
     */
    public static function all(): array
    {
        return self::$catalogue ??= self::build();
    }

    /** @var array<string, array{0: string, 1: string, 2: string}>|null */
    private static ?array $catalogue = null;

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    private static function build(): array
    {
        $crud = fn (string $group, string $prefix, string $things, string $remove = 'archive') => [
            "{$prefix}.view" => [$group, "View {$things}", "See {$things} inside readable scopes."],
            "{$prefix}.create" => [$group, "Create {$things}", "Add {$things} inside scopes with write access."],
            "{$prefix}.update" => [$group, "Edit {$things}", "Edit {$things} inside scopes with write access."],
            "{$prefix}.{$remove}" => [$group, ucfirst($remove)." {$things}", ucfirst($remove)." {$things} inside scopes with write access."],
        ];

        return [
            ...$crud('Products', 'products', 'products'),
            ...$crud('Applications', 'applications', 'applications'),
            ...$crud('Repositories', 'repositories', 'repositories'),
            'repositories.view_sensitive' => ['Repositories', 'View sensitive technical notes', 'See and edit the "sensitive notes" field of readable repositories.'],
            ...$crud('Modules', 'modules', 'modules'),
            ...$crud('Dependencies', 'dependencies', 'dependencies', 'delete'),
            ...$crud('References', 'registries', 'references (APIs, integrations, databases, Figma, environments, deployments, releases)', 'delete'),
            ...$crud('Documentation', 'docs', 'documents'),
            'tasks.view' => ['Tasks', 'View tasks', 'See tasks inside readable scopes.'],
            'tasks.create' => ['Tasks', 'Create tasks', 'Create tasks (e.g. report bugs) in readable scopes.'],
            'tasks.update' => ['Tasks', 'Edit tasks', 'Edit any task inside scopes with write access.'],
            'tasks.assign' => ['Tasks', 'Assign tasks', 'Choose or change the assignee of tasks inside scopes with write access.'],
            'tasks.update_assigned' => ['Tasks', 'Update assigned tasks', 'Change status/progress of tasks assigned to the user.'],
            'tasks.comment' => ['Tasks', 'Comment on tasks', 'Add progress notes to readable tasks.'],
            'clarifications.view' => ['Clarifications', 'View clarifications', 'See Needs Clarification items of readable records.'],
            'clarifications.resolve' => ['Clarifications', 'Resolve clarifications', 'Resolve and reopen clarification items inside scopes with write access.'],
            'reports.view' => ['Reports', 'View reports', 'See progress reports for readable products, modules and tasks.'],
            'users.manage' => [self::ADMIN_GROUP, 'Manage users', 'Create, edit, deactivate users and assign non-admin roles.'],
            'access.manage' => [self::ADMIN_GROUP, 'Manage scoped access', 'Grant, deny and revoke scoped access and scoped roles for users.'],
            'roles.manage' => [self::ADMIN_GROUP, 'Manage roles', 'Create roles and change which permissions each role has.'],
            'settings.manage' => [self::ADMIN_GROUP, 'Manage global settings', 'Manage configurable dropdown options and document templates.'],
            'audit.view' => [self::ADMIN_GROUP, 'View audit history', 'View the activity/audit log.'],
        ];
    }

    /**
     * Initial permissions per role. Super Admin implicitly has every permission.
     *
     * @return array<string, list<string>>
     */
    public static function defaultsByRole(): array
    {
        $all = array_keys(self::all());
        $view = self::viewPermissions();
        $manageContent = [
            'products.update', 'products.archive',
            'applications.create', 'applications.update', 'applications.archive',
            'repositories.create', 'repositories.update', 'repositories.archive',
            'modules.create', 'modules.update', 'modules.archive',
            'dependencies.create', 'dependencies.update', 'dependencies.delete',
            'registries.create', 'registries.update', 'registries.delete',
            'docs.create', 'docs.update', 'docs.archive',
            'tasks.create', 'tasks.update', 'tasks.assign', 'tasks.update_assigned', 'tasks.comment',
            'clarifications.resolve',
        ];

        return [
            Role::SUPER_ADMIN => $all,
            Role::PROJECT_ADMIN => [...$view, ...$manageContent, 'products.create', 'repositories.view_sensitive', 'audit.view'],
            Role::PROJECT_MANAGER => [...$view, ...$manageContent],
            Role::DEVELOPER => [...$view, 'docs.create', 'docs.update', 'tasks.update_assigned', 'tasks.comment'],
            Role::QA_ENGINEER => [...$view, 'tasks.create', 'tasks.update_assigned', 'tasks.comment'],
            Role::VIEWER => $view,
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}> key => [name, description]
     */
    public static function roles(): array
    {
        return [
            Role::SUPER_ADMIN => ['Super Admin', 'Full application access, including users, roles, access and global settings. Cannot be denied access.'],
            Role::PROJECT_ADMIN => ['Project Admin', 'Creates products and manages all project content in authorized scopes; can read the audit log.'],
            Role::PROJECT_MANAGER => ['Project Manager', 'Manages project information, documentation and development tasks (incl. assignment) in assigned scopes.'],
            Role::DEVELOPER => ['Developer', 'Views assigned projects, writes documentation and updates the tasks assigned to them.'],
            Role::QA_ENGINEER => ['QA Engineer', 'Views assigned projects, reports bugs and updates the tasks assigned to them.'],
            Role::VIEWER => ['Viewer', 'Read-only access to everything inside assigned scopes.'],
        ];
    }

    /**
     * Every feature module's "view" permission, including reports (not the audit log).
     *
     * @return list<string>
     */
    public static function viewPermissions(): array
    {
        return array_values(array_filter(
            array_keys(self::all()),
            fn (string $key) => str_ends_with($key, '.view') && ! self::isGlobalOnly($key),
        ));
    }

    public static function isGlobalOnly(string $permission): bool
    {
        return in_array($permission, self::GLOBAL_ONLY, true);
    }

    /**
     * Whether a set of permissions needs write access to the scope it is assigned on.
     *
     * @param  list<string>  $permissions
     */
    public static function grantsWrite(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::isGlobalOnly($permission) || in_array($permission, self::READ_LEVEL_PERMISSIONS, true)) {
                continue;
            }
            if (in_array(substr($permission, (int) strrpos($permission, '.') + 1), self::WRITE_ACTIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The permission for an action on a record type, e.g. (Repository, 'update') => 'repositories.update'.
     */
    public static function for(Model|string $type, string $action): ?string
    {
        $alias = $type instanceof Model ? $type->getMorphClass() : $type;
        $feature = self::FEATURE_BY_TYPE[$alias] ?? null;
        $key = $feature === null ? null : $feature.'.'.$action;

        return $key !== null && array_key_exists($key, self::all()) ? $key : null;
    }
}
