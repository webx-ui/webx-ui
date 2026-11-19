<?php

declare(strict_types=1);

namespace WebxUi\Cart\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\CartHandle;
use WebxUi\Localization\Locales;

/**
 * The JSON doors of the cart, the delivery step and the payment step answer in the language of
 * the page that asked.
 *
 * Their address has no language in it — `/api/cart` is the same on every page — so the site's own
 * `webx.locale` either never runs on them or reads `api` as the language and falls back. The HTML
 * they draw for the page to put in place (the cart's panel, the step drawn again) was then in the
 * application's language (`APP_LOCALE`): an English «Total» in the cart of a Russian page, an
 * English step on a Ukrainian checkout.
 *
 * The page knows its language, and its script says so in `X-Webx-Locale`; a list a widget fetches
 * by a ready address (the towns, the points of the step) carries it as `?locale=` instead, which
 * keeps the browser's cache of such a list apart per language. Believed only as far as a language
 * the site has; otherwise — the site's main language, never the application's.
 */
final class SpeakTheLanguageOfThePage
{
    /** The query parameter of a ready address, for whoever cannot send a header. */
    public const string QUERY = 'locale';

    public function __construct(private readonly Locales $locales) {}

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $asked = $request->headers->get(CartHandle::LOCALE_HEADER) ?: $request->query(self::QUERY);

        if (! is_string($asked) || $asked === '' || ! $this->locales->use($asked)) {
            $this->locales->use($this->locales->defaultCode());
        }

        return $next($request);
    }
}
