<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Filter;

use WebxUi\Catalog\Facets\FacetValue;

/**
 * What is chosen in the filter: facet key → its choice. Immutable, so every link the filter
 * draws is a state of its own, built from the page's by one step.
 */
final class FilterState
{
    /**
     * @param  array<string, FacetValue>  $chosen
     */
    private function __construct(private readonly array $chosen) {}

    /**
     * @param  array<string, FacetValue>  $chosen
     */
    public static function of(array $chosen): self
    {
        return new self(array_filter($chosen, static fn (FacetValue $value): bool => ! $value->isEmpty()));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @return array<string, FacetValue> */
    public function all(): array
    {
        return $this->chosen;
    }

    public function get(string $key): ?FacetValue
    {
        return $this->chosen[$key] ?? null;
    }

    public function has(string $key, ?string $value = null): bool
    {
        $chosen = $this->chosen[$key] ?? null;

        return $chosen !== null && ($value === null || $chosen->has($value));
    }

    public function isEmpty(): bool
    {
        return $this->chosen === [];
    }

    public function with(string $key, FacetValue $value): self
    {
        return self::of([...$this->chosen, $key => $value]);
    }

    public function without(string $key): self
    {
        $chosen = $this->chosen;
        unset($chosen[$key]);

        return new self($chosen);
    }

    /** The link of one value in a list: on if it was off, off if it was on. */
    public function toggle(string $key, string $value): self
    {
        $current = $this->chosen[$key] ?? FacetValue::of([]);

        return $this->with($key, $current->has($value) ? $current->without($value) : $current->with($value));
    }

    /**
     * This state with another's choices put over it: a list takes the other's values in too, a
     * range is replaced. What a landing's page reads — its set, and the reader's tail on top.
     */
    public function merge(self $over): self
    {
        $chosen = $this->chosen;

        foreach ($over->chosen as $key => $value) {
            $mine = $chosen[$key] ?? null;
            $chosen[$key] = $mine !== null && ! $mine->isRange() && ! $value->isRange()
                ? FacetValue::of([...$mine->values, ...$value->values])
                : $value;
        }

        return self::of($chosen);
    }

    /** The same facets with the same choices, in whatever order. */
    public function equals(self $other): bool
    {
        if (count($this->chosen) !== count($other->chosen)) {
            return false;
        }

        foreach ($this->chosen as $key => $value) {
            $theirs = $other->chosen[$key] ?? null;

            if ($theirs === null || ! $value->equals($theirs)) {
                return false;
            }
        }

        return true;
    }

    /** How many values are chosen across every facet; a range counts as one. */
    public function size(): int
    {
        $size = 0;

        foreach ($this->chosen as $value) {
            $size += $value->isRange() ? 1 : count($value->values);
        }

        return $size;
    }
}
