<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Panel;

use WebxUi\Configurator\Models\Configurator;

/**
 * What an editor read, as a short string, so a save can say whether somebody wrote in between:
 * a hash of the configurator as it is being edited — the draft where there is one — with its key
 * and whether it is on the site.
 */
final class Revision
{
    public static function of(Configurator $configurator): string
    {
        $content = [
            ...$configurator->editing(),
            'key' => $configurator->key,
            'published_at' => $configurator->published_at?->toAtomString(),
        ];

        return substr(sha1(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }
}
