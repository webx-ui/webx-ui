<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use WebxUi\Auth\Access\DefaultRoles;

return new class extends Migration
{
    /**
     * The ready-made roles made before they remembered what they were offered: marked, then
     * topped up with the modules installed after them (WEBX_UI_PERMISSIONS.md §12). Missing
     * roles are not created here — one may be missing because the owner deleted it.
     *
     * Down leaves the permissions: by then they are the roles' like any others.
     */
    public function up(): void
    {
        $roles = app(DefaultRoles::class);
        $roles->mark();

        if (DB::table('cms_users')->exists()) {
            $roles->sync(create: false);
        }
    }

    public function down(): void {}
};
