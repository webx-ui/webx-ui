<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Models\RecipeCategory;

/**
 * Recipes, for whatever points at them — first of all another recipe: the similar ones an editor
 * chose (§5.6). The picker tells two recipes apart by their categories and their cover.
 */
final class RecipeTarget extends RelationTarget
{
    public function __construct()
    {
        parent::__construct(
            key: Recipe::TYPE,
            model: Recipe::class,
            permission: 'recipes.view',
            label: 'webx-recipes::relations.recipe',
        );
    }

    /**
     * @return Builder<Model>
     */
    public function query(): Builder
    {
        /** @var Builder<Model> $query */
        $query = Recipe::query()->with('categories');

        return $query;
    }

    protected function subtitle(Model $record, string $locale): ?string
    {
        if (! $record instanceof Recipe) {
            return null;
        }

        $names = $record->categories
            ->map(static fn (RecipeCategory $category): string => $category->displayName($locale))
            ->all();

        return $names === [] ? null : implode(', ', $names);
    }

    protected function thumb(Model $record): ?string
    {
        $cover = $record instanceof Recipe ? $record->cover() : null;
        $thumb = $cover['thumb'] ?? $cover['url'] ?? null;

        return is_string($thumb) ? $thumb : null;
    }
}
