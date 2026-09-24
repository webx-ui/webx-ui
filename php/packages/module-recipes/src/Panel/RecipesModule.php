<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

/**
 * Where recipes are written, ordered and published (§5.9): `recipes.view` opens the list,
 * `recipes.manage` writes — the order included, which is a decision about the catalogue as much
 * as a title is.
 */
final class RecipesModule extends RecipesGroup
{
    public const ID = 'recipes';

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-recipes::module.recipes');
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function order(): int
    {
        return 500;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['recipes.view', 'recipes.manage'];
    }
}
