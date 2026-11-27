<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A package name is no longer globally unique — it is unique PER PLATFORM.
     * So the same package may be registered once for Android and once for iOS,
     * but never twice for the same platform.
     */
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $t) {
            $t->dropUnique(['package_name']);          // apps_package_name_unique
            $t->unique(['package_name', 'platform']);  // apps_package_name_platform_unique
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $t) {
            $t->dropUnique(['package_name', 'platform']);
            $t->unique(['package_name']);
        });
    }
};
