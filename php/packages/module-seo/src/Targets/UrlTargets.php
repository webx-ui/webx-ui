<?php

declare(strict_types=1);

namespace WebxUi\Seo\Targets;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Contracts\Visible;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteType;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\SiteUrl;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Sitemap\SitemapRoutes;

/**
 * Turning an address somebody typed into a {@see UrlTarget}, and a target back into an address.
 *
 * On the way in (§18.2): the host goes, and a foreign one is refused; an address that a redirect
 * catches is replaced by where it leads; then the registry is asked — a canonical row gives its
 * entity, an alias gives the entity of the row it leads to, because the address in a brief may
 * well be older than the last rename. Nothing found — the path stays as it is.
 *
 * On the way out, a bound target is wherever its entity is now.
 */
final class UrlTargets
{
    /** A chain of redirects longer than this is a loop somebody has to fix, not an address. */
    private const HOPS = 5;

    public function __construct(
        private readonly Resolver $resolver,
        private readonly RouteTypes $types,
        private readonly Locales $locales,
        private readonly Config $config,
        private readonly Container $container,
        private readonly SeoRules $rules,
        private readonly UrlMatcher $matcher,
        private readonly SitemapRoutes $sitemapRoutes,
        private readonly SiteUrl $siteUrl,
    ) {}

    /**
     * @param  string|null  $locale  the language of an address that carries no prefix; the site's
     *                               default when null, because this is asked from the panel
     *
     * @throws ForeignHost
     */
    public function resolve(string $url, ?string $locale = null): Binding
    {
        $address = $this->local($url);
        $redirectedFrom = null;

        for ($hop = 0; $hop < self::HOPS; $hop++) {
            $next = $this->redirectOf($address);

            if ($next === null || $next === $address) {
                break;
            }

            $redirectedFrom ??= $address;
            $address = $this->local($next);
        }

        [$language, $path] = $this->split($address, $locale);

        // An address with a query is a page of filters or pagination, not the entity itself.
        if (! str_contains($path, '?')) {
            $resolution = $this->resolver->lookup($address, $language);

            if ($resolution !== null && $resolution->tail === '') {
                $route = $resolution->route;
                $alias = $route->isAlias();

                if ($alias) {
                    $route = $route->target;
                }

                if ($route instanceof Route) {
                    return new Binding(
                        new UrlTarget($route->locale, '/'.$route->path, $route->entity_type, $route->entity_id),
                        $redirectedFrom,
                        $alias,
                    );
                }
            }
        }

        return new Binding(new UrlTarget($language, $path), $redirectedFrom);
    }

    /**
     * The address a visitor should be sent to, language prefix included, without a host — or
     * null for a bound target whose entity has no address in that language any more.
     */
    public function href(UrlTarget $target): ?string
    {
        $path = $target->isBound() ? $this->canonicalPath($target) : $target->path;

        return $path === null ? null : $this->withPrefix($target->locale, $path);
    }

    /** {@see href()}, or the last known address when the entity is gone — for a screen to show. */
    public function address(UrlTarget $target): string
    {
        return $this->href($target) ?? $this->savedAddress($target);
    }

    /** The address as it was saved, language prefix included. */
    public function savedAddress(UrlTarget $target): string
    {
        return $this->withPrefix($target->locale, $target->path);
    }

    /**
     * A link nobody should print (§18.4): the entity is gone or hidden, or an address with no
     * entity that neither the registry nor a named route of the sitemap answers.
     */
    public function isBroken(UrlTarget $target): bool
    {
        if ($target->isBound()) {
            return ! $this->entityIsLive($target);
        }

        $address = $this->withPrefix($target->locale, $target->path);
        [$path] = explode('?', $address, 2);

        if ($this->resolver->lookup($path, $target->locale) !== null) {
            return false;
        }

        foreach ($this->sitemapRoutes->all() as $name) {
            if ($this->namedRouteAt($name, $path)) {
                return false;
            }
        }

        return true;
    }

    /** The current spelling of an exact rule's address, for a rule bound to an entity. */
    public function ruleAddress(string $pattern, ?string $entityType, ?int $entityId): string
    {
        if ($entityType === null || $entityId === null) {
            return $pattern;
        }

        [$locale] = $this->split($pattern, null);

        return $this->href(new UrlTarget($locale, $pattern, $entityType, $entityId)) ?? $pattern;
    }

