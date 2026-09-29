<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * What is chosen in one facet: values for terms, trees and toggles, two ends for a range.
 *
 * Values are the facet's own identifiers as strings — an id, a code — never slugs: slugs are the
 * address's business and are turned into values once, by the serializer (§7.7).
 */
final class FacetValue
{
    /**
     * @param  list<string>  $values
     */
    private function __construct(
        public readonly array $values = [],
        public readonly ?float $min = null,
        public readonly ?float $max = null,
    ) {}

    /**
     * @param  iterable<int|string>  $values
     */
    public static function of(iterable $values): self
    {
        $list = [];

        foreach ($values as $value) {
            $list[(string) $value] = (string) $value;
        }

        return new self(array_values($list));
    }

    public static function range(?float $min, ?float $max): self
    {
        if ($min !== null && $max !== null && $min > $max) {
            [$min, $max] = [$max, $min];
        }

        return new self([], $min, $max);
    }

    public function isRange(): bool
    {
        return $this->values === [] && ($this->min !== null || $this->max !== null);
    }

    public function isEmpty(): bool
    {
        return $this->values === [] && $this->min === null && $this->max === null;
    }

    public function has(string $value): bool
    {
        return in_array($value, $this->values, true);
    }

    public function with(string $value): self
    {
        return self::of([...$this->values, $value]);
    }

    public function without(string $value): self
    {
        return self::of(array_filter($this->values, static fn (string $kept): bool => $kept !== $value));
    }

    public function equals(self $other): bool
    {
        $mine = $this->values;
        $theirs = $other->values;
        sort($mine);
        sort($theirs);

        return $mine === $theirs && $this->min === $other->min && $this->max === $other->max;
    }
}
