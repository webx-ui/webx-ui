<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Catalog;

use WebxUi\Catalog\Facets\CategoryFacet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterState;

/**
 * A landing's set as it is stored (§4 of the landings spec): facet key → `{values}` or
 * `{min, max}`, normalised so that one set has one spelling and one hash — keys in order, values
 * by id ascending, a range as numbers, empty facets left out.
 *
 * Immutable, like the core's {@see FilterState}: a merge or a deletion of a value makes a new set.
 */
final class LandingSet
{
    /**
     * @param  array<string, array{values?: list<string>, min?: float|null, max?: float|null}>  $facets
     */
    private function __construct(private readonly array $facets) {}

    /**
     * From what a form, an agent or the table hand over; whatever is not a set is left out.
     */
    public static function from(mixed $raw): self
    {
        $facets = [];

        foreach (is_array($raw) ? $raw : [] as $key => $choice) {
            if (! is_string($key) || $key === '' || ! is_array($choice)) {
                continue;
            }

            $normal = self::normalise($choice);

            if ($normal !== null) {
                $facets[$key] = $normal;
            }
        }

        ksort($facets, SORT_STRING);

        return new self($facets);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @return array<string, array{values?: list<string>, min?: float|null, max?: float|null}>
     */
    public function all(): array
    {
        return $this->facets;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->facets);
    }

    public function isEmpty(): bool
    {
        return $this->facets === [];
    }

    public function has(string $key): bool
    {
        return isset($this->facets[$key]);
    }

    /** How many facets it fixes: what makes one covering landing larger than another (§5). */
    public function size(): int
    {
        return count($this->facets);
    }

    /**
     * The key of the base and the set together; null for a set with nothing in it, which no
     * landing may hold.
     */
    public function hash(?int $categoryId): ?string
    {
        if ($this->isEmpty()) {
            return null;
        }

        return hash('sha256', ($categoryId ?? 'root').'|'.json_encode($this->facets, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function equals(self $other): bool
    {
        return $this->facets === $other->facets;
    }

    /**
     * One value of a facet replaced by another (a merge) or dropped (a deletion); a list left
     * empty drops the facet.
     */
    public function retarget(string $key, string $from, ?string $to): self
    {
        $values = $this->facets[$key]['values'] ?? null;

        if ($values === null || ! in_array($from, $values, true)) {
            return $this;
        }

        $values = array_values(array_filter($values, static fn (string $value): bool => $value !== $from));

        if ($to !== null) {
            $values[] = $to;
        }

        $facets = $this->facets;
        $facets[$key] = ['values' => $values];

        return self::from($facets);
    }

    /** The set without the facets the registry no longer has — a property in the bin (§9). */
    public function known(Facets $facets): self
    {
        return new self(array_filter(
            $this->facets,
            static fn (string $key): bool => $key !== CategoryFacet::KEY && $facets->find($key) !== null,
            ARRAY_FILTER_USE_KEY,
        ));
    }

    /**
     * What the storefront starts the landing's page from. A list facet is read as a list and a
     * range facet as a range, whatever the stored shape says, so a set written by hand cannot
     * confuse the engine; a facet the registry does not have is left out.
     */
    public function state(Facets $facets): FilterState
    {
        $chosen = [];

        foreach ($this->facets as $key => $choice) {
            $facet = $facets->find($key);

            if ($facet === null || $key === CategoryFacet::KEY) {
                continue;
            }

            $chosen[$key] = $facet->kind() === FacetKind::Range
                ? FacetValue::range($choice['min'] ?? null, $choice['max'] ?? null)
                : FacetValue::of($choice['values'] ?? []);
        }

        return FilterState::of($chosen);
    }

    /**
     * @param  array<array-key, mixed>  $choice
     * @return array{values: list<string>}|array{min: float|null, max: float|null}|null
     */
    private static function normalise(array $choice): ?array
    {
        if (array_key_exists('values', $choice)) {
            $values = [];

            foreach (is_array($choice['values']) ? $choice['values'] : [] as $value) {
                if ((is_string($value) || is_int($value)) && trim((string) $value) !== '') {
                    $values[trim((string) $value)] = true;
                }
            }

            $values = array_keys($values);
            $values = array_map('strval', $values);
            usort($values, 'strnatcmp');

            return $values === [] ? null : ['values' => $values];
        }

        $min = is_numeric($choice['min'] ?? null) ? (float) $choice['min'] : null;
        $max = is_numeric($choice['max'] ?? null) ? (float) $choice['max'] : null;

        return $min === null && $max === null ? null : ['min' => $min, 'max' => $max];
    }
}
