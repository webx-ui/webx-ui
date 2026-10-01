<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Exchange;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LogicException;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Catalog\Models\Product;

/**
 * The columns of an exchange file (§3 of the exchange spec): the core's, the satellites', and the
 * ones providers make up as they go — one per property.
 *
 * A code is unique. A registered column that takes a code already taken is a mistake of whoever
 * registered it and is refused at once, as a facet's is; a provider's column that meets a
 * registered one is renamed `p_<code>` rather than refused, because the code of a property is
 * chosen in the panel by somebody who never heard of the core's columns.
 *
 * A column is the field of the form it fills, so what an administrator may map is what the form
 * lets them write: a field behind a permission they lack is off the screen and off the list.
 */
final class ExchangeColumns
{
    /** @var array<string, ExchangeColumn> */
    private array $columns = [];

    /** @var list<ExchangeColumnProvider|class-string<ExchangeColumnProvider>> */
    private array $providers = [];

    public function __construct(
        private readonly Container $container,
        private readonly ScreenRegistry $screens,
    ) {}

    public function register(ExchangeColumn $column): void
    {
        $key = $column->key();

        if (preg_match('/^[a-z0-9_]{1,64}$/', $key) !== 1) {
            throw new InvalidArgumentException("[{$key}] is not an exchange column code: lowercase Latin letters, digits and underscores, at most 64.");
        }

        if (isset($this->columns[$key])) {
            throw new InvalidArgumentException("The exchange column [{$key}] is registered twice.");
        }

        $this->columns[$key] = $column;
    }

    /**
     * @param  ExchangeColumnProvider|class-string<ExchangeColumnProvider>  $provider
     */
    public function provider(ExchangeColumnProvider|string $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Every column, the registered first, in the order they came.
     *
     * @return array<string, ExchangeColumn>
     */
    public function all(): array
    {
        return $this->gather()[0];
    }

    /**
     * The providers' columns that did not keep their code: the code → the one they got, or null
     * when `p_<code>` was taken too and the column is not offered at all. What `webx:doctor`
     * warns about: a file that names the column by the property's code fills the core's instead.
     *
     * @return array<string, string|null>
     */
    public function renamed(): array
    {
        return $this->gather()[1];
    }

    /**
     * @return array{0: array<string, ExchangeColumn>, 1: array<string, string|null>}
     */
    private function gather(): array
    {
        $all = $this->columns;
        $renamed = [];

        foreach ($this->providers as $provider) {
            $provider = is_string($provider) ? $this->container->make($provider) : $provider;

            foreach ($provider->columns() as $column) {
                $own = $column->key();
                $key = $own;

                if (isset($all[$key])) {
                    $key = 'p_'.$key;
                    $column = new RenamedColumn($column, $key);
                    $renamed[$own] = $key;
                }

                // Two properties that both became `p_…`: the second is not a column until renamed.
                if (isset($all[$key])) {
                    $renamed[$own] = null;

                    continue;
                }

                $all[$key] = $column;
            }
        }

        return [$all, $renamed];
    }

    public function find(string $key): ?ExchangeColumn
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The columns whoever asks may use: the ones whose field is on the form they would see.
     *
     * @param  (callable(string): bool)|null  $can  null — nobody to ask, everything
     * @return array<string, ExchangeColumn>
     */
    public function available(?callable $can = null): array
    {
        $root = $this->screens->tree(Product::SCREEN);
        $everything = self::names($root);
        $visible = $can === null ? $everything : self::names(Tree::filter($root, $can));
        $available = [];

        foreach ($this->all() as $key => $column) {
            $field = $column->field();

            if ($field === '' || $column instanceof WritesProduct) {
                $available[$key] = $column;

                continue;
            }

            if (! isset($everything[$field])) {
                throw new LogicException("The exchange column [{$key}] fills [{$field}], which is not a field of the product form: register the column only when its field is there.");
            }

            if (isset($visible[$field])) {
                $available[$key] = $column;
            }
        }

        return $available;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, true>
     */
    private static function names(array $nodes): array
    {
        $names = [];

        foreach (Tree::fields($nodes) as $node) {
            $names[(string) $node['name']] = true;
        }

        return $names;
    }
}
