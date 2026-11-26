<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the manual "sort_order" to every category table (for the Index/reorder popup).
 * Conditional so a fresh build — where Schemas::category already creates the column —
 * is left untouched.
 */
return new class extends Migration
{
    private array $tables = [
        'frame_categories', 'template_categories', 'dynamic_frame_categories',
        'baby_backdrop_categories', 'video_template_categories', 'ai_image_categories', 'ai_video_categories',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'sort_order')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedInteger('sort_order')->default(0)->after('status');
                    $t->index('sort_order');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'sort_order')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('sort_order'));
            }
        }
    }
};
