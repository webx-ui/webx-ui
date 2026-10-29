<?php

declare(strict_types=1);

namespace WebxUi\Auth\Access;

use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Models\Role;

/**
 * The ready-made roles (WEBX_UI_PERMISSIONS.md §12): a starting point a site edits like any other
 * role, never a set it is held to.
 *
 * Only what is missing is created — a role whose code is taken stays exactly as the site left
 * it — and only with the permissions of the modules installed; a role left with none is not
 * created at all. None of them holds `admins.*`, `roles.*`, `tasks.*` or `debug.view`: who runs
 * the panel and its queue is the owner's to hand out, by hand.
 *
 * A module installed later is topped up into the roles made before it ({@see sync()}). What a
 * role has been offered is remembered in `cms_roles.offered`, and only what was never offered is
 * added: a permission the owner took away has been offered, so it stays away. A role without
 * that list is the site's own and is never touched.
 *
 * Run by the first `webx:admin` (and so by `webx:setup`), by the migrations that brought it to a
 * site already running, by `webx:roles:defaults` — which `webx:boot` and `webx:module:add` run —
 * and by the MCP tool `admins_sync_default_roles`.
 */
final class DefaultRoles
{
    /** The content sections, each with its own permissions and those of its categories. */
    private const CONTENT = [
        'blog.articles.view', 'blog.articles.manage', 'blog.taxonomy.manage', 'blog.authors.manage',
        'events.view', 'events.manage', 'events.categories.manage',
        'faq.view', 'faq.manage', 'faq.categories.manage',
        'press.view', 'press.manage',
        'recipes.view', 'recipes.manage', 'recipes.categories.manage',
        'reviews.view', 'reviews.manage', 'reviews.categories.manage',
        'services.view', 'services.manage', 'services.categories.manage',
        'tariffs.view', 'tariffs.manage', 'tariffs.groups.manage',
        'team.view', 'team.manage',
        'vacancies.view', 'vacancies.manage', 'vacancies.categories.manage',
        'banners.view', 'banners.manage',
    ];

    /** Never in a ready-made role, whatever a module declares. */
    private const NEVER = ['admins.', 'roles.', 'tasks.', 'debug.'];

