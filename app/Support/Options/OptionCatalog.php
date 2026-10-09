<?php

namespace App\Support\Options;

/**
 * Definition of every configurable option group: purpose, initial values,
 * and the database columns that reference it (used for "where used" and
 * safe-deletion checks).
 *
 * Values seeded here are only the initial set; administrators manage them
 * from Global Settings. Security-relevant rules (roles, permissions, access
 * levels) are intentionally NOT configurable options.
 */
class OptionCatalog
{
    /** Allowed "behavior" values for task statuses (structural, not configurable). */
    public const TASK_STATUS_BEHAVIORS = [
        'open' => 'Open (counts as active work)',
        'blocked' => 'Blocked (counts as active and blocked)',
        'closed' => 'Closed (done or cancelled)',
    ];

    public const COLORS = ['secondary', 'primary', 'info', 'success', 'warning', 'danger', 'dark', 'light'];

    /**
     * @return array<string, array{name: string, description: string, usage: list<array{0: string, 1: string, 2: string}>, values: list<array{0: string, 1: string, 2?: string, 3?: string}>}>
     */
    public static function groups(): array
    {
        $needsClarification = ['needs_clarification', 'Needs Clarification', 'warning'];

        return [
            'task_status' => [
                'name' => 'Task statuses',
                'description' => 'Workflow statuses for development tasks. The behavior decides whether a status counts as open, blocked or closed.',
                'usage' => [['tasks', 'status_id', 'Tasks']],
                'values' => [
                    ['backlog', 'Backlog', 'secondary', 'open'],
                    ['todo', 'To Do', 'info', 'open'],
                    ['in_progress', 'In Progress', 'primary', 'open'],
                    ['in_review', 'In Review', 'primary', 'open'],
                    ['blocked', 'Blocked', 'danger', 'blocked'],
                    ['testing', 'Testing', 'info', 'open'],
                    ['done', 'Done', 'success', 'closed'],
                    ['cancelled', 'Cancelled', 'dark', 'closed'],
                ],
            ],
            'task_type' => [
                'name' => 'Task types',
                'description' => 'Classification of development tasks.',
                'usage' => [['tasks', 'type_id', 'Tasks']],
                'values' => [
                    ['feature', 'Feature', 'primary'],
                    ['bug', 'Bug', 'danger'],
                    ['improvement', 'Improvement', 'info'],
                    ['technical_debt', 'Technical Debt', 'warning'],
                    ['documentation', 'Documentation', 'secondary'],
                    ['research', 'Research', 'secondary'],
                    ['integration', 'Integration', 'info'],
                    ['other', 'Other', 'light'],
                ],
            ],
            'priority' => [
                'name' => 'Priorities',
                'description' => 'Priority levels for tasks and modules.',
                'usage' => [['tasks', 'priority_id', 'Tasks'], ['modules', 'priority_id', 'Modules']],
                'values' => [
                    ['low', 'Low', 'secondary'],
                    ['medium', 'Medium', 'info'],
                    ['high', 'High', 'warning'],
                    ['critical', 'Critical', 'danger'],
                ],
            ],
            'product_status' => [
                'name' => 'Product statuses',
                'description' => 'Lifecycle status of products.',
                'usage' => [['products', 'status_id', 'Products']],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['in_development', 'In Development', 'primary'],
                    ['active', 'Active', 'success'],
                    ['on_hold', 'On Hold', 'secondary'],
                    ['deprecated', 'Deprecated', 'dark'],
                    $needsClarification,
                ],
            ],
            'application_status' => [
                'name' => 'Application statuses',
                'description' => 'Lifecycle status of applications.',
                'usage' => [['applications', 'status_id', 'Applications']],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['in_development', 'In Development', 'primary'],
                    ['active', 'Active', 'success'],
                    ['deprecated', 'Deprecated', 'dark'],
                    $needsClarification,
                ],
            ],
            'application_type' => [
                'name' => 'Application types',
                'description' => 'Kind of application within a product.',
                'usage' => [['applications', 'application_type_id', 'Applications']],
                'values' => [
                    ['admin_panel', 'Admin Panel', 'secondary'],
                    ['website', 'Website', 'secondary'],
                    ['client_portal', 'Client Portal', 'secondary'],
                    ['dashboard', 'Dashboard', 'secondary'],
                    ['product_application', 'Product Application', 'secondary'],
                    ['platform_service', 'Platform Service', 'secondary'],
                    ['other', 'Other', 'light'],
                ],
            ],
            'application_classification' => [
                'name' => 'Application frontend/backend classification',
                'description' => 'Whether an application consists of frontend, backend or both.',
                'usage' => [['applications', 'classification_id', 'Applications']],
                'values' => [
                    ['frontend', 'Frontend', 'info'],
                    ['backend', 'Backend', 'primary'],
                    ['fe_be', 'Frontend + Backend', 'secondary'],
                ],
            ],
            'repository_status' => [
                'name' => 'Repository statuses',
                'description' => 'Lifecycle status of repositories.',
                'usage' => [['repositories', 'status_id', 'Repositories']],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['in_development', 'In Development', 'primary'],
                    ['active', 'Active', 'success'],
                    ['maintenance', 'Maintenance', 'secondary'],
                    ['archived', 'Archived', 'dark'],
                    $needsClarification,
                ],
            ],
            'repository_layer' => [
                'name' => 'Repository layers',
                'description' => 'Technical layer of a repository.',
                'usage' => [['repositories', 'layer_id', 'Repositories']],
                'values' => [
                    ['frontend', 'Frontend', 'info'],
                    ['backend', 'Backend', 'primary'],
                    ['fullstack', 'Full-stack', 'secondary'],
                    ['infrastructure', 'Infrastructure', 'dark'],
                    ['other', 'Other', 'light'],
                ],
            ],
            'module_status' => [
                'name' => 'Module statuses',
                'description' => 'Implementation status of modules and features.',
                'usage' => [['modules', 'status_id', 'Modules']],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['in_development', 'In Development', 'primary'],
                    ['implemented', 'Implemented', 'success'],
                    ['deprecated', 'Deprecated', 'dark'],
                    $needsClarification,
                ],
            ],
            'deployment_method' => [
                'name' => 'Deployment methods',
                'description' => 'How code is deployed to an environment.',
                'usage' => [['environments', 'deployment_method_id', 'Environments'], ['deployments', 'method_id', 'Deployments']],
                'values' => [
                    ['manual', 'Manual', 'secondary'],
                    ['ci_cd', 'CI/CD', 'primary'],
                    ['controlled_ci_cd', 'Controlled CI/CD', 'warning'],
                ],
            ],
            'deployment_target' => [
                'name' => 'Deployment targets',
                'description' => 'Hosting target type of a repository.',
                'usage' => [['repositories', 'deployment_target_id', 'Repositories']],
                'values' => [
                    ['web_cdn', 'Web/CDN', 'info'],
                    ['api_server', 'API Server', 'primary'],
                    ['other', 'Other', 'light'],
                ],
            ],
            'deployment_status' => [
                'name' => 'Deployment statuses',
                'description' => 'Status of deployment records.',
                'usage' => [['deployments', 'status_id', 'Deployments']],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['in_progress', 'In Progress', 'primary'],
                    ['deployed', 'Deployed', 'success'],
                    ['failed', 'Failed', 'danger'],
                    ['rolled_back', 'Rolled Back', 'warning'],
                ],
            ],
            'release_status' => [
                'name' => 'Release statuses',
                'description' => 'Status of product releases.',
                'usage' => [['releases', 'status_id', 'Releases']],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['in_progress', 'In Progress', 'primary'],
                    ['released', 'Released', 'success'],
                    ['cancelled', 'Cancelled', 'dark'],
                ],
            ],
            'reference_status' => [
                'name' => 'Reference record statuses',
                'description' => 'Status for API, database, Figma, environment and integration records.',
                'usage' => [
                    ['api_references', 'status_id', 'API references'],
                    ['database_references', 'status_id', 'Database references'],
                    ['figma_references', 'status_id', 'Figma references'],
                    ['environments', 'status_id', 'Environments'],
                    ['integrations', 'status_id', 'Integrations'],
                ],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['active', 'Active', 'success'],
                    ['deprecated', 'Deprecated', 'dark'],
                    $needsClarification,
                ],
            ],
            'document_category' => [
                'name' => 'Documentation categories',
                'description' => 'Categories used to organise Markdown documentation. The value is also used as the folder name for general (non-product) documents.',
                'usage' => [['documents', 'category_id', 'Documents']],
                'values' => [
                    ['overview', 'Overview', 'primary'],
                    ['architecture', 'Architecture', 'info'],
                    ['modules', 'Module', 'secondary'],
                    ['apis', 'API', 'secondary'],
                    ['databases', 'Database', 'secondary'],
                    ['deployment', 'Deployment', 'secondary'],
                    ['decisions', 'Decision (ADR)', 'warning'],
                    ['integrations', 'Integration', 'secondary'],
                    ['repositories', 'Repository', 'secondary'],
                    ['troubleshooting', 'Troubleshooting', 'danger'],
                    ['glossary', 'Glossary', 'light'],
                    ['hub', 'Project Hub (system docs)', 'dark'],
                    ['other', 'Other', 'light'],
                ],
            ],
            'dependency_type' => [
                'name' => 'Dependency types',
                'description' => 'Nature of a dependency between components.',
                'usage' => [['component_dependencies', 'dependency_type_id', 'Dependencies']],
                'values' => [
                    ['authentication', 'Authentication', 'danger'],
                    ['api', 'API', 'primary'],
                    ['database', 'Database', 'info'],
                    ['data_sync', 'Data Sync', 'secondary'],
                    ['integration', 'Integration', 'secondary'],
                    ['infrastructure', 'Infrastructure', 'dark'],
                    ['library', 'Library', 'light'],
                    ['unspecified', 'Unspecified (Needs Clarification)', 'warning'],
                ],
            ],
            'criticality' => [
                'name' => 'Criticality levels',
                'description' => 'Business impact if a dependency fails.',
                'usage' => [['component_dependencies', 'criticality_id', 'Dependencies']],
                'values' => [
                    ['low', 'Low', 'secondary'],
                    ['medium', 'Medium', 'info'],
                    ['high', 'High', 'warning'],
                    ['critical', 'Critical', 'danger'],
                ],
            ],
            'dependency_status' => [
                'name' => 'Dependency statuses',
                'description' => 'Status of dependency records.',
                'usage' => [['component_dependencies', 'status_id', 'Dependencies']],
                'values' => [
                    ['planned', 'Planned', 'info'],
                    ['active', 'Active', 'success'],
                    ['deprecated', 'Deprecated', 'dark'],
                    $needsClarification,
                ],
            ],
            'design_type' => [
                'name' => 'Design types',
                'description' => 'Kind of Figma / design reference.',
                'usage' => [['figma_references', 'design_type_id', 'Figma references']],
                'values' => [
                    ['ui_ux', 'UI/UX', 'primary'],
                    ['wireframe', 'Wireframe', 'secondary'],
                    ['prototype', 'Prototype', 'info'],
                    ['design_system', 'Design System', 'dark'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function usage(string $groupKey): array
    {
        return self::groups()[$groupKey]['usage'] ?? [];
    }
}
