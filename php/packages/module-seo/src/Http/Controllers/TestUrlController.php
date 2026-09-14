<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Seo\Http\Resources\SeoRedirectResource;
use WebxUi\Seo\Http\Resources\SeoUrlResource;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\SeoRules;
use WebxUi\Seo\Panel\UrlMatcher;
use WebxUi\Seo\Panel\UrlRuleSource;
use WebxUi\Seo\Rendering\Seo;
use WebxUi\Seo\Rendering\UrlNormaliser;

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
            'matched' => $matched === null ? null : new SeoUrlResource($matched),
            'chain' => $this->seo->chain($url, null, $locale),
            'seo' => $this->seo->for($url, null, $locale)->toArray(),
        ]);
    }

    private function redirect(string $url): ?SeoRedirect
    {
        $row = $this->matcher->match($url, $this->compiled->redirects());
        $id = $row === null ? null : ($row['id'] ?? null);

        return is_numeric($id) ? SeoRedirect::query()->find((int) $id) : null;
    }
}
