# AB Project Hub — Database Schema

MySQL 8, InnoDB, utf8mb4. All tables have `id` (bigint, auto-increment) and `created_at`/`updated_at`
unless stated. Foreign keys to inventory use `RESTRICT` on delete; records are **archived**
(`archived_at`) rather than deleted. Migrations live in `database/migrations/`.

Polymorphic columns (`*_type`) store short aliases from the enforced morph map in
`AppServiceProvider` (`product`, `application`, `repository`, `module`, `document`, `task`,
`api`, `database`, `figma`, `environment`, `deployment`, `release`, `integration`,
`dependency`, `clarification`, `user`, `role`, `option`, `access_rule`, `role_assignment`, `task_comment`).

## Users, roles and permissions

| Table | Key columns | Notes |
| --- | --- | --- |
| `users` | `name`, `email` (unique), `password`, `role_id` → roles, `is_active`, `deactivated_at`, `last_login_at`, `remember_token` | Users are deactivated, never deleted, so history keeps its author. |
| `roles` | `key` (unique), `name`, `description`, `is_system`, `sort_order` | Six seeded system roles. |
| `permissions` | `key` (unique), `name`, `group`, `description` | Permissions per feature module (see `Capabilities::all()`). |
| `permission_role` | `role_id`, `permission_id` (PK pair) | Editable in *Administration → Roles & Permissions*. |
| `role_assignments` | `user_id` → users (cascade), `role_id` → roles (restrict), `scope_type` (`global`/`product`/`application`/`repository`/`module`), `scope_id`, `reason`, `granted_by`, `revoked_at`, `revoked_by` | Scoped roles. Revoked rows are kept for history; a role used here cannot be deleted. |
| `password_reset_tokens`, `sessions` | Laravel defaults | Sessions use the database driver by default. |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Laravel defaults | |

## Global settings

| Table | Key columns | Notes |
| --- | --- | --- |
| `option_groups` | `key` (unique), `name`, `description` | One per configurable dropdown (catalogue in `OptionCatalog`). |
| `options` | `option_group_id`, `value` (unique per group, immutable), `label`, `description`, `color`, `behavior`, `sort_order`, `is_default`, `is_active` | Referenced by FK from inventory/task tables. `behavior` (`open`/`blocked`/`closed`) is used for `task_status`. At most one active `is_default` per group (enforced in `OptionController`); it is preselected on create forms. |
| `document_templates` | `key` (unique, immutable), `name`, `description`, `category_id` → options (`document_category`, restrict on delete), `content`, `sort_order`, `is_active` | Templates offered on *New document*. Seeded once from `resources/doc-templates/*.md`. |

Option groups: `task_status`, `task_type`, `priority`, `product_status`, `application_status`,
`application_type`, `application_classification`, `repository_status`, `repository_layer`,
`module_status`, `deployment_method`, `deployment_target`, `deployment_status`, `release_status`,
`reference_status`, `document_category`, `dependency_type`, `criticality`, `dependency_status`,
`design_type`.

## Inventory

| Table | Key columns |
| --- | --- |
| `products` | `code` (unique, e.g. `OTT`), `name`, `slug` (unique, used for doc folders), `description`, `primary_owner`, `status_id`, `notes`, `archived_at` |
| `applications` | `code` (unique), `product_id`, `name`, `application_type_id`, `classification_id`, `description`, `status_id`, `notes`, `archived_at` |
| `repositories` | `code` (unique), `name`, `product_id`, `application_id`, `layer_id`, `url`, `primary_owner`, `tech_stack`, `module_label`, `database_reference_id`, `api_reference`, `figma_url`, `issue_tracker_url`, `issue_tracker_ref`, `current_branch`, `deployment_target_id`, `status_id`, `notes`, `sensitive_notes`, `archived_at` |
| `modules` | `code` (unique), `product_id`, `application_id`, `name`, `description`, `primary_repository_id`, `api_reference`, `database_reference_id`, `figma_url`, `owner`, `status_id`, `priority_id`, `notes`, `archived_at` |
| `module_repository` | `module_id`, `repository_id` (PK pair) |
| `component_dependencies` | `code` (unique), `source_type`/`source_id`, `target_type`/`target_id` (unique pair), `dependency_type_id`, `criticality_id`, `description`, `failure_impact`, `status_id`, `document_id`, `notes` |

