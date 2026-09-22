<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * Where the site's language goes in a path an editor wrote by hand.
 *
 * A contract rather than a call into `webx-ui/routing`, because the frame does not require the
 * address registry: a panel of nothing but settings and administrators has no addresses at all.
 * When routing is installed it answers this, and a link to `/account` comes out prefixed; when it
 * is not, the path is handed on as it was written, which is the only honest thing left to do.
 *
 * The one rule this exists to keep is that the strategy is read once. A second reading of
 * `webx-localization.strategy` is a second reading that drifts from the first.
 */
interface SiteUrls
{
    /**
     * An address on this site out of a path.
     *
     * An absolute address is returned untouched, and a path that already names a language does
     * not get a second prefix.
     */
    public function to(string $path, ?string $locale = null): string;
}
