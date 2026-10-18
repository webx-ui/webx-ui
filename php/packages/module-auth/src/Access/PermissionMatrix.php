<?php

declare(strict_types=1);

namespace WebxUi\Auth\Access;

use Illuminate\Contracts\Translation\Translator;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Admin\ModuleRegistry;

/**
 * The permissions a role may list, laid out for the form that ticks them: one row per module in
 * the order of the menu, each permission under the module that declares it first
 * (WEBX_UI_PERMISSIONS.md §11).
 *
 * A permission declared by two modules — rubrics and tags share `blog.taxonomy.manage` — is
 * listed once, under the first; a module left with nothing of its own has no row.
 *
 * The words are this package's: an action word for the usual ones (`view`, `manage`, …) and a
 * line of its own for a permission whose last word says too little (`roles.assign`,
 * `inbox.update`). A permission no word is known for shows its last word as it is.
 */
final class PermissionMatrix
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly AccessPolicy $policy,
        private readonly Translator $translator,
    ) {}

    /**
     * @return list<array{module: string, title: string, permissions: list<array{name: string, label: string, held: bool}>}>
     */
    public function for(mixed $user): array
    {
        $seen = [];
        $rows = [];

        foreach ($this->modules->all() as $module) {
            $permissions = [];

            foreach ($module->permissions() as $permission) {
                if (isset($seen[$permission])) {
                    continue;
                }

                $seen[$permission] = true;
                $permissions[] = [
                    'name' => $permission,
                    'label' => $this->label($permission),
                    // Rule 2 drawn: what the editor does not hold is shown, and cannot be ticked.
                    'held' => $this->policy->missing($user, [$permission]) === [],
                ];
            }

            if ($permissions !== []) {
                $rows[] = ['module' => $module->id(), 'title' => $module->title(), 'permissions' => $permissions];
            }
        }

        return $rows;
    }

    public function label(string $permission): string
    {
        $own = 'webx-auth::permissions.'.str_replace('.', '-', $permission);

        if ($this->translator->has($own)) {
            return (string) $this->translator->get($own);
        }

        $parts = explode('.', $permission);
        $action = (string) end($parts);
        $word = 'webx-auth::permissions.'.$action;

        return $this->translator->has($word) ? (string) $this->translator->get($word) : $action;
    }
}
