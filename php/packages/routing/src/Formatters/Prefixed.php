<?php

declare(strict_types=1);

namespace WebxUi\Routing\Formatters;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Routing\UrlNormaliser;

/**
 * `article/kak-vybrat-remen` — another formatter's answer under a fixed first segment.
 *
 * The prefix belongs to the module that asked for it, not to routing: news deciding that its
 * articles live under `/article/` is the same kind of choice as news deciding they are spelled
 * with the key on the end. The namespace stays flat — `article` is simply an address like any
 * other, and a page called `article` would collide with it, which is correct and is exactly
 * what the unique index is for.
 */
class Prefixed implements PathFormatter
{
    private PathFormatter $inner;

    /** @param  PathFormatter|class-string<PathFormatter>  $inner */
    public function __construct(private readonly string $prefix, PathFormatter|string $inner = Slug::class)
    {
        $this->inner = is_string($inner) ? Container::getInstance()->make($inner) : $inner;
    }

    public function format(Model $entity, string $locale): string
    {
        return UrlNormaliser::join($this->prefix, $this->inner->format($entity, $locale));
    }
}
