<?php

declare(strict_types=1);

namespace WebxUi\Seo\Panel;

use WebxUi\Routing\Resolver;
use WebxUi\Routing\UrlNormaliser;
use WebxUi\Seo\Http\Resources\SeoRedirectResource;
use WebxUi\Seo\Http\Resources\SeoUrlResource;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Rendering\Seo;
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

        return [
            'url' => $url,
            // Said first because it happens first: an address that redirects never gets as far
            // as the rules below, and that is the answer often enough to be worth the query.
            'redirect' => $this->redirect($url),
            // What the registry has at this address, so the answer to "can I redirect this away"
            // and to "why is this address answering at all" comes from the same call.
            'route' => $this->route($url, $locale),
            'matched' => $matched === null ? null : (new SeoUrlResource($matched))->resolve(),
            'chain' => $this->seo->chain($url, $subject, $seoLocale),
            'seo' => $this->seo->for($url, $subject, $seoLocale)->toArray(),
            // The first question when a page is missing from a search engine (§17.6).
            'sitemap' => $this->sitemap->verdict($url, $locale),
        ];
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
