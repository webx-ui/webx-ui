<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Routing\Resolver;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Http\Resources\SeoRedirectResource;
use WebxUi\Seo\Http\Resources\SeoUrlResource;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Panel\UrlRuleSource;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Sitemap\Sitemap;

/**
 * "Why does this page have the wrong title?"
 *
 * The most common question this module gets, and it should take one call to answer rather than
 * a reading of the code: which rule matched, what every source contributed, what the page ends
 * up saying, and whether the address is being redirected before any of that happens.
 */
final class TestUrlController
{
    public function __construct(
        private readonly Seo $seo,
        private readonly UrlRuleSource $rules,
        private readonly SeoRules $compiled,
        private readonly UrlMatcher $matcher,
        private readonly Resolver $resolver,
        private readonly Sitemap $sitemap,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:16'],
        ]);

        $url = UrlNormaliser::normalise((string) $validated['url']);
        $locale = isset($validated['locale']) ? (string) $validated['locale'] : null;

        $matched = $this->rules->matching($url);
        $redirect = $this->redirect($url);

        return ApiResponse::data([
            'url' => $url,
            // Said first because it happens first: an address that redirects never gets as far
            // as the rules below, and that is the answer often enough to be worth the query.
            'redirect' => $redirect === null ? null : new SeoRedirectResource($redirect),
            // What the registry has at this address, so the answer to "can I redirect this away"
            // and to "why is this address answering at all" comes from the same call.
            'route' => $this->route($url, $locale),
            'matched' => $matched === null ? null : new SeoUrlResource($matched),
            'chain' => $this->seo->chain($url, null, $locale),
            'seo' => $this->seo->for($url, null, $locale)->toArray(),
            // The first question when a page is missing from a search engine (§17.6).
            'sitemap' => $this->sitemap->verdict($url, $locale),
        ]);
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

    private function redirect(string $url): ?SeoRedirect
    {
        $row = $this->matcher->match($url, $this->compiled->redirects());
        $id = $row === null ? null : ($row['id'] ?? null);

        return is_numeric($id) ? SeoRedirect::query()->find((int) $id) : null;
    }
}
