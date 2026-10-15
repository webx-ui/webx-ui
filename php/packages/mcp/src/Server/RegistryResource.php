<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource as McpResourceBase;
use WebxUi\Mcp\McpResource;

/**
 * A module's resource as `laravel/mcp` serves it: the address and the MIME type the module
 * gave, the handler's result as text. An address with a `{variable}` in it is served by
 * {@see RegistryResourceTemplate}.
 */
class RegistryResource extends McpResourceBase
{
    final public function __construct(protected readonly McpResource $resource) {}

    /** The class that serves this resource: a template when its address has a variable in it. */
    public static function for(McpResource $resource): self
    {
        return str_contains($resource->uri, '{') ? new RegistryResourceTemplate($resource) : new self($resource);
    }

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
        $result = ($this->resource->handler)($request->all());

        return Response::text(Results::toText(Container::getInstance()->make(EmptyMaps::class)->apply($result)));
    }
}
