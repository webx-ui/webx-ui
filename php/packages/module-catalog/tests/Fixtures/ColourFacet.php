<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebxUi\Catalog\Facets\AbstractFacet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;

/**
 * A satellite's facet, the way a property would be one: terms from a table of its own.
 */
class ColourFacet extends AbstractFacet
{
    public const TABLE = 'test_product_colours';

    public static function migrate(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id');
            $table->string('colour', 32);
        });
    }

    public static function paint(Product $product, string ...$colours): void
    {
        foreach ($colours as $colour) {
            DB::table(self::TABLE)->insert(['product_id' => $product->id, 'colour' => $colour]);
        }
    }

    public function key(): string
    {
        return 'colour';
    }

    public function kind(): FacetKind
    {
        return FacetKind::Terms;
    }

    public function label(): string
    {
        return 'Colour';
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        $labels = [];

        foreach ($values as $value) {
            $labels[$value] = ucfirst($value);
        }

        return $labels;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        $known = DB::table(self::TABLE)->whereIn('colour', $slugs)->distinct()->pluck('colour')->all();

        return array_combine($known, $known);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $table = $query->getModel()->getTable();

        $query->whereExists(static function (QueryBuilder $painted) use ($table, $value): void {
            $painted->selectRaw('1')
                ->from(self::TABLE)
                ->whereColumn(self::TABLE.'.product_id', $table.'.id')
                ->whereIn(self::TABLE.'.colour', $value->values);
        });
    }

    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        return $products->newQuery()
            ->from(self::TABLE)
            ->select(['product_id', 'colour as value'])
            ->whereIn('product_id', clone $products);
    }
}
