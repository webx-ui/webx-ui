<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Engine;

use WebxUi\Catalog\Facets\FacetKind;

/**
 * What the engine counted for one facet (§8.1): a number per value for terms and trees — a tree
 * counts every descendant under its ancestors — the lowest and the highest for a range, and the
 * products left for a toggle.
 *
 * `expanded` is where the filter draws it: open, or under «More filters» (§4.4 of the properties
 * spec). `rank` is the place its source gave it among its neighbours, when the source ranks them —
 * the filter puts them in that order; null keeps the page's.
 */
final class FacetResult
{
    /**
     * @param  array<string, int>  $counts  value → products
     */
    public function __construct(
        public readonly string $key,
        public readonly FacetKind $kind,
        public readonly array $counts = [],
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        public readonly int $count = 0,
        public readonly bool $expanded = true,
        public readonly ?int $rank = null,
    ) {}

    /** The same counts, drawn open or under «More filters», in this place among its neighbours. */
    public function placed(bool $expanded, ?int $rank): self
    {
        return new self($this->key, $this->kind, $this->counts, $this->min, $this->max, $this->count, $expanded, $rank);
    }

    public function countOf(string $value): int
    {
        return $this->counts[$value] ?? 0;
    }

    /**
     * Everything it holds, as arrays and scalars — the form the storefront's cache keeps it in.
     * A site's store may unserialize no classes (`cache.serializable_classes => false`, the
     * default of Laravel's skeleton), and an object would come back as `__PHP_Incomplete_Class`.
     *
     * @return array{key: string, kind: string, counts: array<string, int>, min: ?float, max: ?float, count: int, expanded: bool, rank: ?int}
     */
    public function toCache(): array
    {
        return [
            'key' => $this->key,
            'kind' => $this->kind->value,
            'counts' => $this->counts,
            'min' => $this->min,
            'max' => $this->max,
            'count' => $this->count,
            'expanded' => $this->expanded,
            'rank' => $this->rank,
        ];
    }

    /**
     * The result {@see toCache()} took apart.
     *
     * @param  array{key: string, kind: string, counts: array<string, int>, min: ?float, max: ?float, count: int, expanded: bool, rank: ?int}  $cached
     */
    public static function fromCache(array $cached): self
    {
        return new self(
            $cached['key'],
            FacetKind::from($cached['kind']),
            $cached['counts'],
            $cached['min'],
            $cached['max'],
            $cached['count'],
            $cached['expanded'],
            $cached['rank'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...match ($this->kind) {
            FacetKind::Range => ['key' => $this->key, 'kind' => $this->kind->value, 'min' => $this->min, 'max' => $this->max],
            FacetKind::Toggle => ['key' => $this->key, 'kind' => $this->kind->value, 'count' => $this->count],
            default => ['key' => $this->key, 'kind' => $this->kind->value, 'counts' => $this->counts],
        }, 'expanded' => $this->expanded];
    }
}
