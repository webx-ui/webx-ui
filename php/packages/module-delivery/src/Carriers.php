<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use WebxUi\Delivery\Carriers\StoreCarrier;
use WebxUi\Delivery\Contracts\Carrier;

/**
 * The carriers of the site (§13.1): the shop itself (`store`, built in) and whatever satellites
 * registered from `boot()` through `callAfterResolving(Carriers::class)`. Classes are made on
 * first use, as the method types are.
 */
final class Carriers
{
    public const string STORE = 'store';

    public const string KEY = '/^[a-z0-9-]+$/';

    /** @var array<string, Carrier> */
    private array $carriers = [];

    /** @var list<class-string<Carrier>> */
    private array $pending = [StoreCarrier::class];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<Carrier>|Carrier  $carrier */
    public function register(string|Carrier $carrier): void
    {
        if (is_string($carrier)) {
            if (in_array($carrier, $this->pending, true)) {
                throw new LogicException("Delivery carrier {$carrier} is registered twice.");
            }

            $this->pending[] = $carrier;

            return;
        }

        $this->add($carrier);
    }

    public function has(string $key): bool
    {
        $this->make();

        return isset($this->carriers[$key]);
    }

    public function get(string $key): Carrier
    {
        $this->make();

        return $this->carriers[$key] ?? throw new DeliveryException(sprintf(
            'Delivery carrier %s is not installed. Known carriers: %s.',
            $key,
            implode(', ', array_keys($this->carriers)),
        ));
    }

    /** @return list<Carrier> */
    public function all(): array
    {
        $this->make();

        return array_values($this->carriers);
    }

    /** @return list<string> */
    public function keys(): array
    {
        $this->make();

        return array_keys($this->carriers);
    }

    private function make(): void
    {
        while ($this->pending !== []) {
            $class = array_shift($this->pending);
            $carrier = $this->container->make($class);

            if (! $carrier instanceof Carrier) {
                throw new InvalidArgumentException("{$class} is not a delivery carrier: it does not implement ".Carrier::class.'.');
            }

            $this->add($carrier);
        }
    }

    private function add(Carrier $carrier): void
    {
        $key = $carrier->key();

        if (preg_match(self::KEY, $key) !== 1 || strlen($key) > 32) {
            throw new InvalidArgumentException("[{$key}] is not a delivery carrier key: lowercase letters, digits and dashes, at most 32 characters.");
        }

        if (isset($this->carriers[$key])) {
            throw new LogicException("Delivery carrier {$key} is registered twice.");
        }

        $this->carriers[$key] = $carrier;
    }
}
