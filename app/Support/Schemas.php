<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;

/** Shared column sets used by every module's migration. */
class Schemas
{
    /**
     * A category belongs to MANY apps (via the polymorphic app_category pivot),
     * so the category tables no longer carry an app_id.
     */
    public static function category(Blueprint $t, bool $orientation = false): void
    {
        $t->id();
        $t->string('name', 150);
        $t->string('image')->nullable();
        if ($orientation) {
            $t->string('orientation', 20)->default('portrait');
        }
        $t->boolean('status')->default(true);
        // Manual order (set from the "Index" popup). 0 = unordered → shows first (newest).
        $t->unsignedInteger('sort_order')->default(0);
        $t->timestamps();

        $t->index('name');
        $t->index('sort_order');
    }

    public static function itemBase(Blueprint $t, string $categoryTable): void
    {
        $t->id();
        $t->foreignId('category_id')->constrained($categoryTable)->restrictOnDelete();
    }
}
