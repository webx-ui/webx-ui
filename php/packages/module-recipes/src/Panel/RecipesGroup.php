<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Panel;

use WebxUi\Admin\AbstractModule;
use WebxUi\Recipes\RecipesServiceProvider;

/**
 * What the three sections of the recipes have in common: the group they sit in (§5.9) —
 * Recipes · Categories · Rich in.
 *
 * The group itself is declared by {@see RecipesServiceProvider} in the panel's config at boot
 * rather than shipped as a default: a site that published `webx-admin.php` has its own copy of
 * the list, and a new default would never reach it.
 */
abstract class RecipesGroup extends AbstractModule
{
    /** The id of the navigation group, and the first segment of every permission below. */
    public const GROUP = 'recipes';

    public function group(): ?string
    {
        return self::GROUP;
    }
}
