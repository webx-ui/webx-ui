<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use LogicException;

/**
 * Every facet the site has, in the order they were registered (§7.1).
 *
 * The order is the default one — a category with no settings of its own, and no configured
 * ancestor, shows every facet in it (§6.2). The core registers first, so the category and the
 * price lead, and the satellites follow in the order their providers boot.
 *
 * The code is checked here, once, rather than trusted: `_` is the one character a filter
 * address splits on (§8.1 of the architecture), and a facet whose code carried it would make
 * every address of that facet mean two things.
 */
final class Facets
{
    private const CODE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const KEY = '/^[a-z0-9][a-z0-9._-]{0,63}$/';

    /** @var array<string, Facet> */
    private array $facets = [];

    public function register(Facet $facet): void
    {
        $key = $facet->key();
        $code = $facet->code();

        if (preg_match(self::KEY, $key) !== 1) {
            throw new LogicException("A facet key is `[a-z0-9._-]`; [{$key}] is not.");
        }

        if (preg_match(self::CODE, $code) !== 1) {
            throw new LogicException("A facet code is `[a-z0-9-]` — `_` splits a filter address; [{$code}] of [{$key}] is not.");
        }

        foreach ($this->facets as $other) {
            if ($other->key() !== $key && $other->code() === $code) {
                throw new LogicException("The code [{$code}] of the facet [{$key}] is taken by [{$other->key()}].");
            }
        }

        $this->facets[$key] = $facet;
    }

    public function forget(string $key): void
    {
        unset($this->facets[$key]);
    }

    /** @return list<Facet> */
    public function all(): array
    {
        return array_values($this->facets);
    }

    public function find(string $key): ?Facet
    {
        return $this->facets[$key] ?? null;
    }

    public function byCode(string $code): ?Facet
    {
        foreach ($this->facets as $facet) {
            if ($facet->code() === $code) {
                return $facet;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->facets);
    }
}
