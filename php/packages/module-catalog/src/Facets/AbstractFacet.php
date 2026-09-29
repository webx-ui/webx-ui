<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * What most facets answer the same way, so a satellite writes only what is its own.
 *
 * Indexable when it is a reference book; values are their own slugs and their own labels; a
 * choice is already in its one spelling. Anything of that a facet knows better, it overrides.
 */
abstract class AbstractFacet implements Facet
{
    public function indexable(): bool
    {
        return in_array($this->kind(), [FacetKind::Terms, FacetKind::Tree], true);
    }

    public function field(): IndexField
    {
        return match ($this->kind()) {
            FacetKind::Range => new IndexField($this->key(), IndexField::FLOAT),
            FacetKind::Toggle => new IndexField($this->key(), IndexField::BOOL),
            default => new IndexField($this->key(), IndexField::STRING, multi: true),
        };
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        return array_combine($values, $values);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function slugs(array $values, string $locale): array
    {
        return array_combine($values, $values);
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        return array_combine($slugs, $slugs);
    }

    public function normalise(FacetValue $value): FacetValue
    {
        return $value;
    }
}
