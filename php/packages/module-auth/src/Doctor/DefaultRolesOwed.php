<?php

declare(strict_types=1);

namespace WebxUi\Auth\Doctor;

use Illuminate\Support\Facades\Schema;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Auth\Access\DefaultRoles;

/**
 * Ready-made roles behind the modules installed (WEBX_UI_PERMISSIONS.md §12): a module came after
 * them and nobody topped them up, so the editor or the developer does not see it.
 *
 * A warning, not a failure: nobody is locked out, somebody is just not let in yet. A missing
 * ready-made role says nothing — it may be missing because the owner deleted it.
 */
final class DefaultRolesOwed implements Check
{
    private const string SUBJECT = 'Ready-made roles';

    public function __construct(private readonly DefaultRoles $roles) {}

    public function run(): array
    {
        // Before the migration that brings the column, the migrations check has the say.
        if (! Schema::hasTable('cms_roles') || ! Schema::hasColumn('cms_roles', 'offered')) {
            return [];
        }

        $owing = $this->roles->owing();

        if ($owing === []) {
            return [Diagnosis::ok(self::SUBJECT, 'they hold what they are owed of the modules installed')];
        }

        $lines = array_map(
            static fn (string $slug, array $permissions): string => $slug.' ← '.implode(', ', $permissions),
            array_keys($owing),
            $owing,
        );

        return [Diagnosis::warn(
            self::SUBJECT,
            'not yet offered the permissions of modules installed after them: '.implode('; ', $lines)
            .' — run php artisan webx:roles:defaults --top-up (webx:boot does it on every deploy).',
        )];
    }
}
