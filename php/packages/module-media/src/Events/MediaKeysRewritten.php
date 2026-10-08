<?php

declare(strict_types=1);

namespace WebxUi\Media\Events;

/**
 * Files got new keys and every reference was rewritten to them — in the database directly, so
 * no model event saw it. Whatever caches rendered content listens here and lets go of it.
 */
final readonly class MediaKeysRewritten
{
    /**
     * @param  array<string, string>  $paths  old key → new key
     */
    public function __construct(public array $paths) {}
}
