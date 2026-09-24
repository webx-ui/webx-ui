<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures\Relations;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;

/**
 * Dishes offered to a block, filterable by the chefs they are related to — the shape recipes
 * offered by service will have. Remembers the choice it was handed.
 */
final class DishSource implements CollectionSource
{
    public ?Selection $asked = null;

    public function key(): string
    {
        return 'dishes';
    }

    public function title(): string
    {
        return 'Dishes';
    }

    public function categories(): string
    {
        return 'things/sections';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['chef'];
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function items(Selection $selection, string $locale): array
    {
        $this->asked = $selection;

        return $selection->apply(Dish::query())
            ->get()
            ->map(static fn (Dish $dish): array => [
                'id' => $dish->id,
                'anchor' => 'dish-'.$dish->id,
                'categories' => [],
                'title' => $dish->title,
            ])
            ->values()
            ->all();
    }
}
