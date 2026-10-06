<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Content\ModelContentSource;
use WebxUi\Recipes\Models\Recipe;

/**
 * The recipes' text for the site audit: the lead, the ingredients, the method and the gallery —
 * published and in the draft.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class RecipeContentSource extends ModelContentSource
{
    public function id(): string
    {
        return 'recipes';
    }

    protected function models(): array
    {
        return ['' => Recipe::class];
    }

    protected function columns(Model $model): array
    {
        return ['lead', 'ingredients', 'method', 'gallery'];
    }

    protected function editUrl(Model $model): string
    {
        return '/recipes/'.$model->getKey();
    }
}
