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
    ) {}

    public function value(string $name, mixed $default = null): mixed
    {
        return $this->values[$name] ?? $default;
    }
}
