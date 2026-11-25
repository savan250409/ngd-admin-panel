<?php

use App\Support\Schemas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category + item tables for every content module.
 * Each category belongs to one app; each item belongs to one category.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Links every module's category to one or more apps (polymorphic many-to-many).
        Schema::create('app_category', function (Blueprint $t) {
            $t->id();
            $t->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $t->string('category_type');
            $t->unsignedBigInteger('category_id');
            $t->timestamps();

            $t->unique(['app_id', 'category_type', 'category_id'], 'app_category_unique');
            $t->index(['category_type', 'category_id']);
        });

        // ── Frame (gallery: one record per category, many frame images) ──────
        Schema::create('frame_categories', fn (Blueprint $t) => Schemas::category($t));
        Schema::create('frames', function (Blueprint $t) {
            Schemas::itemBase($t, 'frame_categories');
            $t->json('images');
            $t->boolean('status')->default(true);
            $t->timestamps();
            $t->unique('category_id');
        });

        // ── Template ─────────────────────────────────────────────────────────
        Schema::create('template_categories', fn (Blueprint $t) => Schemas::category($t));
        Schema::create('templates', function (Blueprint $t) {
            Schemas::itemBase($t, 'template_categories');
            $t->string('frame');
            $t->string('thumbnail');
            $t->unsignedInteger('count')->default(1);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        // ── Dynamic Frame (zip + json, like ASM013 posts) ───────────────────
        Schema::create('dynamic_frame_categories', fn (Blueprint $t) => Schemas::category($t));
        Schema::create('dynamic_frames', function (Blueprint $t) {
            Schemas::itemBase($t, 'dynamic_frame_categories');
            $t->string('aspect_ratio', 10);
            $t->string('zip');
            $t->string('thumbnail');
            $t->longText('json')->nullable();
            $t->boolean('is_paid')->default(false);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        // ── Baby Backdrop (one image set per category, like ASM003) ─────────
        Schema::create('baby_backdrop_categories', fn (Blueprint $t) => Schemas::category($t, orientation: true));
        Schema::create('baby_backdrops', function (Blueprint $t) {
            Schemas::itemBase($t, 'baby_backdrop_categories');
            $t->json('images');
            $t->boolean('status')->default(true);
            $t->timestamps();
            $t->unique('category_id');
        });

        // ── Video Template (Lottie zip, like ASM044) ────────────────────────
        Schema::create('video_template_categories', fn (Blueprint $t) => Schemas::category($t));
        Schema::create('video_templates', function (Blueprint $t) {
            Schemas::itemBase($t, 'video_template_categories');
            $t->string('title', 191);
            $t->string('thumbnail');
            $t->string('zip');
            $t->string('zip_original_name')->nullable();
            $t->string('extract_dir')->nullable();
            $t->string('video_html_path')->nullable();
            $t->longText('json')->nullable();
            $t->unsignedInteger('total_image_count')->nullable();
            $t->unsignedInteger('total_editable')->nullable();
            $t->string('width', 20)->nullable();
            $t->string('height', 20)->nullable();
            $t->boolean('is_paid')->default(false);
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        // ── AI Image ─────────────────────────────────────────────────────────
        Schema::create('ai_image_categories', fn (Blueprint $t) => Schemas::category($t));
        Schema::create('ai_images', function (Blueprint $t) {
            Schemas::itemBase($t, 'ai_image_categories');
            $t->string('image');
            $t->text('prompt');
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        // ── AI Video ─────────────────────────────────────────────────────────
        Schema::create('ai_video_categories', fn (Blueprint $t) => Schemas::category($t));
        Schema::create('ai_videos', function (Blueprint $t) {
            Schemas::itemBase($t, 'ai_video_categories');
            $t->string('video');
            $t->string('thumbnail')->nullable();
            $t->text('prompt');
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'ai_videos', 'ai_video_categories', 'ai_images', 'ai_image_categories',
            'video_templates', 'video_template_categories', 'baby_backdrops', 'baby_backdrop_categories',
            'dynamic_frames', 'dynamic_frame_categories', 'templates', 'template_categories',
            'frames', 'frame_categories', 'app_category',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
