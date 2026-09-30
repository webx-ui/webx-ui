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
