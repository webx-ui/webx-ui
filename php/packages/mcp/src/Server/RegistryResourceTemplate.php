<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Server;

use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Support\UriTemplate;

/**
 * A module's resource whose address names a variable — `tasks://projects/{slug}`.
 *
 * Listed under `resources/templates/list` rather than `resources/list`, because there is no one
 * address to read; `laravel/mcp` matches the address an agent asks for against the template and
 * hands the variables to the handler among its arguments.
 */
final class RegistryResourceTemplate extends RegistryResource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate($this->resource->uri);
    }
}
