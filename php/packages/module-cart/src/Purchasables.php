<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use WebxUi\Cart\Contracts\Purchasable;

/**
 * The providers of what goes into a cart, by type: `catalog.product`, later `catalog.variant`,
 * `certificates.nominal`. The owner of the records registers its provider when the cart is
 * installed; the cart knows nothing of the owner.
 */
final class Purchasables
{
    private const string TYPE = '/^[a-z0-9-]+\.[a-z0-9-]+$/';

    /** @var array<string, class-string<Purchasable>|Purchasable> */
    private array $providers = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<Purchasable>|Purchasable  $provider  a class is made on first use */
    public function register(string $type, string|Purchasable $provider): void
    {
        if (preg_match(self::TYPE, $type) !== 1) {
            throw new InvalidArgumentException("A purchasable type is <module>.<entity> in [a-z0-9-], [{$type}] is not.");
        }

        // Two providers of one type is a mistake of the build, not something to settle quietly.
        if (isset($this->providers[$type])) {
            throw new LogicException("A purchasable is already registered as [{$type}].");
        }

        $this->providers[$type] = $provider;
    }

    public function has(string $type): bool
    {
        return isset($this->providers[$type]);
    }

    /** @throws UnknownPurchasable */
    public function get(string $type): Purchasable
    {
        $provider = $this->providers[$type] ?? throw UnknownPurchasable::type($type);

        if (is_string($provider)) {
            $made = $this->container->make($provider);

            if (! $made instanceof Purchasable) {
                throw new LogicException("[{$provider}] does not implement ".Purchasable::class.'.');
            }

            $provider = $this->providers[$type] = $made;
        }

        return $provider;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->providers);
    }
}
