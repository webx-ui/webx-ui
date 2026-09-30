<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Illuminate\Database\Eloquent\Collection;
use LogicException;
use WebxUi\Catalog\Models\Product;

/**
 * The satellites' columns of the panel's list of products (§7.4), in the order they registered.
 * The core's own columns are the list's, not this registry's: they are the product itself.
 *
 * Columns of the database come from sources ({@see ColumnSource}), asked on every read and
 * after the registered ones; a source's column whose key is taken is left out.
 */
final class ProductColumns
{
    /** @var array<string, ProductColumn> */
    private array $columns = [];

    /** @var list<ColumnSource> */
    private array $sources = [];

    public function register(ProductColumn $column): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $column->key()) !== 1) {
            throw new LogicException("A column key is `[a-z0-9._-]`; [{$column->key()}] is not.");
        }

        $this->columns[$column->key()] = $column;
    }

    public function source(ColumnSource $source): void
    {
        $this->sources[] = $source;
    }

    /** @return list<ProductColumn> */
    public function all(): array
    {
        $all = $this->columns;

        foreach ($this->sources as $source) {
            foreach ($source->columns() as $column) {
                $all[$column->key()] ??= $column;
            }
        }

        return array_values($all);
    }

    /**
     * What the list says about its extra columns, once per response.
     *
     * @return list<array{key: string, label: string, sort: string|null}>
     */
    public function describe(): array
    {
        return array_map(static fn (ProductColumn $column): array => [
            'key' => $column->key(),
            'label' => $column->label(),
            'sort' => $column->sort(),
        ], $this->all());
    }

    /**
     * Every column's values for a page of products, by product.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public function values(Collection $products): array
    {
        $rows = [];

        foreach ($products as $product) {
            $rows[(int) $product->id] = [];
        }

        foreach ($this->all() as $column) {
            $key = $column->key();
            $values = $column->values($products);

            foreach (array_keys($rows) as $id) {
                $rows[$id][$key] = $values[$id] ?? null;
            }
        }

        return $rows;
    }
}
