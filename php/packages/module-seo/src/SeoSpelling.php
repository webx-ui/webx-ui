<?php

declare(strict_types=1);

namespace WebxUi\Seo;

use WebxUi\Routing\Contracts\Spelling;

/**
 * The resolver's spelling, decided by the SEO tab — the same settings and the same function as
 * the normalisation middleware ({@see Normalisation::path()}). A part that is off is left as it
 * was written: «keep as it is» in the panel means the registry does not impose its own either.
 */
final readonly class SeoSpelling implements Spelling
{
    public function __construct(private Normalisation $settings) {}

    public function of(string $path): string
    {
        return $this->settings->path($path === '' ? '/' : $path);
    }
}
