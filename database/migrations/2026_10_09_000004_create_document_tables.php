<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->string('title', 200);
            $table->foreignId('category_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('repository_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('visibility', 20)->default('inherit');
            $table->text('summary')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('content_hash', 64)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->timestamp('content_updated_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['product_id', 'category_id']);
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('summary', 255)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->integer('lines_delta')->default(0);
            $table->json('metadata_changes')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('documents');
    }
};
