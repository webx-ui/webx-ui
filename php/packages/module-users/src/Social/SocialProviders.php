<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

use InvalidArgumentException;

/**
 * The registry of social networks (spec §9.1): Google and Facebook from the module, any other
 * from a package or the site — `register()` in `boot()`. Whether one is shown is
 * `ProviderKeys`' to say: the registry is what could be, not what is switched on.
 */
final class SocialProviders
{
    /** @var array<string, Provider> */
    private array $providers = [];

    public function register(Provider $provider): void
    {
        $key = $provider->key();

        if (preg_match('/^[a-z0-9-]{1,32}$/', $key) !== 1) {
            throw new InvalidArgumentException("The key of a social provider is [a-z0-9-], up to 32 characters: \"{$key}\".");
        }

        if (isset($this->providers[$key])) {
            throw new InvalidArgumentException("The social provider \"{$key}\" is registered already.");
        }

        $this->providers[$key] = $provider;
    }

    /**
     * In the order of `webx-users.social.order`, then the order of registration.
     *
     * @return list<Provider>
     */
    public function all(): array
    {
        $order = array_values(array_map('strval', (array) config('webx-users.social.order', [])));
        $providers = array_values($this->providers);
        $position = static function (Provider $provider) use ($order): int {
            $index = array_search($provider->key(), $order, true);

            return $index === false ? PHP_INT_MAX : (int) $index;
        };

        usort($providers, static fn (Provider $a, Provider $b): int => $position($a) <=> $position($b));

        return $providers;
    }

    public function find(string $key): ?Provider
    {
        return $this->providers[$key] ?? null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
