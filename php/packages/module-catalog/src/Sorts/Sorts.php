<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Sorts;

use Illuminate\Contracts\Config\Repository as Config;
use LogicException;

/**
 * Every order the catalogue knows (§7.2), and the ones a reader is offered.
 *
 * Knowing and offering are two lists on purpose. The panel sorts by any of them; the storefront
 * shows the ones `webx-catalog.sorts` names, in that order — a site that does not want "by name"
 * on its pages still has it in the panel.
 */
final class Sorts
{
    public const DEFAULT = 'default';

    /** @var array<string, Sort> */
    private array $sorts = [];

    public function __construct(private readonly Config $config) {}

    public function register(Sort $sort): void
    {
        if (preg_match('/^[a-z0-9_]{1,32}$/', $sort->key()) !== 1) {
            throw new LogicException("A sort key is `[a-z0-9_]`; [{$sort->key()}] is not.");
        }

        $this->sorts[$sort->key()] = $sort;
    }

    public function find(string $key): ?Sort
    {
        return $this->sorts[$key] ?? null;
    }

    /** The sort by this key, or the default one for a key nobody registered. */
    public function resolve(?string $key): Sort
    {
        return $this->sorts[(string) $key] ?? $this->sorts[self::DEFAULT] ?? throw new LogicException('No default sort is registered.');
    }

    /** @return list<Sort> */
    public function all(): array
    {
        return array_values($this->sorts);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->sorts);
    }

    /**
     * What the storefront offers, in the config's order; a key nobody registered — the price
     * sorts on a site without prices — is skipped.
     *
     * @return list<Sort>
     */
    public function storefront(): array
    {
        $offered = [];

        foreach ((array) $this->config->get('webx-catalog.sorts', [self::DEFAULT]) as $key) {
            if (is_string($key) && isset($this->sorts[$key])) {
                $offered[] = $this->sorts[$key];
            }
        }

        return $offered;
    }
}
