<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The module used to call itself `users`, and its permissions with it: `users.view`,
     * `users.manage`, `users.audit`. It is the administrators, and `users` is kept for the
     * site's own users, which become a module of their own once a site needs sign-up.
     *
     * Permissions live inside each role as a list, so the rename is a rewrite of that list —
     * a role granted `users.manage` yesterday still grants managing administrators today.
     */
    public function up(): void
    {
        $this->rewrite('users.', 'admins.');
    }

    public function down(): void
    {
        $this->rewrite('admins.', 'users.');
    }

    private function rewrite(string $from, string $to): void
    {
        $roles = DB::table('cms_roles')->select(['id', 'permissions'])->get();

        foreach ($roles as $role) {
            /** @var list<string> $permissions */
            $permissions = json_decode((string) $role->permissions, true) ?: [];

            $renamed = array_map(
                static fn (string $permission): string => str_starts_with($permission, $from)
                    ? $to.substr($permission, strlen($from))
                    : $permission,
                $permissions,
            );

            if ($renamed !== $permissions) {
                DB::table('cms_roles')
                    ->where('id', $role->id)
                    ->update(['permissions' => json_encode(array_values($renamed))]);
            }
        }
    }
};