Dependency endpoints may be a repository, module, API reference, database or integration.
`sensitive_notes` on repositories is only shown to users with `repositories.view_sensitive`; it
is still not a place for secrets (the `NoSecrets` rule applies).

## Reference registries

| Table | Key columns |
| --- | --- |
| `database_references` | `code`, `name`, `product_id` (nullable), `owner`, `used_by`, `system_of_record`, `environment_notes`, `status_id`, `notes` — **no connection strings or credentials** |
| `api_references` | `code`, `name`, `product_id`, `repository_id`, `base_path`, `spec_url`, `document_id`, `status_id`, `notes` |
| `figma_references` | `code`, `name`, `product_id`, `application_id`, `repository_id`, `design_type_id`, `url`, `status_id`, `notes` |
| `integrations` | `code`, `name`, `source_product_id`, `target_product_id`, `description`, `document_id`, `status_id`, `notes` |
| `integration_repository` | `integration_id`, `repository_id` |
| `environments` | `code`, `name`, `purpose`, `deployment_method_id`, `status_id`, `sort_order`, `notes` |
| `environment_repository` | `environment_id`, `repository_id` |
| `deployments` | `code`, `repository_id`, `environment_id`, `infrastructure`, `method_id`, `branch`, `version`, `secret_reference` (name/location in a secret manager only), `deployed_on`, `status_id`, `notes` |
| `releases` | `code`, `product_id`, `version`, `environment_id`, `release_date`, `status_id`, `document_id`, `notes` |
| `release_repository` | `release_id`, `repository_id` |

## Documentation

| Table | Key columns | Notes |
| --- | --- | --- |
| `documents` | `path` (unique, relative to docs root), `title`, `category_id`, `product_id`, `repository_id`, `module_id`, `visibility` (`inherit`/`all_users`), `summary`, `owner_id`, `created_by`, `updated_by`, `content_hash` (SHA-256), `size_bytes`, `content_updated_at`, `archived_at` | Content lives in the `.md` file, not the DB. |
| `document_revisions` | `document_id`, `user_id`, `action` (`created`/`updated`/`metadata_updated`/`registered`), `summary`, `content_hash`, `previous_hash`, `size_bytes`, `line_count`, `lines_delta`, `metadata_changes` (JSON), `created_at` | Metadata only — no content snapshots. |

## Tasks

| Table | Key columns | Notes |
| --- | --- | --- |
| `tasks` | `key` (unique, `{PRODUCT}-{n}`), `number` (unique per product), `title`, `description`, `product_id`, `application_id`, `repository_id`, `module_id`, `type_id`, `status_id`, `priority_id`, `assignee_id`, `reporter_id`, `start_date`, `due_date`, `issue_url`, `blocker_description`, `completion_notes` | Number allocated under a row lock on the product. |
| `task_comments` | `task_id`, `user_id`, `body` | Progress notes. |
| `document_task` | `document_id`, `task_id` | Task ↔ document links. |

## Access, audit and clarifications

| Table | Key columns | Notes |
| --- | --- | --- |
| `access_rules` | `user_id`, `scope_type` (`global`/`product`/`application`/`repository`/`module`/`document`), `scope_id`, `permission` (`read`/`write`/`deny`), `reason`, `granted_by`, `revoked_at`, `revoked_by` | Revoked rules are kept for history. At most one active rule per user and scope. |
| `activity_logs` | `user_id`, `action`, `subject_type`, `subject_id`, `subject_label`, `changes` (JSON), `ip_address`, `created_at` | Append-only audit trail. |
| `clarifications` | `subject_type`/`subject_id`, `product_id`, `application_id`, `repository_id`, `module_id`, `field`, `issue`, `original_value`, `source`, `status` (`open`/`resolved`), `resolution`, `resolved_by`, `resolved_at` | "Needs Clarification" items. Scope columns drive visibility. |

## Relationships (overview)

```
products ─┬─< applications ─┬─< repositories >─┬─ module_repository ─< modules
          │                 └─< modules        ├─ environment_repository ─ environments
          ├─< repositories                     ├─ integration_repository ─ integrations
          ├─< modules                          └─ release_repository ─ releases
          ├─< documents, tasks, api_references, figma_references, releases
          └─< integrations (source/target)

documents ─< document_revisions        tasks ─< task_comments
documents >─ document_task ─< tasks
users ─< access_rules, role_assignments >─ roles, activity_logs;  users >─ roles >─ permission_role ─< permissions
options ◄── *_status_id, *_type_id, priority_id, layer_id, category_id, …
```
