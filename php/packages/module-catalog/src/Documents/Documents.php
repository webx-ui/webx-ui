<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Documents;

use Illuminate\Database\Eloquent\Collection;
use LogicException;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Models\Product;

/**
 * The contributors to the search document, and the document they make together (§7.3).
 *
 * The schema is every contributor's fields; an engine that keeps an index compares it with the
 * live one on `webx:catalog:index --rebuild`. Two contributors naming one field is a mistake
 * that would surface as one of them silently losing — it is refused here instead.
 */
final class Documents
{
    /** @var list<DocumentContributor> */
    private array $contributors = [];

    public function register(DocumentContributor $contributor): void
    {
        $this->contributors[] = $contributor;
    }

    /** @return list<DocumentContributor> */
    public function all(): array
    {
        return $this->contributors;
    }

    /**
     * @param  list<string>  $locales
     * @return list<IndexField>
     */
    public function schema(array $locales): array
    {
        $fields = [];

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->fields() as $field) {
                $names = $field->localized
                    ? array_map(static fn (string $locale): string => $field->name.'_'.$locale, $locales)
                    : [$field->name];

                foreach ($names as $name) {
                    if (isset($fields[$name])) {
                        throw new LogicException("Two contributors write the index field [{$name}].");
                    }

                    $fields[$name] = new IndexField($name, $field->type, $field->multi, code: $field->code);
                }
            }
        }

        return array_values($fields);
    }

    /**
     * One document per product, from every contributor in one pass over the batch.
     *
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $locales
     * @return array<int, array<string, mixed>>
     */
    public function build(Collection $products, array $locales): array
    {
        $documents = [];

        foreach ($products as $product) {
            $documents[(int) $product->id] = [];
        }

        foreach ($this->contributors as $contributor) {
            foreach ($contributor->contribute($products, $locales) as $id => $fields) {
                if (isset($documents[$id])) {
                    $documents[$id] = [...$documents[$id], ...$fields];
                }
            }
        }

        return $documents;
    }
}
