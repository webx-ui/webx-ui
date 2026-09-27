<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Relations;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Admin\Collections\RecordQuery;
use WebxUi\Admin\Tests\Fixtures\Categories\Section;

/**
 * A record query over a model with categories named by slug, like services.
 *
 * @extends RecordQuery<Dish>
 */
final class DishQuery extends RecordQuery
{
    /** @param  int|string|Section|iterable<int|string|Section>|null  $categories */
    public function in(int|string|Section|iterable|null $categories): self
    {
        return $this->withCategories($categories);
    }

    protected function newQuery(string $locale): Builder
    {
        return Dish::query();
    }

    protected function categoryModel(): string
    {
        return Section::class;
    }

    protected function cards(array $records, string $locale): array
    {
        return array_map(static fn (Dish $dish): array => ['title' => (string) $dish->getAttribute('title')], $records);
    }
}
