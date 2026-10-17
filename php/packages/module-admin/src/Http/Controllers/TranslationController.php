<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Translations\DictionaryBuilder;

/**
 * The interface's own words, for a panel that runs in the browser.
 *
 * Public, like the language list and for the same reason: the sign-in screen needs its labels
 * before there is a session. What is here is the text of the interface, which anybody who can
 * see the panel can already read.
 *
 * An unknown language is answered in the nearest one the panel has rather than with a 404 — a
 * missing translation is not a missing page, and a blank interface is a worse answer than an
 * English one.
 *
 * Two hundred kilobytes, asked for on every opening of the panel and of the panel on the site,
 * and the same until a lang file changes: answered with an `ETag` made of what the dictionary is
 * built from, and `private, no-cache` — the browser keeps the words and asks whether they are still the
 * ones, and the answer is a 304 without a body. New words still reach the panel at once.
 */
final class TranslationController
{
    public function __construct(
        private readonly DictionaryBuilder $dictionary,
        private readonly Locales $locales,
    ) {}

    public function __invoke(Request $request, string $locale): Response
    {
        $resolved = $this->locales->resolvePanel($locale);
        $tag = md5($resolved.'|'.$this->locales->fallback().'|'.$this->dictionary->version($resolved));

        // Private: the answer goes out through the `web` group with a session cookie on it, and
        // no shared cache in between should keep one.
        $cache = ['etag' => $tag, 'private' => true, 'no_cache' => true];
        $unchanged = (new JsonResponse)->setCache($cache);

        if ($unchanged->isNotModified($request)) {
            return $unchanged;
        }

        return ApiResponse::data($this->dictionary->build($resolved))->setCache($cache);
    }
}
