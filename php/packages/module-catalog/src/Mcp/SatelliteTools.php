<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Mcp;

use Closure;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Tool;

/**
 * A satellite's tools and resources served as the catalogue's own.
 *
 * A module's tools are named after its id, and some of a satellite's work belongs to the catalogue
 * by name: `catalog_categories_properties` is a question about a category, and the values of a
 * property are `catalog_property_values_*`, not `catalog_properties_values_*`. Served under the
 * catalogue, they also carry its scopes (`catalog:read`, `catalog:write`) and its permissions,
 * which is what a satellite without permissions of its own wants anyway.
 *
 * Asked lazily, at every listing, as the core's own tools are: a satellite registers a closure
 * from its provider and builds nothing until an agent asks. A name taken twice is caught by the
 * MCP registry, like any other duplicate.
 */
final class SatelliteTools
{
    /** @var list<Closure(): list<Tool>> */
    private array $tools = [];

    /** @var list<Closure(): list<McpResource>> */
    private array $resources = [];

    /**
     * @param  Closure(): list<Tool>  $tools  named after `catalog_`: `property_values_list`
     */
    public function tools(Closure $tools): void
    {
        $this->tools[] = $tools;
    }

    /**
     * @param  Closure(): list<McpResource>  $resources
     */
    public function resources(Closure $resources): void
    {
        $this->resources[] = $resources;
    }

    /**
     * @return list<Tool>
     */
    public function allTools(): array
    {
        return array_merge(...array_map(static fn (Closure $tools): array => $tools(), $this->tools) ?: [[]]);
    }

    /**
     * @return list<McpResource>
     */
    public function allResources(): array
    {
        return array_merge(...array_map(static fn (Closure $resources): array => $resources(), $this->resources) ?: [[]]);
    }
}
