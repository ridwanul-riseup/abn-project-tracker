<?php

namespace App\Providers;

use App\Models\AccessRule;
use App\Models\ApiReference;
use App\Models\Application;
use App\Models\ApplicationSiteCredential;
use App\Models\ApplicationSiteUrl;
use App\Models\Clarification;
use App\Models\ComponentDependency;
use App\Models\DatabaseReference;
use App\Models\Deployment;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Environment;
use App\Models\FigmaReference;
use App\Models\Integration;
use App\Models\Module;
use App\Models\Option;
use App\Models\Product;
use App\Models\Release;
use App\Models\Repository;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Policies\DocumentPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\TaskPolicy;
use App\Support\Access\AccessService;
use App\Support\Access\Capabilities;
use App\Support\Docs\DocumentStorage;
use App\Support\Docs\LocalMarkdownStorage;
use App\Support\Options\Options;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(AccessService::class);
        $this->app->scoped(Options::class);

        $this->app->singleton(DocumentStorage::class, function () {
            $path = (string) config('hub.docs.path');
            $root = str_starts_with($path, '/') ? $path : base_path($path);

            return new LocalMarkdownStorage($root);
        });
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAuthorization();
        $this->configureRateLimiting();

        Paginator::useBootstrapFive();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : Password::min(8),
        );
    }

    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'product' => Product::class,
            'application' => Application::class,
            'application_site_url' => ApplicationSiteUrl::class,
            'application_site_credential' => ApplicationSiteCredential::class,
            'repository' => Repository::class,
            'module' => Module::class,
            'document' => Document::class,
            'task' => Task::class,
            'task_comment' => TaskComment::class,
            'database' => DatabaseReference::class,
            'api' => ApiReference::class,
            'figma' => FigmaReference::class,
            'environment' => Environment::class,
            'deployment' => Deployment::class,
            'release' => Release::class,
            'integration' => Integration::class,
            'dependency' => ComponentDependency::class,
            'clarification' => Clarification::class,
            'user' => User::class,
            'role' => Role::class,
            'option' => Option::class,
            'document_template' => DocumentTemplate::class,
            'access_rule' => AccessRule::class,
            'role_assignment' => RoleAssignment::class,
        ]);
    }

    protected function configureAuthorization(): void
    {
        foreach ([
            Product::class, Application::class, Repository::class, Module::class,
            DatabaseReference::class, ApiReference::class, FigmaReference::class,
            Environment::class, Deployment::class, Release::class, Integration::class,
            ComponentDependency::class, Clarification::class,
        ] as $class) {
            Gate::policy($class, InventoryPolicy::class);
        }
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);

        // With a scope: permission on that record (or an ancestor). Without: held anywhere (menus, buttons).
        foreach (array_keys(Capabilities::all()) as $capability) {
            Gate::define($capability, function (User $user, ?Model $scope = null) use ($capability) {
                $access = app(AccessService::class);

                return $scope !== null
                    ? $access->can($user, $capability, $scope)
                    : $access->hasCapabilityAnywhere($user, $capability);
            });
        }

        // Creating something inside a scope: the permission there plus read/write access to it.
        // Without a scope (records that have no parent) it needs the permission and application-wide write.
        Gate::define('create-in', function (User $user, string $permission, ?Model $scope = null) {
            $access = app(AccessService::class);

            if ($scope === null) {
                return $access->hasCapability($user, $permission) && $access->hasGlobalWrite($user);
            }

            return $access->can($user, $permission, $scope)
                && (Capabilities::grantsWrite([$permission]) ? $access->canWrite($user, $scope) : $access->canRead($user, $scope));
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
