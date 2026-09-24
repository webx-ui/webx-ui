<?php

declare(strict_types=1);

use WebxUi\Recipes\Rendering\RecipeQuery;

if (! function_exists('recipes')) {
    /**
     * `recipes()` — the recipes a template may show, as cards (see {@see RecipeQuery}).
     *
     *     recipes()->in('breakfasts')->take(6);
     *     recipes()->relatedTo('service', $service);
     *     recipes()->except($recipe)->take(3);
     *
     * Guarded, because a site may have taken the name first, and redeclaring it would be a fatal
     * error at boot. `php artisan webx:doctor` says when it is not ours.
     */
    function recipes(): RecipeQuery
    {
        return new RecipeQuery;
    }
}
