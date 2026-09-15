<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Models\Route;

/**
 * Who answers this address.
 *
 * Reached through `Route::fallback()`, which is what keeps this from shadowing anything: a
 * fallback is by definition tried when nothing else matched, so `/sign-up` and `/account/orders`
 * win without a word being said about registration order (§8.1).
 *
 * The cost of an address is one indexed query plus loading the entity. There is no cache and
 * that is a decision, not an omission (§2, decision 13): invalidating half a million rows every
 * time a category is renamed costs more than the lookup it saves.
 */
class Resolver
{
    /**
     * A path of `a/b/c` asks about `a/b/c`, `a/b`, `a` and `''` — but a filter tail on a
     * catalogue category can be arbitrarily long, and the query has to stay bounded.
     *
     * The shortest candidates are the ones worth keeping: rows in the registry are shallow
     * (a page tree is two or three levels), and everything below that is a tail the handler
     * reads. The full path is always kept on top of them, because an exact hit must never be
     * the one that falls off the end.
     */
    private const CANDIDATES = 6;

    public function __construct(
        private readonly RouteTypes $types,
        private readonly Locales $locales,
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    public function resolve(Request $request): Response
    {
        // Decoded, because the registry stores what an editor typed: a Cyrillic slug is
        // `ремень` in the `path` column and `%D1%80%D0%B5...` on the wire.
        $incoming = rawurldecode($request->getPathInfo());

        [$locale, $prefix, $path] = $this->split(UrlNormaliser::key($incoming));

        // One spelling per address, decided before anything is looked up (§8.2). A trailing
        // slash, a capital letter or a doubled slash is the same address said differently, and
        // saying it one way keeps it out of the analytics twice and out of the index twice.
        $spelling = $this->spelling($prefix, $path);

        if ($spelling !== $incoming) {
            return $this->redirect($request, $spelling);
        }

        $resolution = $this->match($locale, $path);

        if ($resolution === null) {
            throw new NotFoundHttpException;
        }

        if ($resolution->route->isAlias()) {
            return $this->follow($request, $resolution);
        }

        return $this->hand($request, $resolution);
    }

    /**
     * The row that owns this path, and what was left over.
     *
     * Exact beats prefix (§2, decision 7), which is why the rows are tried longest first: a page
     * at `about/mission` is its own page, not a tail handed to `about`. A shorter row only wins
     * when its type says it takes tails, so the home page — an address of `''`, which is a
     * prefix of everything — cannot swallow the site.
     */
    public function match(string $locale, string $path): ?Resolution
    {
        /** @var list<Route> $rows */
        $rows = Route::query()
            ->where('locale', $locale)
            ->whereIn('path', $this->candidates($path))
            ->get()
            ->all();

        usort($rows, static fn (Route $a, Route $b): int => mb_strlen($b->path) <=> mb_strlen($a->path));

        foreach ($rows as $row) {
            $exact = $row->path === $path;

            if (! $exact && ! $this->acceptsTail($row)) {
                continue;
            }

            $tail = $exact ? '' : ltrim(mb_substr($path, mb_strlen($row->path)), '/');

            return new Resolution($row, $tail);
        }

        return null;
    }

    /**
     * The address of a row as a browser should see it: language prefix, encoding and all.
     *
     * Shared with `HasUrl::url()` through nothing at all, deliberately — the trait answers for
     * an entity it holds, this answers for a row the resolver already has, and neither has to
     * load the other's half.
     */
    public function publicUrl(Route $route, string $tail = ''): string
    {
        $prefix = $this->prefixFor($route->locale);
        $path = $tail === '' ? $route->path : $route->path.'/'.$tail;

        return URL::to($this->encode($this->spelling($prefix, UrlNormaliser::key($path))));
    }

    /**
     * An alias answers 301, and a tail behind it moves with it (§8.4).
     *
     * So a renamed category takes its pages of filters along, which is the difference between
     * one redirect and a few thousand dead addresses.
     */
    private function follow(Request $request, Resolution $resolution): RedirectResponse
    {
        $target = $resolution->route->target;

        // An alias with nothing on the other end is broken data, not a redirect loop waiting to
        // happen: `webx:routes:check` reports it, and until somebody fixes it the address is
        // simply gone.
        if (! $target instanceof Route) {
            throw new NotFoundHttpException;
        }

        return $this->redirect($request, $this->publicUrl($target, $resolution->tail));
    }

    /** A canonical row: find the type, load the entity, hand both to the handler (§8.5). */
    private function hand(Request $request, Resolution $resolution): Response
    {
        $type = $this->types->find($resolution->route->entity_type);

        // A row whose module is no longer installed. Nothing here can answer for it, and 404 is
        // the honest answer; `webx:routes:check` is where it gets noticed.
        if ($type === null || $type->handler === null) {
            throw new NotFoundHttpException;
        }

        $entity = $this->entity($type, $resolution->route->entity_id);

        if (! $entity instanceof Model) {
            throw new NotFoundHttpException;
        }

        $resolution = $resolution->with($type, $entity);
        $request->attributes->set(Resolution::ATTRIBUTE, $resolution);

        /** @var RouteHandler $handler */
        $handler = $this->container->make($type->handler);

        // Publication is the handler's business, not the registry's (§8.6): the entity carries
        // that state already, and a draft answers 404 to everybody but a preview.
        return $handler->handle($request, $entity, $resolution->tail);
    }

    private function entity(RouteType $type, int $id): ?Model
    {
        /** @var Model $model */
        $model = $this->container->make($type->model);

        return $model->newQuery()->find($id);
    }

    private function acceptsTail(Route $route): bool
    {
        $type = $this->types->find($route->entity_type);

        return $type instanceof RouteType && $type->acceptsTail;
    }

    /**
     * @return list<string>
     */
    private function candidates(string $path): array
    {
        $segments = $path === '' ? [] : explode('/', $path);
        $candidates = [''];

        for ($length = 1; $length <= count($segments); $length++) {
            $candidates[] = implode('/', array_slice($segments, 0, $length));
        }

        if (count($candidates) > self::CANDIDATES) {
            $candidates = array_slice($candidates, 0, self::CANDIDATES - 1);
            $candidates[] = $path;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Which language this request is in, and what is left of the path once its prefix is gone.
     *
     * The same segment `SetLocale` reads: a site that puts the language in the path declares its
     * routes inside `{locale?}`, so the registry never sees the prefix either. The comparison is
     * case-insensitive because the key is lower case and a language code is not — `pt-BR` has to
     * survive the round trip, and it is the configured spelling that goes back into the redirect.
     *
     * @return array{string, string, string} Locale, the prefix as it should be spelled, the path.
     */
    private function split(string $key): array
    {
        if ($this->strategy() !== 'prefix') {
            return [$this->locales->current(), '', $key];
        }

        [$first, $rest] = array_pad(explode('/', $key, 2), 2, '');

        foreach ($this->locales->codes() as $code) {
            if (mb_strtolower($code, 'UTF-8') === $first) {
                return [$code, $code, (string) $rest];
            }
        }

        return [$this->locales->current(), '', $key];
    }

    /** The prefix a language's own addresses carry; empty when the site does not use one. */
    private function prefixFor(string $locale): string
    {
        if ($this->strategy() !== 'prefix') {
            return '';
        }

        if ($locale === $this->locales->defaultCode() && ! (bool) $this->config->get('webx-localization.prefix_default', false)) {
            return '';
        }

        return $locale;
    }

    private function strategy(): string
    {
        return (string) $this->config->get('webx-localization.strategy', 'prefix');
    }

    /** The one spelling of an address, with a leading slash because that is what a request has. */
    private function spelling(string $prefix, string $path): string
    {
        $spelling = trim($prefix === '' ? $path : $prefix.'/'.$path, '/');

        return '/'.$spelling;
    }

    private function encode(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /** The query string survives a redirect: it is somebody's `?page=2`, not decoration. */
    private function redirect(Request $request, string $target): RedirectResponse
    {
        $query = (string) $request->getQueryString();

        return new RedirectResponse(URL::to($target).($query === '' ? '' : '?'.$query), 301);
    }
}