    public function __construct(
        private readonly AccessPolicy $policy,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * The roles, by code, with every permission they would hold on a site that has everything.
     *
     * @return array<string, list<string>>
     */
    public function definitions(): array
    {
        $contentViews = array_values(array_filter(self::CONTENT, static fn (string $p): bool => str_ends_with($p, '.view')));

        return [
            'editor' => [
                ...self::CONTENT,
                'pages.view', 'pages.manage', 'blocks.view', 'menu.view', 'menu.manage',
                'media.view', 'media.upload', 'media.manage', 'seo.view', 'settings.view',
            ],
            'seo' => [
                'seo.view', 'seo.manage', 'audit.view', 'audit.run', 'pages.view', 'blocks.view',
                'catalog.view', 'media.view', ...$contentViews,
            ],
            'leads' => ['inbox.view', 'inbox.update', 'notifications.view'],
            'catalog' => ['catalog.view', 'catalog.manage', 'search-index.view', 'media.view', 'media.upload'],
            // Works the orders; the money given back and the statuses stay with the owner (decision
            // 33 of the orders spec). `users.view` only where the visitors are installed — and only
            // beside the orders: a site with the visitors alone has no order manager to make.
            ...($this->policy->isDeclared('orders.view') ? ['orders' => ['orders.view', 'orders.manage', 'users.view']] : []),
            'developer' => [
                'blocks.view', 'blocks.manage', 'blocks.regions', 'snippets.manage', 'menu.view',
                'menu.manage', 'settings.view', 'settings.manage', 'cache.view', 'cache.reset',
                'audit.view', 'audit.run', 'audit.manage', 'search-index.view',
                'search-index.manage', 'notifications.view', 'notifications.manage', 'media.view',
                'media.upload',
            ],
            // Every `view` there is, which is why it is worked out rather than written down.
            'viewer' => array_values(array_filter(
                $this->policy->declared(),
                static fn (string $permission): bool => str_ends_with($permission, '.view'),
            )),
        ];
    }

    /**
     * Create the roles that are missing.
     *
     * @return list<string> the codes of the roles created
     */
    public function seed(): array
    {
        $created = $this->missing();

        foreach ($created as $slug => $permissions) {
            $this->create($slug, $permissions);
        }

        return array_keys($created);
    }

    /**
     * Top up the ready-made roles there are and, unless told not to, create the missing ones.
     *
     * Creating is what a person asks for. A deploy only tops up: a ready-made role the owner
     * deleted is missing too, and a deploy that brought it back every time would be overruling
     * the owner on a schedule.
     *
     * @param  list<string>|null  $only  just these roles, when not all of them
     * @return array{created: array<string, list<string>>, added: array<string, list<string>>} what was done, or would be on a dry run
     */
    public function sync(bool $dryRun = false, bool $create = true, ?array $only = null): array
    {
        $chosen = static fn (string $slug): bool => $only === null || in_array($slug, $only, true);
        $created = $create ? array_filter($this->missing(), $chosen, ARRAY_FILTER_USE_KEY) : [];
        $added = [];

        foreach ($this->readyMade() as $role) {
            if (! $chosen($role->slug)) {
                continue;
            }

            [$adding, $offering] = $this->owed($role);

            if ($adding !== []) {
                $added[$role->slug] = $adding;
            }

            if ($dryRun || $offering === []) {
                continue;
            }

            $role->offered = [...($role->offered ?? []), ...$offering];

            if ($adding === []) {
                // Offered and already held: nothing anybody gains, so nothing for the journal.
                $role->saveQuietly();

                continue;
            }

            $role->permissions = [...$role->permissions, ...$adding];
            $role->save();
        }

        if (! $dryRun) {
            foreach ($created as $slug => $permissions) {
                $this->create($slug, $permissions);
            }
        }

        return ['created' => $created, 'added' => $added];
    }

    /**
     * What the ready-made roles there are would gain from a sync, by role — nothing written.
     *
     * @return array<string, list<string>>
     */
    public function owing(): array
    {
        return $this->sync(dryRun: true, create: false)['added'];
    }

    /**
     * Mark the ready-made roles made before `offered` existed, once.
     *
     * A role counts as ready-made if its code is one of the definitions and it holds nothing the
     * definition does not: a role of the site's own that happens to be called `editor` has
     * something else in it, almost always, and is left as the site's. What it was offered is what
     * it holds now, plus every permission of its definition from a module it holds anything of —
     * that module was there when the role was made, so what of it is missing was taken away.
     * The modules it holds nothing of came later, and a sync tops them up.
     *
     * @return list<string> the codes of the roles marked
     */
    public function mark(): array
    {
        $definitions = $this->definitions();
        $owners = $this->owners();
        $marked = [];

        foreach (Role::query()->whereNull('offered')->whereIn('slug', array_keys($definitions))->get() as $role) {
            $held = $role->permissions ?? [];
            $definition = $definitions[$role->slug];

            if (array_diff($held, $definition) !== []) {
                continue;
            }

            $present = array_unique(array_filter(array_map(static fn (string $p): ?string => $owners[$p] ?? null, $held)));
            $offered = array_filter($definition, static fn (string $p): bool => in_array($owners[$p] ?? null, $present, true));

            $role->offered = array_values(array_unique([...$held, ...$offered]));
            $role->saveQuietly();
            $marked[] = $role->slug;
        }

        return $marked;
    }

    /**
     * The roles that are missing, each with what it would be made of; a role with nothing is not.
     *
     * @return array<string, list<string>>
     */
    private function missing(): array
    {
        $missing = [];

        foreach ($this->definitions() as $slug => $permissions) {
            if (Role::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $granted = $this->grantable($permissions);

            if ($granted !== []) {
                $missing[$slug] = $granted;
            }
        }

        return $missing;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function create(string $slug, array $permissions): void
    {
        Role::query()->create([
            'slug' => $slug,
            'name' => (string) __('webx-auth::roles.default-'.$slug),
            'permissions' => $permissions,
            'offered' => $permissions,
        ]);
    }

    /**
     * The roles a sync may top up: made here, by a code still defined.
     *
     * @return list<Role>
     */
    private function readyMade(): array
    {
        return Role::query()
            ->whereNotNull('offered')
            ->whereIn('slug', array_keys($this->definitions()))
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * What a role is owed: the permissions never offered to it that it does not hold yet, and
     * every permission never offered to it — the ones it already holds are recorded as offered
     * too, so taking them away later is a decision that sticks.
     *
     * @return array{list<string>, list<string>}
     */
    private function owed(Role $role): array
    {
        $offering = array_values(array_diff(
            $this->grantable($this->definitions()[$role->slug] ?? []),
            $role->offered ?? [],
        ));

        return [array_values(array_diff($offering, $role->permissions ?? [])), $offering];
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string> those an installed module declares and a ready-made role may hold
     */
    private function grantable(array $permissions): array
    {
        $declared = $this->policy->declared();

        return array_values(array_unique(array_filter(
            $permissions,
            static fn (string $permission): bool => in_array($permission, $declared, true) && ! self::reserved($permission),
        )));
    }

    /**
     * Which module declares each permission.
     *
     * @return array<string, string>
     */
    private function owners(): array
    {
        $owners = [];

        foreach ($this->modules->all() as $module) {
            foreach ($module->permissions() as $permission) {
                $owners[$permission] ??= $module->id();
            }
        }

        return $owners;
    }

    private static function reserved(string $permission): bool
    {
        foreach (self::NEVER as $prefix) {
            if (str_starts_with($permission, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
