<?php

// Every list is paginated: index pages, admin lists and related lists on detail pages.

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\TaskComment;

it('pages related lists on a detail page independently', function () {
    config(['hub.detail_per_page' => 2]);
    $ott = makeProduct('OTT');
    foreach (['OTT-A', 'OTT-B', 'OTT-C'] as $code) {
        makeModule($ott, $code);
    }
    foreach (['OTT-R1', 'OTT-R2', 'OTT-R3'] as $code) {
        makeRepository($ott, $code);
    }

    $this->actingAs(superAdmin())->get(route('products.show', $ott))
        ->assertOk()
        ->assertSee('OTT-A')->assertDontSee('OTT-C')
        ->assertSee('OTT-R1')->assertDontSee('OTT-R3')
        ->assertSee('1–2 of 3')
        ->assertSee('modules=2', false);

    $this->get(route('products.show', [$ott, 'modules' => 2]))
        ->assertSee('OTT-C')->assertDontSee('OTT-A')
        ->assertSee('OTT-R1');
});

it('pages task comments and returns to the newest page after commenting', function () {
    config(['hub.detail_per_page' => 2]);
    $admin = superAdmin();
    $ott = makeProduct('OTT');
    $task = makeTask($ott->id);
    foreach (['First note', 'Second note'] as $body) {
        TaskComment::query()->create(['task_id' => $task->id, 'user_id' => $admin->id, 'body' => $body]);
    }

    $this->actingAs($admin)->post(route('tasks.comments.store', $task), ['body' => 'Third note'])
        ->assertRedirect(route('tasks.show', [$task, 'comments' => 2]).'#comments');

    $this->get(route('tasks.show', $task))->assertSee('First note')->assertDontSee('Third note')->assertSee('Comments (3)');
    $this->get(route('tasks.show', [$task, 'comments' => 2]))->assertSee('Third note');
});

it('pages option values in global settings', function () {
    config(['hub.per_page' => 3]);
    $group = OptionGroup::query()->where('key', 'task_type')->firstOrFail();
    $last = Option::query()->where('option_group_id', $group->id)->orderByDesc('sort_order')->firstOrFail();

    $this->actingAs(superAdmin())->get(route('admin.options.show', $group))
        ->assertOk()->assertDontSee($last->value)->assertSee('page=2', false);
});
