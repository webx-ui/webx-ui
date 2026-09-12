<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Closure;

/**
 * A ready-made request a module offers an agent: "write a description for this product",
 * "find pages whose SEO contradicts their content".
 */
final class Prompt
{
    /**
     * @param  array<string, string>  $arguments  name => what it is for
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly Closure $handler,
        public readonly array $arguments = [],
    ) {}
}
