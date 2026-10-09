<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_references', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->string('base_path', 255)->nullable();
            $table->string('spec_url', 500)->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('figma_references', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('design_type_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->string('url', 1000)->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->foreignId('source_product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('target_product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_repository', function (Blueprint $table) {
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->primary(['integration_id', 'repository_id']);
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->foreignId('repository_id')->constrained()->restrictOnDelete();
            $table->foreignId('environment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('infrastructure', 100)->nullable();
            $table->foreignId('method_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->string('branch', 100)->nullable();
            $table->string('version', 50)->nullable();
            $table->string('secret_reference', 255)->nullable();
            $table->date('deployed_on')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('version', 50);
            $table->foreignId('environment_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('release_date')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('release_repository', function (Blueprint $table) {
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->primary(['release_id', 'repository_id']);
        });

        Schema::create('component_dependencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('dependency_type_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('criticality_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->text('failure_impact')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['target_type', 'target_id']);
            $table->unique(['source_type', 'source_id', 'target_type', 'target_id'], 'component_dependencies_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_dependencies');
        Schema::dropIfExists('release_repository');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('integration_repository');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('figma_references');
        Schema::dropIfExists('api_references');
    }
};
