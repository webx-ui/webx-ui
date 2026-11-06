<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use Closure;

/**
 * Something an agent can read by address rather than by calling: an audit listing, a report,
 * a rendered preview. Named McpResource because `Resource` is a reserved word in PHP 8.
 *
 * The handler is `fn (array $arguments, ?Authenticatable $user = null)`, as a tool's: the variables
 * of the address, and the administrator asking — null on the local stdio server. A resource has no
 * door of its own, so one that answers differently by person asks inside.
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
