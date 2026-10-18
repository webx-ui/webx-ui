<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Assigning roles used to come with `admins.manage` — the form of an administrator had the
     * roles on it — and is a permission of its own now, `roles.assign` (WEBX_UI_PERMISSIONS.md
     * §13, point 2). A role that could do it yesterday can do it today; nobody gets
     * `roles.manage`, since roles could not be edited from the panel before.
     *
     * Down leaves the permission where it is: it was added to roles that already did the work
     * it names, and a role somebody gave it by hand since is indistinguishable from one this
     * gave it to.
     */
    public function up(): void
    {
        $roles = DB::table('cms_roles')->select(['id', 'permissions'])->get();

        foreach ($roles as $role) {
            /** @var list<string> $permissions */
            $permissions = json_decode((string) $role->permissions, true) ?: [];

            if (! in_array('admins.manage', $permissions, true) || in_array('roles.assign', $permissions, true)) {
                continue;
            }

            $permissions[] = 'roles.assign';

            DB::table('cms_roles')
                ->where('id', $role->id)
                ->update(['permissions' => json_encode(array_values($permissions))]);
        }
    }

    public function down(): void {}
};
