<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

/**
 * What a block's template knows about the region it is printed in, as `$region`.
 *
 * The attributes of the tag are the one thing the layout can tell the blocks — `:compact="true"`
 * on a landing page reaches the header from code and the header from blocks alike — and the
 * path is where the visitor stands, spelled the way `menu()` compares it. Outside a region —
 * on a page, on the sample a type is checked on — `$region` is null.
 */
final readonly class RegionContext
{
    /**
     * @param  array<string, mixed>  $data  The tag's attributes, less `name` and `fallback`.
     */
    public function __construct(
        public string $name,
        private array $data = [],
        private string $path = '',
    ) {}

    /**
     * One attribute of the tag, or all of them without a key.
     */
    public function data(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }

        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /** The path of the request, no slashes around it: `''` on the front page, `about/team` below. */
    public function path(): string
    {
        return $this->path;
    }
}
