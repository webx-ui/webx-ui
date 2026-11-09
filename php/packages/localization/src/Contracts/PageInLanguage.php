<?php

declare(strict_types=1);

namespace WebxUi\Localization\Contracts;

use Illuminate\Http\Request;

/**
 * The address of the page being answered, in another of the site's languages (ROUTING §18.1).
 *
 * The language of the first visit asks it where to send somebody. `null` is "this page has no
 * such address" — no translation, or nothing but that language's home page — and then nobody is
 * sent anywhere. This package knows neither the registry of addresses nor the switcher, so the
 * default answer is always `null`; `webx-ui/widgets` binds the one its switcher uses, so the
 * switcher and the redirect never disagree.
 */
interface PageInLanguage
{
    /** Absolute, without a query; `null` — no page of this one in that language. */
    public function url(Request $request, string $locale): ?string;
}
