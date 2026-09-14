<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;
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
    /** @var array<string, list<array<string, mixed>>> */
    private array $loaded = [];

    public function __construct(
        private readonly Cache $cache,
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
                ->get(['id', 'match_type', 'pattern', 'priority'])
                ->map(static fn (SeoUrl $rule): array => [
                    'id' => $rule->id,
                    'match_type' => $rule->match_type,
                    'pattern' => $rule->pattern,
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

    public function forget(): void
    {
        $this->loaded = [];

        if ($this->enabled()) {
            foreach (['urls', 'redirects'] as $what) {
                $this->cache->forget($this->key($what));
            }
        }
    }

    /**
     * @param  Closure(): list<array<string, mixed>>  $load
     * @return list<array<string, mixed>>
     */
    private function remember(string $what, Closure $load): array
    {
        if (isset($this->loaded[$what])) {
            return $this->loaded[$what];
        }

        try {
            $rows = $this->enabled()
                ? $this->cache->remember($this->key($what), $this->ttl(), $load)
                : $load();
        } catch (Throwable) {
            // The tables are not there yet: the package is installed and `migrate` has not
            // run, or a console command is doing something else entirely. A site with no SEO
            // is better than a site that will not answer.
            $rows = [];
        }

        return $this->loaded[$what] = is_array($rows) ? array_values($rows) : [];
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('webx-seo.cache.enabled', true);
    }

    private function ttl(): int
    {
        return (int) $this->config->get('webx-seo.cache.ttl', 86400);
    }

    private function key(string $what): string
    {
        return (string) $this->config->get('webx-seo.cache.key', 'webx.seo.rules').'.'.$what;
    }
}
