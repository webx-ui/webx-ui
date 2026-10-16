<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

use LogicException;

/**
 * A channel's secrets, decrypted, for the length of one send or one check (§4.1).
 *
 * It never leaves the process: a dump shows the names with the values masked, and it refuses to
 * be serialised, so a job, a cache entry or a log context cannot carry a token away by accident.
 */
final class Credentials
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(private readonly array $values = []) {}

    public function get(string $name): ?string
    {
        $value = $this->values[$name] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * The names of the secrets that are set, each with true — never a value.
     *
     * @return array<string, true>
     */
    public function names(): array
    {
        return array_map(static fn (): bool => true, array_filter($this->values, static fn (string $value): bool => $value !== ''));
    }

    /**
     * Every secret with its value — only for writing them back, merged with what a form changed.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * Replace the secrets in a text: Telegram's address carries the token, and an error message
     * that quotes the address must not.
     */
    public function mask(string $text): string
    {
        foreach ($this->values as $value) {
            if ($value !== '' && strlen($value) >= 4) {
                $text = str_replace($value, '***', $text);
            }
        }

        return $text;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return array_map(static fn (): string => '***', $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('Credentials are not serialised: a job carries the id of its channel and reads them when it runs.');
    }
}
