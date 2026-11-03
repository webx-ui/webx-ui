<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use WebxUi\Payments\Contracts\MethodType;

/**
 * The types of payment methods the site can turn on (spec §6.2). The base registers
 * `store.on_receipt` and `store.bank_transfer`; a satellite registers its own from `boot()`
 * through `callAfterResolving(MethodTypes::class)`.
 *
 * A class is registered by name and made on first use: a satellite's types cost nothing on a page
 * that never asks for payment. The key is checked when the type is made, the first moment it is
 * known.
 */
final class MethodTypes
{
    /** `<provider>.<kind>`. */
    public const string KEY = '/^[a-z0-9-]+\.[a-z0-9_-]+$/';

    /** @var array<string, MethodType> by key */
    private array $types = [];

    /** @var list<class-string<MethodType>> not made yet */
    private array $pending = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<MethodType>|MethodType  $type  a class is made on first use */
    public function register(string|MethodType $type): void
    {
        if (is_string($type)) {
            if (in_array($type, $this->pending, true)) {
                throw new LogicException("Payment method type {$type} is registered twice.");
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
                throw new InvalidArgumentException("{$class} is not a payment method type: it does not implement ".MethodType::class.'.');
            }

            $this->add($type);
        }
    }

    private function add(MethodType $type): void
    {
        $key = $type->key();

        if (preg_match(self::KEY, $key) !== 1 || strlen($key) > 64) {
            throw new InvalidArgumentException("[{$key}] is not a payment method type key: <provider>.<kind>, lowercase letters, digits, dashes and underscores.");
        }

        if (isset($this->types[$key])) {
            throw new LogicException("Payment method type {$key} is registered twice.");
        }

        if (! str_starts_with($key, $type->provider().'.')) {
            throw new LogicException("Payment method type {$key} says it belongs to {$type->provider()}: the key starts with the provider.");
        }

        $this->types[$key] = $type;
    }
}
