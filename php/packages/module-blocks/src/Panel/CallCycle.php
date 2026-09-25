<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use WebxUi\Blocks\Exceptions\BlocksException;

/** Types that call each other in a circle, with the circle: `recipe-card → badge → recipe-card`. */
final class CallCycle extends BlocksException
{
    /**
     * @param  list<string>  $path
     */
    public function __construct(public readonly array $path)
    {
        parent::__construct('The types call each other in a circle: '.self::describe($path).'.');
    }

    /**
     * @param  list<string>  $path
     */
    public static function describe(array $path): string
    {
        return implode(' → ', $path);
    }
}
