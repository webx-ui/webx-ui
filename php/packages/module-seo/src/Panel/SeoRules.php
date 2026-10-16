<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;
use WebxUi\Cache\CacheGroups;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Models\SeoUrl;

/**
 * The active rules and redirects, compiled and kept.
 *
 * Every hit on the public side has to be compared against all of them, so what is cached is the
 * short version — what it takes to decide whether a rule matches, already in the order it will
 * be tried. The row itself is read only for the rule that won, once, and that is also what keeps
 * the cache free of the translations and pictures that would otherwise go stale in it.
 */
final class SeoRules
{
    public const GROUP = 'seo';

    public function __construct(
        private readonly CacheGroups $groups,
        private readonly Config $config,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function urls(): array
    {
        return $this->remember('urls', static fn (): array => UrlMatcher::ordered(
            SeoUrl::query()
                ->active()
                ->orderBy('id')
                ->get(['id', 'match_type', 'pattern', 'entity_type', 'entity_id', 'priority'])
                ->map(static fn (SeoUrl $rule): array => [
                    'id' => $rule->id,
                    'match_type' => $rule->match_type,
                    // A rule bound to an entity matches where the entity is now (§18.2): the list
                    // is thrown away whenever a row of the registry is saved, so a new slug is a
                    // new compiled pattern on the next request.
                    'pattern' => $rule->currentPattern(),
                    'priority' => $rule->priority,
                ])
                ->all(),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function redirects(): array
    {
        return $this->remember('redirects', static fn (): array => UrlMatcher::ordered(
            SeoRedirect::query()
                ->active()
                ->orderBy('id')
                ->get(['id', 'match_type', 'pattern', 'target', 'status'])
                ->map(static fn (SeoRedirect $redirect): array => [
                    'id' => $redirect->id,
                    'match_type' => $redirect->match_type,
                    'pattern' => $redirect->pattern,
                    'target' => $redirect->target,
                    'status' => $redirect->status,
                    'priority' => 0,
                ])
                ->all(),
        ));
    }

    /**
     * Throw the compiled lists away: the `seo` group moves (WEBX_UI_CACHE.md). A rule or a
     * redirect saved through its model moves the group on its own; this is for a write that
     * went round it.
     */
    public function forget(): void
    {
        $this->groups->bump(self::GROUP);
    }

    /**
     * The compiled list, in the `seo` cache group — and in `routes` too: a rule bound to an
     * entity is compiled with the entity's current address (§18.2), so a new slug in the
     * registry is a new compiled list. Read once a request, by the registry's own memo.
     *
     * @param  Closure(): list<array<string, mixed>>  $load
     * @return list<array<string, mixed>>
     */
    private function remember(string $what, Closure $load): array
    {
        try {
            $rows = $this->enabled()
                ? $this->groups->remember('seo.rules.'.$what, [self::GROUP, CacheGroups::ROUTES], $load, $this->ttl(), localized: false)
                : $load();
        } catch (Throwable) {
            // The tables are not there yet: the package is installed and `migrate` has not
            // run, or a console command is doing something else entirely. A site with no SEO
            // is better than a site that will not answer.
            $rows = [];
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('webx-seo.cache.enabled', true);
    }

    private function ttl(): int
    {
        return (int) $this->config->get('webx-seo.cache.ttl', 86400);
    }
}
