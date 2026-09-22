<?php

declare(strict_types=1);

namespace WebxUi\Routing;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\URL;
use WebxUi\Localization\Locales;

/**
 * Where the language goes in an address, for everybody who has to put it there.
 *
 * This used to be a private method of {@see HasUrl}, which was fine while the only addresses on
 * the site belonged to entities. A menu item pointing at `/account` has no entity behind it and
 * still needs the prefix the site is published under — and the one thing that must not happen is
 * a second reading of the strategy, because a second reading is one that drifts from the first.
 *
 *     $site->prefix('uk');            // 'uk', or '' on a site whose default it is
 *     $site->to('/account', 'uk');    // 'https://example.test/uk/account'
 *     $site->to('https://other.test/x'); // untouched
 */
final class SiteUrl
{
    public function __construct(
        private readonly Config $config,
        private readonly Locales $locales,
    ) {}

    /**
     * Empty unless the site puts the language in the path, and unless this language needs it.
     *
     * The default language has no prefix at all with `prefix_default` off, which is what most
     * sites want: `/about` and `/uk/about` being two addresses for one page is the thing search
     * engines complain about.
     */
    public function prefix(?string $locale = null): string
    {
        $locale ??= $this->locales->current();

        if ((string) $this->config->get('webx-localization.strategy', 'prefix') !== 'prefix') {
            return '';
        }

        if (
            $locale === $this->locales->defaultCode()
            && ! (bool) $this->config->get('webx-localization.prefix_default', false)
        ) {
            return '';
        }

        return $locale;
    }

    /**
     * An address on this site out of a path somebody wrote by hand.
     *
     * Two things it deliberately leaves alone. An absolute address is somebody else's site —
     * or this one spelled in full — and prefixing it would produce nonsense; and a path whose
     * first segment is already a language is one the editor prefixed themselves, which happens
     * every time somebody copies an address out of the browser (§2, decision 7). Any of the
     * site's languages counts there, not only the one being rendered: `/en/about` written into
     * a Russian page is a link to the English page, not a mistake to correct.
     */
    public function to(string $path, ?string $locale = null): string
    {
        $path = trim($path);

        if ($this->isAbsolute($path)) {
            return $path;
        }

        $prefix = $this->alreadyPrefixed($path) ? '' : $this->prefix($locale);

        return URL::to(UrlNormaliser::join($prefix, $path));
    }

    /** A scheme, or a protocol-relative `//host/path` — either way, not ours to build. */
    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1;
    }

    /** Does the path open with one of the site's languages? */
    private function alreadyPrefixed(string $path): bool
    {
        $segment = UrlNormaliser::key(explode('/', ltrim($path, '/'), 2)[0]);

        if ($segment === '') {
            return false;
        }

        // Lower case on both sides: `key()` folds the path, and a code may be spelled `pt-BR`.
        $codes = array_map(static fn (string $code): string => mb_strtolower($code, 'UTF-8'), $this->locales->codes());

        return in_array($segment, $codes, true);
    }
}
