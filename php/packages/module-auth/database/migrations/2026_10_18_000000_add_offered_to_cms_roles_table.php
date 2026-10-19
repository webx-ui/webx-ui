<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a ready-made role has been offered so far (WEBX_UI_PERMISSIONS.md §12): a module
     * installed later is topped up into it, and only with what was never offered — a permission
     * the owner took away stays away. Null is a role of the site's own, never topped up.
     *
     * Dated before the migration that seeds the roles, so a site that gets both at once has the
     * column by the time the roles are made.
     */
    public function up(): void
    {
        Schema::table('cms_roles', function (Blueprint $table): void {
            $table->json('offered')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('cms_roles', function (Blueprint $table): void {
            $table->dropColumn('offered');
        });
    }
};
