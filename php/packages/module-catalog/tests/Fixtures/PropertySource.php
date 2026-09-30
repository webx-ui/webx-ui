<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Catalog\Facets\FacetRelevance;
use WebxUi\Catalog\Facets\RelevantFacets;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Search\SearchContributor;

/**
 * Facets from the database, the way the properties will be (§4.3–§4.5 of the properties spec):
 * one table of `(product_id, property, value)`, a facet per property counted in batches, the
 * facets that belong on a page picked by share, and the values searched.
 *
 * It keeps count of what it was asked, so a test can say how often.
 */
final class PropertySource implements RelevantFacets, SearchContributor
{
    public const TABLE = 'test_product_properties';

    public int $asked = 0;

    /** @var list<list<string>> the keys of every batch counted */
    public array $batches = [];

    /** @var list<string> the keys counted one by one */
    public array $singles = [];

    /**
     * @param  list<string>  $properties  in their position
     */
    public function __construct(
        private readonly array $properties,
        public float $minShare = 0.1,
        public int $limit = 8,
    ) {}

    public static function migrate(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id');
            $table->string('property', 32);
            $table->string('value', 32);
        });
    }

    public static function set(Product $product, string $property, string ...$values): void
    {
        foreach ($values as $value) {
            DB::table(self::TABLE)->insert(['product_id' => $product->id, 'property' => $property, 'value' => $value]);
        }
    }

    public function facets(): array
    {
        $this->asked++;

        return array_map(fn (string $property): PropertyFacet => new PropertyFacet($property, $this), $this->properties);
    }

    public function relevant(FilterContext $context, FilterState $state, QueryBuilder $products): array
    {
        $total = max(1, (int) DB::query()->fromSub(clone $products, 'found')->count());
        $having = DB::table(self::TABLE)
            ->whereIn('product_id', clone $products)
            ->groupBy('property')
            ->selectRaw('property, count(distinct product_id) as products')
            ->pluck('products', 'property');

        $shares = [];

        foreach ($this->properties as $property) {
            $shares['p.'.$property] = (int) ($having[$property] ?? 0) / $total;
        }

        return FacetRelevance::ranked($shares, $this->minShare, $this->limit);
    }

    public function applySql(Builder $query, string $text, string $locale): void
    {
        $query->whereIn($query->getModel()->qualifyColumn('id'), DB::table(self::TABLE)->select('product_id')->where('value', $text));
    }
}
