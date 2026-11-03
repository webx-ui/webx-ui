<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use WebxUi\Delivery\Contracts\MethodType;

/**
 * The types of delivery methods the site can turn on (§6.2). The base registers `store.pickup`
 * and `store.courier`; a satellite registers its own from `boot()` through
 * `callAfterResolving(MethodTypes::class)`.
 *
 * A class is registered by name and made on first use: a satellite's types cost nothing on a page
 * that never asks for delivery. The key is checked when the type is made, as that is the first
 * moment it is known.
 */
final class MethodTypes
{
    /** `<carrier>.<kind>`. */
    public const string KEY = '/^[a-z0-9-]+\.[a-z0-9-]+$/';

    /** @var array<string, MethodType> by key */
    private array $types = [];

    /** @var list<class-string<MethodType>> not made yet */
    private array $pending = [];

    public function __construct(
        private readonly Container $container,
        private readonly Carriers $carriers,
    ) {}

    /** @param  class-string<MethodType>|MethodType  $type */
    public function register(string|MethodType $type): void
    {
        if (is_string($type)) {
            if (in_array($type, $this->pending, true)) {
                throw new LogicException("Delivery method type {$type} is registered twice.");
            }

            $this->pending[] = $type;

            return;
        }

        $this->add($type);
    }

    public function has(string $key): bool
    {
        $this->make();

        return isset($this->types[$key]);
    }

    /** @throws UnknownMethodType */
    public function get(string $key): MethodType
    {
        $this->make();

        return $this->types[$key] ?? throw UnknownMethodType::named($key, array_keys($this->types));
    }

    /** @return list<MethodType> */
    public function all(): array
    {
        $this->make();

        return array_values($this->types);
    }

    /** @return list<MethodType> types of one carrier */
    public function ofCarrier(string $carrier): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (MethodType $type): bool => $type->carrier() === $carrier,
        ));
    }

    /** @return list<string> */
    public function keys(): array
    {
        $this->make();

        return array_keys($this->types);
    }

    private function make(): void
    {
        while ($this->pending !== []) {
            $class = array_shift($this->pending);
            $type = $this->container->make($class);

            if (! $type instanceof MethodType) {
                throw new InvalidArgumentException("{$class} is not a delivery method type: it does not implement ".MethodType::class.'.');
            }

            $this->add($type);
        }
    }

    private function add(MethodType $type): void
    {
        $key = $type->key();

        if (preg_match(self::KEY, $key) !== 1 || strlen($key) > 64) {
            throw new InvalidArgumentException("[{$key}] is not a delivery method type key: <carrier>.<kind>, lowercase letters, digits and dashes.");
        }

        if (isset($this->types[$key])) {
            throw new LogicException("Delivery method type {$key} is registered twice.");
        }

        $carrier = $type->carrier();

        if (! str_starts_with($key, $carrier.'.')) {
            throw new LogicException("Delivery method type {$key} says it belongs to carrier {$carrier}: the key starts with the carrier.");
        }

        // A type without its carrier has no title, no settings and nothing to sync: a mistake
        // of the satellite that registered it, and better seen at the first request than never.
        if (! $this->carriers->has($carrier)) {
            throw new LogicException("Delivery method type {$key} belongs to carrier {$carrier}, which is not registered.");
        }

        $this->types[$key] = $type;
    }
}
