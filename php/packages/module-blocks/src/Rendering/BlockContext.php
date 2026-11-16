<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

/**
 * What a template knows about the block it is printing, as `$block`.
 *
 * The values themselves arrive as variables — `$title`, `$tone` — for the common case; this
 * object is for the rest: the instance key the panel finds the block by, the type and version
 * that produced it, and a value whose name cannot be a variable (`project-name`).
 */
final readonly class BlockContext
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public string $key,
        public string $type,
        public int $version,
        public array $values,
        public ?object $entity,
        public int $depth,
        // Where it stands among the blocks around it, from 0: a template whose pictures are the
        // first screen only when it opens the page (`depth` and `index` both 0) fetches them
        // first, and leaves them for later anywhere else.
        public int $index = 0,
    ) {}

    public function value(string $name, mixed $default = null): mixed
    {
        return $this->values[$name] ?? $default;
    }
}
