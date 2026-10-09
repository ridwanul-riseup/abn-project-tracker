<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->unsignedInteger('number');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('repository_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('type_id')->constrained('options')->restrictOnDelete();
            $table->foreignId('status_id')->constrained('options')->restrictOnDelete();
            $table->foreignId('priority_id')->constrained('options')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('issue_url', 500)->nullable();
            $table->text('blocker_description')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'number']);
            $table->index(['status_id', 'priority_id']);
            $table->index('assignee_id');
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('document_task', function (Blueprint $table) {
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->primary(['document_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_task');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
    }
};
