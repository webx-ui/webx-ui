<?php

declare(strict_types=1);

namespace WebxUi\Auth\Access;

use WebxUi\Admin\Access\AccessPolicy;
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
 * Run by the first `webx:admin` (and so by `webx:setup`), by the migration that brought it to a
 * site already running, and by `webx:roles:defaults`.
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

    public function __construct(private readonly AccessPolicy $policy) {}

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
        $declared = $this->policy->declared();
        $created = [];

        foreach ($this->definitions() as $slug => $permissions) {
            if (Role::query()->where('slug', $slug)->exists()) {
                continue;
            }

            $granted = array_values(array_unique(array_filter(
                $permissions,
                static fn (string $permission): bool => in_array($permission, $declared, true) && ! self::reserved($permission),
            )));

            if ($granted === []) {
                continue;
            }

            Role::query()->create([
                'slug' => $slug,
                'name' => (string) __('webx-auth::roles.default-'.$slug),
                'permissions' => $granted,
            ]);

            $created[] = $slug;
        }

        return $created;
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
