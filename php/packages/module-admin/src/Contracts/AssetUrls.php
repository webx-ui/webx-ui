<?php

declare(strict_types=1);

namespace WebxUi\Admin\Contracts;

/**
 * Where a library key is served from right now.
 *
 * Implemented by whoever has the files — `webx-ui/module-media` — and asked for by the panel,
 * which cannot depend on it: the dependency runs the other way. Bound optionally, so a site
 * with no file manager simply has nothing to ask, and what carries a key is left as it is.
 *
 * Keys in, addresses out, a list at a time: a document with twenty pictures is one question,
 * not twenty. A key the library no longer knows answers `null` — a file deleted out from under
 * a page is not an error worth refusing to render the page over.
 */
interface AssetUrls
{
    /**
     * @param  list<string>  $paths
     * @return array<string, string|null>
     */
    public function urls(array $paths): array;
}
