<?php

declare(strict_types=1);

namespace WebxUi\Localization;

use Illuminate\Http\Request;
use WebxUi\Localization\Contracts\PageInLanguage;

/**
 * Bound until something that knows the site's addresses says otherwise: without it there is no
 * telling which page is "the same" in another language, and a guessed path is a 404. The language
 * of the first visit stays silent under it, and `webx:doctor` says so.
 */
final class NoPageInLanguage implements PageInLanguage
{
    public function url(Request $request, string $locale): ?string
    {
        return null;
    }
}
