<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use WebxUi\Auth\Access\DefaultRoles;

return new class extends Migration
{
    /**
     * The ready-made roles for a site that was already running when they arrived
     * (WEBX_UI_PERMISSIONS.md §13, point 7). Only where somebody signs in already: a new site
     * gets them with its first administrator, from `webx:admin`, once its modules are known.
     *
     * Down removes nothing: by then a role may be held, renamed or edited, and is the site's.
     */
    public function up(): void
    {
        if (! DB::table('cms_users')->exists()) {
            return;
        }

        app(DefaultRoles::class)->seed();
    }

    public function down(): void {}
};