    /**
     * The language and the prefix-less path of an address as a request carries it.
     *
     * @return array{string, string}
     */
    public function split(string $address, ?string $locale): array
    {
        $address = UrlNormaliser::normalise($address);
        [$path, $query] = array_pad(explode('?', $address, 2), 2, null);
        $fallback = $locale !== null && $this->locales->has($locale) ? $locale : $this->locales->defaultCode();

        $language = $fallback;

        if ($this->strategy() === 'prefix') {
            [$first, $rest] = array_pad(explode('/', ltrim((string) $path, '/'), 2), 2, '');

            foreach ($this->locales->codes() as $code) {
                if (mb_strtolower($code, 'UTF-8') === mb_strtolower($first, 'UTF-8')) {
                    $language = $code;
                    $path = '/'.$rest;

                    break;
                }
            }
        }

        $path = UrlNormaliser::normalise((string) $path);

        return [$language, $query === null || $query === '' ? $path : $path.'?'.$query];
    }

    /** The donor of the request being answered, as a language and a prefix-less path. */
    public function current(Request $request): UrlTarget
    {
        [$locale, $path] = $this->split(rawurldecode($request->getRequestUri()), $this->locales->current());

        return new UrlTarget($locale, $path);
    }

    /**
     * The address with the scheme and host taken off, refusing one on another host.
     *
     * @throws ForeignHost
     */
    private function local(string $url): string
    {
        $url = trim($url);
        $absolute = preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1 || str_starts_with($url, '//');

        if ($absolute) {
            $host = parse_url(str_starts_with($url, '//') ? 'http:'.$url : $url, PHP_URL_HOST);

            if (! is_string($host) || ! $this->isOwnHost($host)) {
                throw new ForeignHost($url);
            }

            $parts = (array) parse_url(str_starts_with($url, '//') ? 'http:'.$url : $url);
            $url = (is_string($parts['path'] ?? null) ? $parts['path'] : '/')
                .(is_string($parts['query'] ?? null) ? '?'.$parts['query'] : '');
        }

        return UrlNormaliser::normalise($url);
    }

    private function isOwnHost(string $host): bool
    {
        $own = [parse_url((string) $this->config->get('app.url'), PHP_URL_HOST)];

        if ($this->container->bound('request')) {
            $request = $this->container->make('request');
            $own[] = $request instanceof Request ? $request->getHost() : null;
        }

        $bare = static fn (mixed $name): string => is_string($name) ? preg_replace('/^www\./', '', mb_strtolower($name)) ?? '' : '';

        return in_array($bare($host), array_filter(array_map($bare, $own)), true);
    }

    /** Where an active redirect sends this address, or null. */
    private function redirectOf(string $address): ?string
    {
        $row = $this->matcher->match($address, $this->rules->redirects());

        if ($row === null) {
            return null;
        }

        $matchType = is_string($row['match_type'] ?? null) ? $row['match_type'] : UrlMatcher::EXACT;
        $pattern = is_string($row['pattern'] ?? null) ? $row['pattern'] : '';
        $target = is_string($row['target'] ?? null) ? $row['target'] : '';

        return $target === '' ? null : UrlMatcher::target($matchType, $pattern, $target, $address);
    }

    private function canonicalPath(UrlTarget $target): ?string
    {
        $path = Route::query()
            ->canonical()
            ->where('entity_type', $target->entityType)
            ->where('entity_id', $target->entityId)
            ->where('locale', $target->locale)
            ->value('path');

        return is_string($path) ? '/'.$path : null;
    }

    private function entityIsLive(UrlTarget $target): bool
    {
        if ($this->canonicalPath($target) === null) {
            return false;
        }

        $type = $this->types->find((string) $target->entityType);

        if (! $type instanceof RouteType) {
            return false;
        }

        try {
            /** @var Model $model */
            $model = $this->container->make($type->model);
            $entity = $model->newQuery()->find($target->entityId);
        } catch (Throwable) {
            return false;
        }

        if (! $entity instanceof Model) {
            return false;
        }

        return ! $entity instanceof Visible || $entity->isVisible($target->locale);
    }

    private function namedRouteAt(string $name, string $path): bool
    {
        try {
            $url = route($name, [], false);
        } catch (Throwable) {
            return false;
        }

        return UrlNormaliser::normalise($url) === UrlNormaliser::normalise($path);
    }

    /** The prefix the registry writes addresses with — `SiteUrl` is its one spelling. */
    private function withPrefix(string $locale, string $path): string
    {
        $prefix = $this->siteUrl->prefix($locale);
        $path = ltrim($path, '/');

        if ($prefix === '') {
            return '/'.$path;
        }

        return '/'.$prefix.($path === '' || str_starts_with($path, '?') ? $path : '/'.$path);
    }

    private function strategy(): string
    {
        return (string) $this->config->get('webx-localization.strategy', 'prefix');
    }
}
