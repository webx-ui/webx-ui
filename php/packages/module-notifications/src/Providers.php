<?php

declare(strict_types=1);

namespace WebxUi\Notifications;

use WebxUi\Notifications\Contracts\Provider;

/**
 * The kinds of channel the site has (§4.2). The package registers `telegram` and `email`; a
 * package or a site adds its own the same way, from its provider:
 *
 *     $this->callAfterResolving(Providers::class, fn (Providers $providers) => $providers->register(new Slack));
 *
 * A channel whose provider is gone stays in the database and sends nothing: its deliveries are
 * logged as skipped, and webx:doctor says why.
 */
final class Providers
{
    /** @var array<string, Provider> */
    private array $providers = [];

    public function register(Provider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function find(string $key): ?Provider
    {
        return $this->providers[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * @return array<string, Provider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
