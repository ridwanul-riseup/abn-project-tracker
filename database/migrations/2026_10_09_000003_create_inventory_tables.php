<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->text('description')->nullable();
            $table->string('primary_owner', 150)->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->foreignId('application_type_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('classification_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('database_references', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 150);
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('owner', 150)->nullable();
            $table->string('used_by', 255)->nullable();
            $table->string('system_of_record', 150)->nullable();
            $table->string('environment_notes', 255)->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('purpose', 255)->nullable();
            $table->foreignId('deployment_method_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 200);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('layer_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->string('url', 500)->nullable();
            $table->string('primary_owner', 150)->nullable();
            $table->string('tech_stack', 255)->nullable();
            $table->string('module_label', 150)->nullable();
            $table->foreignId('database_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->string('api_reference', 255)->nullable();
            $table->string('figma_url', 1000)->nullable();
            $table->string('issue_tracker_url', 500)->nullable();
            $table->string('issue_tracker_ref', 100)->nullable();
            $table->string('current_branch', 100)->nullable();
            $table->foreignId('deployment_target_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->text('sensitive_notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['product_id', 'layer_id']);
        });

        Schema::create('environment_repository', function (Blueprint $table) {
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->primary(['environment_id', 'repository_id']);
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('primary_repository_id')->nullable()->constrained('repositories')->nullOnDelete();
            $table->string('api_reference', 255)->nullable();
            $table->foreignId('database_reference_id')->nullable()->constrained()->nullOnDelete();
            $table->string('figma_url', 1000)->nullable();
            $table->string('owner', 150)->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('module_repository', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->primary(['module_id', 'repository_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_repository');
        Schema::dropIfExists('modules');
        Schema::dropIfExists('environment_repository');
        Schema::dropIfExists('repositories');
        Schema::dropIfExists('environments');
        Schema::dropIfExists('database_references');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('products');
    }
};
