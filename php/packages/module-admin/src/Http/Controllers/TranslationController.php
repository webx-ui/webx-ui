<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
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
 */
final class TranslationController
{
    public function __construct(
        private readonly DictionaryBuilder $dictionary,
        private readonly Locales $locales,
    ) {}

    public function __invoke(string $locale): JsonResponse
    {
        return ApiResponse::data($this->dictionary->build($this->locales->resolvePanel($locale)));
    }
}
