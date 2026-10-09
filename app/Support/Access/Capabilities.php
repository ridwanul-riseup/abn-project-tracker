<?php

namespace App\Support\Access;

use App\Models\Role;

/**
 * Role capabilities ("what a user may do"). Scoped access rules decide
 * "where" a user may do it. Both must allow an action.
 */
class Capabilities
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}> key => [group, name, description]
     */
    public static function all(): array
    {
        return [
            'products.create' => ['Inventory', 'Create products', 'Create new products (creator receives write access).'],
            'inventory.write' => ['Inventory', 'Edit project inventory', 'Create/edit/archive applications, repositories, modules, dependencies and references inside scopes with write access.'],
            'repositories.view_sensitive' => ['Inventory', 'View sensitive technical notes', 'See the "sensitive notes" field on readable repositories.'],
            'docs.write' => ['Documentation', 'Edit documentation', 'Create and edit Markdown documents inside scopes with write access.'],
            'tasks.create' => ['Tasks', 'Create tasks', 'Create tasks (e.g. report bugs) in readable scopes.'],
            'tasks.manage' => ['Tasks', 'Manage tasks', 'Edit and assign any task inside scopes with write access.'],
            'tasks.update_assigned' => ['Tasks', 'Update assigned tasks', 'Change status/progress of tasks assigned to the user.'],
            'tasks.comment' => ['Tasks', 'Comment on tasks', 'Add progress notes to readable tasks.'],
            'users.manage' => ['Administration', 'Manage users', 'Create, edit, deactivate users and assign non-admin roles.'],
            'access.manage' => ['Administration', 'Manage scoped access', 'Grant, deny and revoke scoped access for users.'],
            'roles.manage' => ['Administration', 'Manage role permissions', 'Change which capabilities each role has.'],
            'settings.manage' => ['Administration', 'Manage global settings', 'Manage configurable dropdown options.'],
            'audit.view' => ['Administration', 'View audit history', 'View the activity/audit log.'],
        ];
    }

    /**
     * Initial capabilities per role. Super Admin implicitly has all capabilities.
     *
     * @return array<string, list<string>>
     */
    public static function defaultsByRole(): array
    {
        return [
            Role::SUPER_ADMIN => array_keys(self::all()),
            Role::PROJECT_ADMIN => [
                'products.create', 'inventory.write', 'repositories.view_sensitive', 'docs.write',
                'tasks.create', 'tasks.manage', 'tasks.update_assigned', 'tasks.comment', 'audit.view',
            ],
            Role::PROJECT_MANAGER => [
                'inventory.write', 'docs.write', 'tasks.create', 'tasks.manage', 'tasks.update_assigned', 'tasks.comment',
            ],
            Role::DEVELOPER => ['docs.write', 'tasks.update_assigned', 'tasks.comment'],
            Role::QA_ENGINEER => ['tasks.create', 'tasks.update_assigned', 'tasks.comment'],
            Role::VIEWER => [],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}> key => [name, description]
     */
    public static function roles(): array
    {
        return [
            Role::SUPER_ADMIN => ['Super Admin', 'Full application access, including users, roles, access and global settings. Cannot be denied access.'],
            Role::PROJECT_ADMIN => ['Project Admin', 'Manages authorized products, repositories, modules, tasks and documentation.'],
            Role::PROJECT_MANAGER => ['Project Manager', 'Manages project information and development tasks in assigned scopes.'],
            Role::DEVELOPER => ['Developer', 'Views assigned projects, updates assigned tasks, edits docs where granted write access.'],
            Role::QA_ENGINEER => ['QA Engineer', 'Views authorized projects, reports bugs and updates assigned tasks.'],
            Role::VIEWER => ['Viewer', 'Read-only access to explicitly authorized resources.'],
        ];
    }
}
