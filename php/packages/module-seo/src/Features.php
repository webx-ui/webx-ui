<?php

declare(strict_types=1);

namespace WebxUi\Seo;

/**
 * The optional tools of this module, as a developer switched them on for the project (§18.3).
 *
 * Read at the moment of asking rather than once at boot, except by the route file — routes are
 * registered once, and a feature that is off answers 404 because nothing was registered for it.
 */
final class Features
{
    public static function links(): bool
    {
        return (bool) config('webx-seo.links.enabled', false);
    }

    public static function faq(): bool
    {
        return (bool) config('webx-seo.faq.enabled', false);
    }

    /**
     * The share title, description and picture in the SEO card. Off by default: Open Graph is
     * filled in from the page itself, and fields nobody fills in are fields that look forgotten.
     */
    public static function ogFields(): bool
    {
        return (bool) config('webx-seo.og.panel_fields', false);
    }
}
