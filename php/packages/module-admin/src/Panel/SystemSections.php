<?php

declare(strict_types=1);

namespace WebxUi\Admin\Panel;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * The captions inside «System»: what the site is built of, how it is found and checked, and who
 * may get in. The settings stay above them, uncaptioned — the one entry everybody looks for.
 *
 * Added to the config at boot rather than shipped as a default: a site that published
 * `webx-admin.php` has its own copy of the groups, older than the captions, and a section the
 * group does not declare is ignored. A site that declared its own sections keeps them; these are
 * added beside.
 */
final class SystemSections
{
    public const GROUP = 'system';

    /** Files, blocks, regions, menus. */
    public const SITE = 'site';

    /** SEO, the audit, a search index. */
    public const SEARCH = 'search';

    /** Administrators and agents. */
    public const ACCESS = 'access';

    public static function register(Config $config): void
    {
        /** @var array<string, mixed> $groups */
        $groups = (array) $config->get('webx-admin.groups', []);
        $group = $groups[self::GROUP] ?? ['title' => 'webx-admin::nav.system', 'icon' => 'gear', 'order' => 900];

        if (! is_array($group)) {
            return;
        }

        $sections = is_array($group['sections'] ?? null) ? $group['sections'] : [];
        $sections[self::SITE] ??= ['title' => 'webx-admin::nav.system-site', 'order' => 100];
        $sections[self::SEARCH] ??= ['title' => 'webx-admin::nav.system-search', 'order' => 200];
        $sections[self::ACCESS] ??= ['title' => 'webx-admin::nav.system-access', 'order' => 300];
        $group['sections'] = $sections;

        $config->set('webx-admin.groups', [...$groups, self::GROUP => $group]);
    }
}
