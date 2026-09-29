<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use LogicException;
use WebxUi\Catalog\Models\Product;

/**
 * The core's sorts (§7.2, §9): each a list of steps over the columns the core owns.
 *
 * One class rather than six because they differ in nothing but the steps. `score` lives in its
 * own table (§3) and is joined only by the sorts that need it; a product nobody has looked at
 * yet has no row there and counts as nought.
 */
final class CoreSort implements Sort
{
    /** The columns a step may name, as the default sort's config spells them. */
    public const COLUMNS = ['priority', 'score', 'created_at', 'price', 'name'];

    /**
     * @param  array<string, 'asc'|'desc'>  $steps
     */
    public function __construct(
        private readonly string $key,
        private readonly array $steps,
    ) {
        foreach (array_keys($steps) as $column) {
            if (! in_array($column, self::COLUMNS, true)) {
                throw new LogicException('A sort step is one of '.implode(', ', self::COLUMNS)."; [{$column}] is not.");
            }
        }
    }

    /**
     * The default: the steps of `webx-catalog.default_sort` — the hand-set priority, then
     * popularity, then the newest (§9).
     *
     * @param  array<mixed>  $steps
     */
    public static function default(array $steps): self
    {
        $clean = [];

        foreach ($steps as $column => $direction) {
            if (is_string($column) && in_array($column, self::COLUMNS, true)) {
                $clean[$column] = $direction === 'asc' ? 'asc' : 'desc';
            }
        }

        return new self(Sorts::DEFAULT, $clean === [] ? ['priority' => 'desc', 'created_at' => 'desc'] : $clean);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return (string) __('webx-catalog::storefront.sort-'.str_replace('_', '-', $this->key));
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, string $locale): void
    {
        $table = $query->getModel()->getTable();

        if (array_key_exists('score', $this->steps)) {
            $query->leftJoin('catalog_product_popularity as popularity', static function (JoinClause $join) use ($table): void {
                $join->on('popularity.product_id', '=', $table.'.id');
            });
        }

        $grammar = $query->getQuery()->getGrammar();

        foreach ($this->steps as $column => $direction) {
            match ($column) {
                'score' => $query->orderByRaw('coalesce('.$grammar->wrap('popularity.score').', 0) '.$direction),
                'name' => $query->orderByTranslation('name', $direction, $locale),
                // A product without a price is at the end both ways: "cheapest first" that opens
                // with twenty "price on request" is not what anybody asked for.
                'price' => $query->orderByRaw($grammar->wrap($table.'.price').' is null')->orderBy($table.'.price', $direction),
                default => $query->orderBy($table.'.'.$column, $direction),
            };
        }
    }

    /**
     * @return array<string, 'asc'|'desc'>
     */
    public function indexOrder(string $locale): array
    {
        $order = [];

        foreach ($this->steps as $column => $direction) {
            $order[$column === 'name' ? 'name_'.$locale : $column] = $direction;
        }

        return $order;
    }
}
