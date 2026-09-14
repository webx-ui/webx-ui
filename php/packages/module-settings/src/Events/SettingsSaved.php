<?php

declare(strict_types=1);

namespace WebxUi\Settings\Events;

/** Fired after a save, with the keys that were written — for a site that caches something built from them. */
final class SettingsSaved
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(public readonly array $keys) {}
}
