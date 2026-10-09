<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Defaults that reproduce the behaviour before defaults became configurable. */
    private const INITIAL_DEFAULTS = [
        'task_status' => 'backlog',
        'task_type' => 'feature',
        'priority' => 'medium',
    ];

    public function up(): void
    {
        Schema::table('options', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('sort_order');
        });

        foreach (self::INITIAL_DEFAULTS as $group => $value) {
            $groupId = DB::table('option_groups')->where('key', $group)->value('id');
            if ($groupId !== null) {
                DB::table('options')->where('option_group_id', $groupId)->where('value', $value)
                    ->where('is_active', true)->update(['is_default' => true]);
            }
        }

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('options')->restrictOnDelete();
            $table->text('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');

        Schema::table('options', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
