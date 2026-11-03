<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use JsonSerializable;
use Stringable;

/**
 * A gateway's keys for one method (spec §15.1): what the base hands a satellite, read from the
 * method's encrypted column or the site's environment. Immutable, and silent: it turns into
 * `[hidden]` in a string, a dump, a log line or a JSON answer, so a key never leaves by accident.
 * The journal's masker reads the values on purpose — to wipe them from what it writes (§20.5).
 */
final class Credentials implements JsonSerializable, Stringable
{
    public const string HIDDEN = '[hidden]';

    /** @param  array<string, string>  $values  field name => value; empty values are not keys */
    private function __construct(private readonly array $values) {}

    /** @param  array<array-key, mixed>  $values */
    public static function of(array $values): self
    {
        $keys = [];

        foreach ($values as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value)) && (string) $value !== '') {
                $keys[$name] = (string) $value;
            }
        }

        return new self($keys);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]);
    }

    /** @return list<string> the names of the fields that are set — never their values */
    public function names(): array
    {
        return array_keys($this->values);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * The values by field, for {@see Exchange\ExchangeMasker} to wipe. Nothing else reads them all.
     *
     * @internal
     *
     * @return array<string, string>
     */
    public function reveal(): array
    {
        return $this->values;
    }

    public function __toString(): string
    {
        return self::HIDDEN;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return array_fill_keys($this->names(), self::HIDDEN);
    }

    public function jsonSerialize(): string
    {
        return self::HIDDEN;
    }

    /** @return array<string, string> */
    public function __serialize(): array
    {
        // A queued job or a cached value would carry the keys in clear text.
        throw new \LogicException('Gateway credentials are never serialized: read them again where they are needed.');
    }
}
