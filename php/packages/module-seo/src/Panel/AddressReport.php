<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;
use WebxUi\Localization\Locales;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\RouteHandler;
use WebxUi\Routing\RouteTypes;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Http\Resources\SeoRedirectResource;
use WebxUi\Seo\Http\Resources\SeoUrlResource;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Rendering\Alternates;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\SocialTags;
use WebxUi\Seo\Sitemap\Sitemap;

/**
 * "Why does this page have the wrong title?" — the answer, in one piece.
 *
 * The panel's test-url and the MCP `test_url` both return this, so the two cannot drift: they
 * once did, and the agent was told nothing about a redirect the panel showed plainly.
 */
final class AddressReport
{
    public function __construct(
        private readonly Seo $seo,
        private readonly UrlRuleSource $rules,
        private readonly RedirectFinder $redirects,
        private readonly Resolver $resolver,
        private readonly Sitemap $sitemap,
        private readonly AddressSubject $subjects,
        private readonly SocialTags $social,
        private readonly Alternates $alternates,
        private readonly Locales $locales,
        private readonly RouteTypes $types,
        private readonly Router $router,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(string $url, ?string $locale = null): array
    {
        $url = UrlNormaliser::normalise($url);
        $matched = $this->rules->matching($url);
        // The entity the public page would be about, in the language of its row, so the chain
        // has the page's own card in it — the public page has it, and the answer is about that.
        [$subject, $rowLocale] = $this->subjects->at($url, $locale);
        $seoLocale = $locale ?? $rowLocale;
        $data = $this->seo->for($url, $subject, $seoLocale);
        $tagLocale = $seoLocale ?? $this->locales->current();
        $redirect = $this->redirect($url);
        $route = $this->route($url, $locale);

        return [
            'url' => $url,
            // What a visitor gets, in one line: a redirect (a written one, an old address, or a
            // handler that only sends the reader on), a page, or a 404. Everything below is what
            // the head would say *if* a page were printed here — for a 404 or a redirect, nobody
            // ever reads it, and an answer that did not say so read as a page with full SEO.
            'answers' => $this->answers($url, $redirect, $route, $subject),
            // Said first because it happens first: an address that redirects never gets as far
            // as the rules below, and that is the answer often enough to be worth the query.
            'redirect' => $redirect,
            // What the registry has at this address, so the answer to "can I redirect this away"
            // and to "why is this address answering at all" comes from the same call.
            'route' => $route,
            'matched' => $matched === null ? null : (new SeoUrlResource($matched))->resolve(),
            'chain' => $this->seo->chain($url, $subject, $seoLocale),
            'seo' => $data->toArray(),
            // Every JSON-LD block the head prints: the sources' above, plus the trail and the
            // entity's own (Recipe, Event…). Not what a template pushes while it renders.
            'json_ld' => $this->seo->printedJsonLd($data, $subject, $tagLocale),
            // The og:*, article:* and twitter:* lines exactly as the <head> prints them — the
            // picture after the SVG fall-through, the type the entity gave.
            'social' => array_map(
                static fn (array $tag): array => ['key' => $tag['key'], 'content' => $tag['content']],
                $this->social->for($data, $subject, $tagLocale, $this->alternates->for($url, $subject, $tagLocale, $data)),
            ),
            // The first question when a page is missing from a search engine (§17.6).
            'sitemap' => $this->sitemap->verdict($url, $locale),
        ];
    }

    /**
     * What the site does with a request for this address, as a visitor would find out.
     *
     * @param  array<string, mixed>|null  $redirect
     * @param  array<string, mixed>|null  $route
     * @return array<string, mixed>
     */
    private function answers(string $url, ?array $redirect, ?array $route, ?object $subject): array
    {
        if ($redirect !== null) {
            return ['kind' => 'redirect', 'by' => 'redirect', 'status' => $redirect['status'] ?? 301, 'leads_to' => $redirect['leads_to'] ?? null];
        }

        if ($route !== null && $route['target'] !== null) {
            return ['kind' => 'redirect', 'by' => 'alias', 'status' => 301, 'leads_to' => $route['target']];
        }

        if ($route !== null) {
            $type = $this->types->find((string) $route['entity_type']);

            if ($type !== null && ! $type->servesPages()) {
                return ['kind' => 'redirect', 'by' => 'handler'] + $this->handlerRedirect($url, $type->handler, $subject);
            }

            return ['kind' => 'page', 'by' => 'route'];
        }

        if ($this->servedByTheApp($url)) {
            return ['kind' => 'page', 'by' => 'app'];
        }

        return [
            'kind' => 'not-found',
            'status' => 404,
            'note' => 'No page lives at this address: the site answers 404. The SEO below is what the defaults would say, not what anybody reads.',
        ];
    }

    /**
     * Where a handler that is not a page sends this address, asked of the handler itself — the
     * only one who knows (an event's booking link, a category's first item).
     *
     * @return array{handler: string|null, status: int|null, leads_to: string|null}
     */
    private function handlerRedirect(string $url, ?string $handler, ?object $subject): array
    {
        if ($handler === null || $subject === null) {
            return ['handler' => $handler, 'status' => null, 'leads_to' => null];
        }

        try {
            $instance = app($handler);
            $response = $instance instanceof RouteHandler ? $instance->handle(Request::create($url), $subject, '') : null;
        } catch (Throwable) {
            return ['handler' => $handler, 'status' => null, 'leads_to' => null];
        }

        // The class the site bound, not the module's: that is the one to go and read.
        $handler = is_object($instance) ? $instance::class : $handler;

        return $response instanceof RedirectResponse
            ? ['handler' => $handler, 'status' => $response->getStatusCode(), 'leads_to' => $response->getTargetUrl()]
            : ['handler' => $handler, 'status' => $response?->getStatusCode(), 'leads_to' => null];
    }

    /** A route of the application's own (not the registry's fallback) that takes this address. */
    private function servedByTheApp(string $url): bool
    {
        try {
            return ! $this->router->getRoutes()->match(Request::create($url))->isFallback;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The redirect a visitor would get, with `leads_to` — where it sends this address, `$1` put
     * back for a mask or a regex.
     *
     * @return array<string, mixed>|null
     */
    private function redirect(string $url): ?array
    {
        $found = $this->redirects->find($url);

        if ($found === null) {
            return null;
        }

        [$row, $target] = $found;
        $id = $row['id'] ?? null;
        $redirect = is_numeric($id) ? SeoRedirect::query()->find((int) $id) : null;

        return $redirect === null ? null : (new SeoRedirectResource($redirect))->resolve() + ['leads_to' => $target];
    }

    /**
     * Whatever the address registry holds here — a live page, or the trail of one that moved.
     *
     * A manual rule is older than live content (the spec's decision 6) and nothing here changes
     * that: the panel says the address is taken and lets the rule be written anyway. Saying it
     * is the point, because an address that quietly stops being reachable is found by a reader,
     * not by whoever wrote the rule.
     *
     * @return array<string, mixed>|null
     */
    private function route(string $url, ?string $locale): ?array
    {
        $resolution = $this->resolver->lookup($url, $locale);

        if ($resolution === null) {
            return null;
        }

        $route = $resolution->route;

        $target = $route->isAlias() ? $route->target : null;

        return [
            'path' => '/'.$route->path,
            'url' => $this->resolver->publicUrl($route),
            'kind' => $route->kind,
            // Where an old address leads now. Null on a live page, which has nowhere to lead.
            'target' => $target === null ? null : '/'.$target->path,
            'entity_type' => $route->entity_type,
            'entity_id' => $route->entity_id,
            // False when a shorter row matched a longer address: the page at `/parts` answering
            // for `/parts/bobcat`, not a page of its own.
            'exact' => $resolution->tail === '',
        ];
    }
}
