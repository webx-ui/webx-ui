<?php

declare(strict_types=1);

namespace WebxUi\Routing\Spelling;

use WebxUi\Routing\Contracts\Spelling;
use WebxUi\Routing\UrlNormaliser;

/**
 * The registry's own spelling, for a site without anybody else's: one slash between segments,
 * none at the end, lower case — the way the registry keys its rows. A file keeps its case:
 * `/files/Report.PDF` is somebody's upload, not a page under another spelling.
 */
final class RegistrySpelling implements Spelling
{
    public function of(string $path): string
    {
        if (preg_match('~/[^/]+\.[a-z0-9]{1,5}$~i', $path) === 1) {
            return '/'.trim((string) preg_replace('#/+#', '/', $path), '/');
        }

        return '/'.UrlNormaliser::key($path);
    }
}
