<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Exchange\PrivateColumn;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Support\RequestMemo;
use WebxUi\Localization\Locales;
use WebxUi\Templates\Cdata;
use WebxUi\Templates\Variable;
use WebxUi\Templates\Variables;

/**
 * What a template may say about a product (§4 of the feeds spec): the core's variables, the
 * satellites', the ones a site registers, the ones providers make up as they go, and every
 * registered column of the exchange by itself. It lives in the core so that neither a satellite
 * has to know a feed nor a feed a satellite.
 *
 * A name that is asked for twice is a mistake and is refused. Stronger comes first: a registered
 * variable, then a fallback, then a column. `brand` is a slug in a file and a name here, and
 * `availability` means the same thing with a stock satellite and without one, so the core only
 * gives a fallback for it. Provider columns are not variables: their provider says what to expose,
 * as `property.<code>` rather than a bare code.
 */
final class ProductVariables
{
    /** @var array<string, ProductVariable> */
    private array $variables = [];

    /** @var array<string, ProductVariable> */
    private array $fallbacks = [];

    /** @var list<ProductVariableProvider|class-string<ProductVariableProvider>> */
    private array $providers = [];

    public function __construct(
        private readonly Container $container,
        private readonly ExchangeColumns $columns,
        private readonly Locales $locales,
    ) {}

    public function register(ProductVariable $variable): void
    {
        $name = $variable->describe()->name;

        if (isset($this->variables[$name])) {
            throw new InvalidArgumentException("The product variable [{$name}] is registered twice.");
        }

        $this->variables[$name] = $variable;
    }

    /** A variable that gives way to a registered one of its name, whenever that one comes. */
    public function fallback(ProductVariable $variable): void
    {
        $this->fallbacks[$variable->describe()->name] = $variable;
    }

    /**
     * @param  ProductVariableProvider|class-string<ProductVariableProvider>  $provider
     */
    public function provider(ProductVariableProvider|string $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Every variable by name.
     *
     * @return array<string, ProductVariable>
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->columns->registered() as $key => $column) {
            if (! $column instanceof PrivateColumn) {
                $all[$key] = new ColumnVariable($column, $this->locales);
            }
        }

        $all = [...$all, ...$this->fallbacks, ...$this->variables];

        foreach ($this->providers as $provider) {
            $provider = is_string($provider) ? $this->container->make($provider) : $provider;

            foreach ($provider->variables() as $variable) {
                // What a person named in the panel never takes a name from the code.
                $all[$variable->describe()->name] ??= $variable;
            }
        }

        return $all;
    }

    /**
     * What the editor suggests and what a template is checked against.
     *
     * @return Variables<Variable>
     */
    public function variables(): Variables
    {
        return new Variables(array_values(array_map(
            static fn (ProductVariable $variable): Variable => $variable->describe(),
            $this->all(),
        )));
    }

    /**
     * The values of a page of products: product id → name → value, flat, as `Template` takes them.
     *
     * @param  Collection<int, Product>  $products
     * @param  list<string>|null  $only  the names a template uses; null — all of them
     * @param  list<ProductVariable>  $overrides  stronger than the registry for this call: what the
     *                                            settings of one feed decide, a mapping above all
     * @return array<int, array<string, string|list<array<string, string>>|Cdata>>
     */
    public function values(Collection $products, string $locale, ?array $only = null, array $overrides = []): array
    {
        $variables = $this->all();

        foreach ($overrides as $override) {
            $variables[$override->describe()->name] = $override;
        }

        if ($only !== null) {
            $variables = array_intersect_key($variables, array_flip($only));
        }

        $rows = [];

        foreach ($products as $product) {
            $rows[(int) $product->id] = [];
        }

        try {
            foreach ($variables as $name => $variable) {
                if ($rows === []) {
                    break;
                }

                $empty = $variable->describe()->isList() ? [] : '';
                $values = $variable->values($products, $locale);

                foreach (array_keys($rows) as $id) {
                    $rows[$id][$name] = $values[$id] ?? $empty;
                }
            }
        } finally {
            // A build is one job of thousands of pages, and the memo lives as long as the request.
            RequestMemo::forget();
        }

        return $rows;
    }
}
