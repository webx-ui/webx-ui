<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource as McpResourceBase;
use WebxUi\Mcp\McpResource;

/**
 * A module's resource as `laravel/mcp` serves it: the address and the MIME type the module
 * gave, the handler's result as text.
 */
final class RegistryResource extends McpResourceBase
{
    public function __construct(private readonly McpResource $resource) {}

    public function name(): string
    {
        return Str::slug($this->resource->name);
    }

    public function title(): string
    {
        return $this->resource->name;
    }

    public function description(): string
    {
        return $this->resource->description;
    }

    public function uri(): string
    {
        return $this->resource->uri;
    }

    public function mimeType(): string
    {
        return $this->resource->mimeType;
    }

    public function handle(Request $request): Response
    {
        return Response::text(Results::toText(($this->resource->handler)($request->all())));
    }
}
