<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor\Checks;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Cache\CacheGroups;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * Whether what the site caches outlives the request (WEBX_UI_CACHE.md §4).
 *
 * The `array` store is Laravel's default in testing and a container's by accident: every page
 * then builds the menu, the settings and the addresses again, and nothing says so — the site
 * works, slowly. The `database` store works everywhere but keeps what nobody reads; the frame
 * sweeps it hourly, which needs the scheduler to run.
 */
final class CacheStore implements Check
{
    public function __construct(
        private readonly Config $config,
        private readonly Application $app,
        private readonly CacheGroups $groups,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $store = $this->config->get('cache.default');
        $store = is_string($store) ? $store : 'array';
        $driver = $this->config->get("cache.stores.{$store}.driver");
        $driver = is_string($driver) ? $driver : $store;
        $groups = count($this->groups->ids());
        $declared = $groups === 0 ? 'no cache group declared' : "{$groups} cache group".($groups === 1 ? '' : 's');

        if (! $this->groups->enabled()) {
            return [Diagnosis::warn('Cache', "{$store} ({$driver}) — the versioned cache is off (`webx-admin.cache.enabled`): every page builds everything again. On for a site anybody visits.")];
        }

        if ($driver === 'array' || $driver === 'null') {
            $detail = "{$store} ({$driver}) — nothing survives the request: every page builds the menu, the settings and the addresses again. Set CACHE_STORE to `file`, `database` or `redis`.";

            return [$this->app->environment('production')
                ? Diagnosis::warn('Cache', $detail)
                : Diagnosis::ok('Cache', "{$store} ({$driver}) — fine for development; in production ".lcfirst(substr($detail, strpos($detail, 'nothing'))))];
        }

        if ($driver === 'database') {
            return [Diagnosis::ok('Cache', "{$store} (database) — {$declared}; expired rows are swept hourly by `webx:cache:prune`, which needs `schedule:run` behind the system cron.")];
        }

        return [Diagnosis::ok('Cache', "{$store} ({$driver}) — {$declared}.")];
    }
}
