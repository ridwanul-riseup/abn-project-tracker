<?php

use App\Http\Controllers\Admin\AccessRuleController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\OptionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ClarificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DependencyController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Support\Registries;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1');
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('search', SearchController::class)->name('search');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->middleware('throttle:sensitive')->name('profile.password');

    // Inventory
    foreach ([
        'products' => ProductController::class,
        'applications' => ApplicationController::class,
        'repositories' => RepositoryController::class,
        'modules' => ModuleController::class,
    ] as $name => $controller) {
        $parameter = Str::singular($name);
        Route::resource($name, $controller)->except('destroy');
        Route::post("{$name}/{{$parameter}}/archive", [$controller, 'archive'])->name("{$name}.archive");
        Route::post("{$name}/{{$parameter}}/restore", [$controller, 'restore'])->name("{$name}.restore");
    }

    Route::resource('dependencies', DependencyController::class)->except('show');

    Route::get('clarifications', [ClarificationController::class, 'index'])->name('clarifications.index');
    Route::post('clarifications/{clarification}/resolve', [ClarificationController::class, 'resolve'])->name('clarifications.resolve');
    Route::post('clarifications/{clarification}/reopen', [ClarificationController::class, 'reopen'])->name('clarifications.reopen');

    // Documentation
    Route::get('docs', [DocumentController::class, 'index'])->name('docs.index');
    Route::get('docs/create', [DocumentController::class, 'create'])->name('docs.create');
    Route::post('docs', [DocumentController::class, 'store'])->name('docs.store');
    Route::get('docs/resolve', [DocumentController::class, 'resolve'])->name('docs.resolve');
    Route::post('docs/preview', [DocumentController::class, 'preview'])->middleware('throttle:sensitive')->name('docs.preview');
    Route::get('docs/{document}', [DocumentController::class, 'show'])->name('docs.show');
    Route::get('docs/{document}/edit', [DocumentController::class, 'edit'])->name('docs.edit');
    Route::put('docs/{document}', [DocumentController::class, 'update'])->name('docs.update');
    Route::get('docs/{document}/history', [DocumentController::class, 'history'])->name('docs.history');
    Route::post('docs/{document}/archive', [DocumentController::class, 'archive'])->name('docs.archive');
    Route::post('docs/{document}/restore', [DocumentController::class, 'restore'])->name('docs.restore');

    // Tasks
    Route::resource('tasks', TaskController::class)->except('destroy');
    Route::patch('tasks/{task}/progress', [TaskController::class, 'progress'])->name('tasks.progress');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->middleware('throttle:sensitive')->name('tasks.comments.store');

    // Reports (read-only, limited to the user's access)
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/modules', [ReportController::class, 'modules'])->name('reports.modules');
    Route::get('reports/workload', [ReportController::class, 'workload'])->name('reports.workload');
    Route::get('reports/products/{product}', [ReportController::class, 'product'])->name('reports.product');

    // Reference registries (APIs, integrations, databases, Figma, environments, deployments, releases)
    Route::prefix('registry/{type}')->whereIn('type', Registries::types())->name('registries.')->group(function () {
        Route::get('/', [RegistryController::class, 'index'])->name('index');
        Route::get('create', [RegistryController::class, 'create'])->name('create');
        Route::post('/', [RegistryController::class, 'store'])->name('store');
        Route::get('{id}', [RegistryController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('{id}/edit', [RegistryController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('{id}', [RegistryController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('{id}', [RegistryController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // Administration (every action is additionally authorized in the controllers)
    Route::prefix('admin')->name('admin.')->middleware('throttle:120,1')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/send-reset', [UserController::class, 'sendReset'])->middleware('throttle:sensitive')->name('users.send-reset');

        Route::get('users/{user}/access', [AccessRuleController::class, 'index'])->name('users.access');
        Route::post('users/{user}/access', [AccessRuleController::class, 'store'])->name('users.access.store');
        Route::post('access-rules/{rule}/revoke', [AccessRuleController::class, 'revoke'])->name('access-rules.revoke');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles', [RoleController::class, 'update'])->name('roles.update');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('roles/{role}', [RoleController::class, 'updateDetails'])->name('roles.details');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('settings', [OptionController::class, 'index'])->name('options.index');
        Route::get('settings/{group:key}', [OptionController::class, 'show'])->name('options.show');
        Route::post('settings/{group:key}', [OptionController::class, 'store'])->name('options.store');
        Route::put('options/{option}', [OptionController::class, 'update'])->name('options.update');
        Route::post('options/{option}/toggle', [OptionController::class, 'toggle'])->name('options.toggle');
        Route::post('options/{option}/default', [OptionController::class, 'setDefault'])->name('options.default');
        Route::post('options/{option}/move/{direction}', [OptionController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('options.move');
        Route::delete('options/{option}', [OptionController::class, 'destroy'])->name('options.destroy');

        Route::get('templates', [DocumentTemplateController::class, 'index'])->name('templates.index');
        Route::get('templates/create', [DocumentTemplateController::class, 'create'])->name('templates.create');
        Route::post('templates', [DocumentTemplateController::class, 'store'])->name('templates.store');
        Route::get('templates/{template}/edit', [DocumentTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('templates/{template}', [DocumentTemplateController::class, 'update'])->name('templates.update');
        Route::post('templates/{template}/toggle', [DocumentTemplateController::class, 'toggle'])->name('templates.toggle');
        Route::post('templates/{template}/move/{direction}', [DocumentTemplateController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('templates.move');
        Route::delete('templates/{template}', [DocumentTemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');
    });
});
