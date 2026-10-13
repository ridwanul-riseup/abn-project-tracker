<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('application_site_urls')) {
            Schema::create('application_site_urls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained()->cascadeOnDelete();
                $table->string('label', 100)->nullable();
                $table->string('url', 500);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['application_id', 'sort_order'], 'app_site_urls_app_sort_idx');
            });
        }

        if (! Schema::hasTable('application_site_credentials')) {
            Schema::create('application_site_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_site_url_id')->constrained('application_site_urls')->cascadeOnDelete();
                $table->string('title', 150);
                $table->string('account_login', 255);
                $table->text('password');
                $table->string('short_description', 500)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['application_site_url_id', 'sort_order'], 'app_site_creds_url_sort_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_site_credentials');
        Schema::dropIfExists('application_site_urls');
    }
};
