<?php

declare(strict_types=1);

namespace WebxUi\Admin\Gate;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What the password over the site lets through without asking.
 *
 * The frame knows its own addresses — the panel and its JSON — and nothing else, so a package
 * that answers where a browser without the password has to reach puts its own opening here:
 * the MCP server and its OAuth dance, the block preview under its token. Each package knows
 * where it answers and what makes a request to it legitimate; a list in one place would have
 * to learn every package's configuration and still be wrong the day one of them changes.
 *
 * Openings are asked on every request of a closed site, so they are read at that moment and
 * not when they were added: a config value that changes in a test, or differs per request,
 * is the one that counts.
 */
final class Openings
{
    /** @var list<Closure(Request): bool> */
    private array $openings = [];

    public function __construct(private readonly Config $config)
    {
        // The panel, whose own sign-in is what guards it — a password in front of it would be
        // a second one for the same person, and one the agent connecting through it cannot type.
        $this->allow(fn (Request $request): bool => $this->under($request, $this->config->get('webx-admin.path'))
            || $this->under($request, $this->config->get('webx-admin.api_path')));

        // ACME and its neighbours. Closed, the certificate stops renewing three months later,
        // and nothing about it points back here.
        $this->paths(['.well-known/*']);

        // What the site itself said. Read with `??`: a published config from before the gate
        // has no `gate` key at all, and `mergeConfigFrom` merges only the top level.
        $this->allow(fn (Request $request): bool => Str::is(
            array_values(array_filter((array) ($this->config->get('webx-admin.gate.except') ?? []), 'is_string')),
            $this->path($request),
        ));
    }

    /**
     * Let a request through on a condition of the caller's.
     *
     * @param  Closure(Request): bool  $opening
     */
    public function allow(Closure $opening): void
    {
        $this->openings[] = $opening;
    }

    /**
     * Let through the addresses that match these masks — `Str::is` against the path without its
     * leading slash, so `oauth/*` is everything under `/oauth/`.
     *
     * @param  list<string>|Closure(): list<string>  $masks  a closure when the masks come from config
     */
    public function paths(array|Closure $masks): void
    {
        $this->allow(fn (Request $request): bool => Str::is(
            $masks instanceof Closure ? $masks() : $masks,
            $this->path($request),
        ));
    }

    public function open(Request $request): bool
    {
        foreach ($this->openings as $opening) {
            if ($opening($request)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the request is at this path or below it. An empty path is nothing rather than
     * everything: a panel mounted at the root is not a site with a front to close.
     */
    public function under(Request $request, mixed $prefix): bool
    {
        $prefix = is_string($prefix) ? trim($prefix, '/') : '';

        if ($prefix === '') {
            return false;
        }

        $path = $this->path($request);

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }

    private function path(Request $request): string
    {
        return trim($request->path(), '/');
    }
}
