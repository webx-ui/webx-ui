<?php

declare(strict_types=1);

namespace WebxUi\Auth;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Passport\Passport;
use WebxUi\Admin\AbstractModule;

/**
 * The page that tells a person how to connect their own agent.
 *
 * A section rather than a corner of the profile dialog, because it is the page somebody is
 * sent a link to — a client who has never opened this panel before, on a call, with somebody
 * reading the steps out. It carries no permission at all: anybody who got in may connect an
 * agent, and the agent will not be able to do anything they cannot (§15 of the spec).
 *
 * It exists only where there is something to connect to. A site with the HTTP server switched
 * off, or without Passport, has no address to print and no dance to describe, and the section
 * stays out of the menu rather than explaining a door that is not there.
 */
final class ConnectModule extends AbstractModule
{
    public function __construct(private readonly Config $config) {}

    public function id(): string
    {
        return 'connect';
    }

    public function title(): string
    {
        return (string) __('webx-auth::connect.title');
    }

    public function icon(): string
    {
        return 'link';
    }

    /**
     * Last in the system group: it is read once and then never again.
     */
    public function order(): int
    {
        return 950;
    }

    public function group(): string
    {
        return 'system';
    }

    /**
     * The address a client is given, absolute — it is copied into another program on another
     * machine, where a path beginning with a slash means nothing.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return ['url' => url($this->path())];
    }

    /**
     * Whether this panel has an agent door at all.
     */
    public function available(): bool
    {
        return $this->config->get('webx-mcp.path') !== false
            && $this->config->get('webx-mcp.oauth.enabled') !== false
            && class_exists(Passport::class);
    }

    /**
     * Where the server answers, which is `webx-mcp.path` or the panel's API with `/mcp` after
     * it — the same fallback `McpServiceProvider` registers the route with.
     */
    private function path(): string
    {
        $path = $this->config->get('webx-mcp.path');

        if (is_string($path) && $path !== '') {
            return $path;
        }

        return trim((string) $this->config->get('webx-admin.api_path', 'api/cms'), '/').'/mcp';
    }
}
