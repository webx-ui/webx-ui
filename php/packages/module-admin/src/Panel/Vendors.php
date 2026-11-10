<?php

declare(strict_types=1);

namespace WebxUi\Admin\Panel;

/**
 * Which Composer packages are ours, and which of them are the site's own modules.
 *
 * Ours are the packages of the monorepo, `webx-ui/*`, and the modules a site keeps in its own
 * `modules/` folder, `site/*` (SITE_MODULES §5): one vendor for every site, since two sites are
 * two applications and have nothing to collide over. Every tool that tells ours from somebody
 * else's asks here — the agents' guide, the modules' styles, the snapshots, the debug bar,
 * `webx:modules` — so a site module is seen by all of them or by none, never by four of five.
 */
final class Vendors
{
    /** The monorepo's vendor. */
    public const string PACKAGED = 'webx-ui/';

    /** The vendor of every site module, installed from `modules/*` by a path repository. */
    public const string SITE = 'site/';

    /** @var list<string> */
    public const array OURS = [self::PACKAGED, self::SITE];

    /** A package of the monorepo or of the site. */
    public static function ours(string $package): bool
    {
        foreach (self::OURS as $vendor) {
            if (str_starts_with($package, $vendor)) {
                return true;
            }
        }

        return false;
    }

    /** A package that lives in the site's repository rather than in `vendor`. */
    public static function local(string $package): bool
    {
        return str_starts_with($package, self::SITE);
    }

    /** A module of the panel, `webx-ui/module-*` or `site/module-*`: ours, and called a module. */
    public static function module(string $package): bool
    {
        if (! self::ours($package)) {
            return false;
        }

        return str_starts_with(substr($package, (int) strpos($package, '/') + 1), 'module-');
    }
}
