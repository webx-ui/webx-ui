<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * What most facets answer the same way, so a satellite writes only what is its own.
 *
 * Indexable when it is a reference book; values are their own slugs and their own labels; a
 * choice is already in its one spelling. Anything of that a facet knows better, it overrides.
 *
 * The code in an address is the site's to translate, not the facet's: `webx-catalog.facet_codes`
 * names it per language (`'price' => ['ru' => 'cena']`), and a language it does not name gets
 * {@see baseCode()} — the key, unless the facet says otherwise.
 */
abstract class AbstractFacet implements Facet
{
    public function code(string $locale): string
    {
        $codes = config('webx-catalog.facet_codes');
        // Read as an array, not by a dotted path: a key like `p.12` is a dot of its own.
        $configured = is_array($codes) && is_array($codes[$this->key()] ?? null) ? ($codes[$this->key()][$locale] ?? null) : null;

        return is_string($configured) && $configured !== '' ? $configured : $this->baseCode();
    }

    /** The code in every language the config does not name. */
    protected function baseCode(): string
    {
        return $this->key();
    }

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
