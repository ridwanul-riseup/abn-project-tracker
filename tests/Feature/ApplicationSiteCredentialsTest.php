<?php

use App\Models\Application;
use App\Models\ApplicationSiteCredential;
use App\Models\ApplicationSiteUrl;
use App\Models\Role;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->product = makeProduct('TST');
    $this->admin = superAdmin();
    $this->application = Application::query()->create([
        'code' => 'TST-APP',
        'name' => 'Test application',
        'product_id' => $this->product->id,
    ]);
});

it('creates an application with nested site urls and encrypted credentials', function () {
    $this->actingAs($this->admin)->post(route('applications.store'), [
        'code' => 'TST-WEB',
        'name' => 'Web app',
        'product_id' => $this->product->id,
        'site_urls' => [[
            'label' => 'Development',
            'url' => 'https://dev.example.com',
            'credentials' => [[
                'title' => 'Admin Panel',
                'account_login' => 'dev_admin',
                'password' => 'S3cret-Dev-Pass!',
                'short_description' => 'Dev admin',
            ]],
        ]],
    ])->assertRedirect();

    $app = Application::query()->where('code', 'TST-WEB')->firstOrFail();
    $url = $app->siteUrls()->firstOrFail();
    $credential = $url->credentials()->firstOrFail();

    expect($url->label)->toBe('Development')
        ->and($url->url)->toBe('https://dev.example.com')
        ->and($credential->title)->toBe('Admin Panel')
        ->and($credential->account_login)->toBe('dev_admin')
        ->and($credential->password)->toBe('S3cret-Dev-Pass!');

    $raw = DB::table('application_site_credentials')->where('id', $credential->id)->value('password');
    expect($raw)->not->toBe('S3cret-Dev-Pass!')
        ->and(Crypt::decryptString($raw))->toBe('S3cret-Dev-Pass!');
});

it('loads credentials on edit without exposing passwords and updates them in one transaction', function () {
    $url = $this->application->siteUrls()->create(['label' => 'Staging', 'url' => 'https://stage.example.com', 'sort_order' => 0]);
    $credential = $url->credentials()->create([
        'title' => 'Portal',
        'account_login' => 'stage_user',
        'password' => 'Old-Pass-123',
        'sort_order' => 0,
    ]);

    $this->actingAs($this->admin);
    $this->get(route('applications.edit', $this->application))->assertOk()
        ->assertSee('https://stage.example.com')
        ->assertSee('stage_user')
        ->assertDontSee('Old-Pass-123');

    $this->put(route('applications.update', $this->application), [
        'code' => $this->application->code,
        'name' => $this->application->name,
        'product_id' => $this->product->id,
        'site_urls' => [[
            'id' => $url->id,
            'label' => 'Staging',
            'url' => 'https://stage.example.com',
            'credentials' => [[
                'id' => $credential->id,
                'title' => 'Portal updated',
                'account_login' => 'stage_user',
                'password' => '',
                'short_description' => 'Still staging',
            ]],
        ]],
    ])->assertRedirect(route('applications.show', $this->application));

    expect($credential->fresh()->title)->toBe('Portal updated')
        ->and($credential->fresh()->password)->toBe('Old-Pass-123');

    $this->put(route('applications.update', $this->application), [
        'code' => $this->application->code,
        'name' => $this->application->name,
        'product_id' => $this->product->id,
        'site_urls' => [[
            'id' => $url->id,
            'label' => 'Staging',
            'url' => 'https://stage.example.com',
            'credentials' => [[
                'id' => $credential->id,
                'title' => 'Portal updated',
                'account_login' => 'stage_user',
                'password' => 'New-Pass-456',
            ]],
        ]],
    ])->assertRedirect();

    expect($credential->fresh()->password)->toBe('New-Pass-456');
});

it('shows masked credentials on the application detail page and hides them without permission', function () {
    $url = $this->application->siteUrls()->create(['url' => 'https://prod.example.com', 'sort_order' => 0]);
    $url->credentials()->create([
        'title' => 'Admin',
        'account_login' => 'prod_admin',
        'password' => 'Hidden-1',
        'sort_order' => 0,
    ]);

    $viewer = userWithRole(Role::VIEWER, [[$this->product, 'read']]);

    $this->actingAs($this->admin)->get(route('applications.show', $this->application))->assertOk()
        ->assertSee('prod_admin')
        ->assertSee('Admin')
        ->assertDontSee('Hidden-1')
        ->assertSee('••••••••');

    $this->actingAs($viewer)->get(route('applications.show', $this->application))->assertOk()
        ->assertDontSee('Live site URLs')
        ->assertDontSee('prod_admin');
});

it('lists credentials with filters and keeps passwords masked', function () {
    $url = $this->application->siteUrls()->create(['label' => 'Production', 'url' => 'https://app.example.com', 'sort_order' => 0]);
    ApplicationSiteCredential::query()->create([
        'application_site_url_id' => $url->id,
        'title' => 'Client Portal',
        'account_login' => 'client1',
        'password' => 'Secret-Client',
        'sort_order' => 0,
    ]);

    $this->actingAs($this->admin)->get(route('application-credentials.index', ['q' => 'Client Portal']))->assertOk()
        ->assertSee('TST-APP')
        ->assertSee('https://app.example.com')
        ->assertSee('client1')
        ->assertDontSee('Secret-Client');
});

it('removes urls and credentials omitted from the edit form', function () {
    $keep = $this->application->siteUrls()->create(['url' => 'https://keep.example.com', 'sort_order' => 0]);
    $drop = $this->application->siteUrls()->create(['url' => 'https://drop.example.com', 'sort_order' => 1]);
    $drop->credentials()->create(['title' => 'Gone', 'account_login' => 'x', 'password' => 'x', 'sort_order' => 0]);

    $this->actingAs($this->admin)->put(route('applications.update', $this->application), [
        'code' => $this->application->code,
        'name' => $this->application->name,
        'product_id' => $this->product->id,
        'site_urls' => [[
            'id' => $keep->id,
            'url' => 'https://keep.example.com',
            'credentials' => [],
        ]],
    ])->assertRedirect();

    expect(ApplicationSiteUrl::query()->whereKey($drop->id)->exists())->toBeFalse()
        ->and(ApplicationSiteCredential::query()->count())->toBe(0);
});
