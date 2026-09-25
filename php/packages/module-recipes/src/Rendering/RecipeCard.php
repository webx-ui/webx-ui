<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Rendering;

use Illuminate\Container\Container;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\BlockShapes;
use WebxUi\Localization\Locales;
use WebxUi\Recipes\Models\Recipe;

/**
 * The recipe card as a place a site can redraw from the panel (§5 of the components spec).
 *
 * The module's views call `@webxPart(RecipeCard::SLUG, ['card' => $card], RecipeCard::FALLBACK)`:
 * until the site customises the card, that is the partial; once it publishes its own, that. The
 * shape `recipes.card` is what `$card` holds — {@see Cards} — described for the help under the
 * editor and for an agent, with a sample the stage and the checks draw on.
 */
final class RecipeCard
{
    public const SLUG = 'recipe-card';

    public const SHAPE = 'recipes.card';

    public const FALLBACK = 'webx-recipes::partials.card';

    /**
     * Only with `module-blocks`: without it there is nothing to customise, and `@webxPart` prints
     * the partial by itself.
     */
    public static function declare(string $module): void
    {
        if (! class_exists(BlockComponents::class)) {
            return;
        }

        $container = Container::getInstance();

        $container->make(BlockShapes::class)->register(self::SHAPE, self::fields(), static fn (): array => self::sample());

        $container->make(BlockComponents::class)->declare(
            slug: self::SLUG,
            module: $module,
            fallback: self::FALLBACK,
            // Keys, not words: this runs at boot, before a request has a language.
            title: 'webx-recipes::components.card',
            description: 'webx-recipes::components.card-description',
            schema: [['type' => 'wx-data', 'id' => 'card', 'label' => 'trans::webx-recipes::screen.recipe', 'props' => ['shape' => self::SHAPE]]],
        );
    }

    /**
     * Every key of a card, in the order {@see Cards} builds it.
     *
     * @return list<array{name: string, type: string, description: string}>
     */
    public static function fields(): array
    {
        return [
            ['name' => 'id', 'type' => 'int', 'description' => 'The recipe id.'],
            ['name' => 'anchor', 'type' => 'string', 'description' => 'The slug in this language, for an anchor.'],
            ['name' => 'categories', 'type' => 'list<int>', 'description' => 'Ids of the categories, what the block filter compares. Use category_links to print them.'],
            ['name' => 'title', 'type' => 'string', 'description' => 'The title in this language.'],
            ['name' => 'url', 'type' => 'string', 'description' => 'The address of the recipe page.'],
            ['name' => 'lead', 'type' => 'string', 'description' => 'The lead as plain text.'],
            ['name' => 'cover', 'type' => '{url, thumb, width, height, alt}|null', 'description' => 'The first picture of the gallery.'],
            ['name' => 'gallery', 'type' => 'list<{url, thumb, width, height, alt}>', 'description' => 'Every picture, resolved.'],
            ['name' => 'minutes', 'type' => 'int|null', 'description' => 'Total time in minutes; WebxUi\Recipes\Rendering\Duration::format() writes it out.'],
            ['name' => 'servings', 'type' => 'int|null', 'description' => 'How many it serves.'],
            ['name' => 'category_links', 'type' => 'list<{id, title, url}>', 'description' => 'Visible categories with an address, in the order chosen: the first is the main one.'],
            ['name' => 'service_links', 'type' => 'list<{id, title, url}>', 'description' => 'Visible related services, in the order chosen; empty without the services module.'],
            ['name' => 'nutrients', 'type' => 'list<{id, title}>', 'description' => 'What the recipe is rich in, only the ones shown on the site.'],
            ['name' => 'fields', 'type' => 'array<string, mixed>', 'description' => "The project's own fields, by name."],
        ];
    }

    /**
     * The card of the first published recipe — a real one looks like the site. A site with no
     * recipe yet gets a made-up card of the same shape, so a stage is never blank.
     *
     * @return array<string, mixed>
     */
    public static function sample(): array
    {
        $container = Container::getInstance();
        $locale = $container->make(Locales::class)->current();
        $recipe = Recipe::query()->visible()->with(Cards::RELATIONS)->orderBy('position')->first();

        if ($recipe instanceof Recipe) {
            return $container->make(Cards::class)->recipes([$recipe], $locale)[0];
        }

        return [
            'id' => 0,
            'anchor' => 'oatmeal-with-berries',
            'categories' => [0],
            'title' => 'Oatmeal with berries',
            'url' => '#',
            'lead' => 'Creamy oats with frozen berries and a spoon of nut butter — ten minutes from pot to table.',
            'cover' => null,
            'gallery' => [],
            'minutes' => 10,
            'servings' => 1,
            'category_links' => [['id' => 0, 'title' => 'Breakfast', 'url' => '#']],
            'service_links' => [['id' => 0, 'title' => 'Nutrition consultation', 'url' => '#']],
            'nutrients' => [['id' => 0, 'title' => 'Fibre']],
            'fields' => [],
        ];
    }
}
