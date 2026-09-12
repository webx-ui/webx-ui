<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Closure;

/**
 * Something an agent can read by address rather than by calling: an audit listing, a report,
 * a rendered preview. Named McpResource because `Resource` is a reserved word in PHP 8.
 */
final class McpResource
{
    public function __construct(
        /** e.g. `seo://audit/missing-title` */
        public readonly string $uri,
        public readonly string $name,
        public readonly string $description,
        public readonly Closure $handler,
        public readonly string $mimeType = 'application/json',
    ) {}
}
