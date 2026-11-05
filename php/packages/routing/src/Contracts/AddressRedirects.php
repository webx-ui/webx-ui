<?php

declare(strict_types=1);

namespace WebxUi\Routing\Contracts;

/**
 * Who keeps the redirects of a site, when it has anybody: `module-seo` binds this.
 *
 * Routing does not require that package, and a site without it still has its addresses moved —
 * the registry's own aliases answer for them — so `webx:slugs:latin` asks for this only if bound.
 */
interface AddressRedirects
{
    /**
     * A permanent redirect from one path to another, both as a browser sends them.
     */
    public function moved(string $from, string $to): void;
}
