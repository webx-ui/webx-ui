<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use InvalidArgumentException;

/**
 * The kinds of file the exchange knows (§2 of the exchange spec), filled from service providers.
 * A file is recognised by its extension, so two formats cannot claim the same one.
 */
final class ExchangeFormats
{
    /** @var array<string, ExchangeFormat> */
    private array $formats = [];

    public function register(ExchangeFormat $format): void
    {
        $key = $format->key();

        if (preg_match('/^[a-z0-9]{1,16}$/', $key) !== 1) {
            throw new InvalidArgumentException("[{$key}] is not an exchange format key: lowercase Latin letters and digits, at most 16.");
        }

        foreach ($this->formats as $other) {
            if ($other->key() === $key) {
                continue;
            }

            $shared = array_intersect($other->extensions(), $format->extensions());

            if ($shared !== []) {
                throw new InvalidArgumentException("The exchange format [{$key}] claims .".implode(', .', $shared).', which is ['.$other->key().']\'s.');
            }
        }

        $this->formats[$key] = $format;
    }

    public function find(string $key): ?ExchangeFormat
    {
        return $this->formats[$key] ?? null;
    }

    /** The format of a file by the extension of its name. */
    public function forName(string $name): ?ExchangeFormat
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        foreach ($this->formats as $format) {
            if (in_array($extension, $format->extensions(), true)) {
                return $format;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->formats);
    }

    /** @return list<string> */
    public function extensions(): array
    {
        $all = [];

        foreach ($this->formats as $format) {
            array_push($all, ...$format->extensions());
        }

        return $all;
    }
}
