<?php

declare(strict_types=1);

namespace WebxUi\Seo\Targets;

use InvalidArgumentException;

/** An address on somebody else's site, where only this site's addresses make sense (§18.2). */
final class ForeignHost extends InvalidArgumentException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct("The address [{$url}] is on another site.");
    }
}
