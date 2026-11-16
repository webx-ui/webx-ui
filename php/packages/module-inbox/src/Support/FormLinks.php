<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Support;

use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Localization\Locales;

/**
 * Where a link of a form's settings leads, in the language of the page: `redirect` (instead of the
 * thank-you) and `thank-you.button-link` (the button under it, FORM_STEPS §16.2).
 *
 * A record of the site is its version in that language, or in the main one when it has none: a
 * visitor of the Russian page is better off on the English page than on a 404. A record that is not
 * on the site in either — a draft, the bin, deleted — leads nowhere (null). An address is the
 * editor's, and a path of this site gets the visitor's language the way every hand-written link does.
 */
final class FormLinks
{
    public static function href(?Link $link, LinkUrls $urls, Locales $locales, ?string $locale = null): ?string
    {
        if ($link === null) {
            return null;
        }

        $locale ??= app()->getLocale();

        if ($link->target !== LinkTarget::Entity) {
            return $urls->href($link, $locale);
        }

        foreach (array_unique([$locale, $locales->defaultCode()]) as $one) {
            $candidate = $urls->candidate($link, $one);

            if ($candidate !== null && $candidate->available && $candidate->url !== null) {
                return $urls->hrefWith($link, $candidate, $one);
            }
        }

        return null;
    }
}
