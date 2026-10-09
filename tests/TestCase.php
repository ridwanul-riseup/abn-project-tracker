<?php

namespace Tests;

use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\OptionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Every test gets an empty managed docs directory.
        $root = base_path((string) config('hub.docs.path'));
        File::deleteDirectory($root);
        File::ensureDirectoryExists($root);

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed([RolesAndPermissionsSeeder::class, OptionSeeder::class, DocumentTemplateSeeder::class]);
        }
    }
}
