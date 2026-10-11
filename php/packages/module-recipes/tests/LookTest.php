<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * The catalogue's and the gallery's own inline look is for a site without a theme: under one,
 * resources/theme/recipes.css is the base and the theme has to be the last word on its classes
 * (THEMES §10.2), which an inline `<style>` later in the head would take from it.
 */
final class LookTest extends TestCase
{
    #[Test]
    public function without_a_theme_the_catalogue_and_the_gallery_print_their_inline_look_once(): void
    {
        $this->recipe('porridge', attributes: ['gallery' => [['path' => $this->picture()->path], ['path' => $this->picture('media/ab/cd/oats.jpg')->path]]]);
        $this->recipe('borscht');

        $index = (string) $this->get('/recipes')->assertOk()->getContent();
        $recipe = (string) $this->get('/recipes/porridge')->assertOk()->getContent();

        $this->assertSame(1, substr_count($index, '.wx-recipes__grid { display: grid;'));
        $this->assertSame(1, substr_count($recipe, '.wx-recipe__strip { display: flex;'));
        $this->assertStringContainsString('<ul class="wx-recipe__strip">', $recipe);
        $this->assertStringNotContainsString('style="', $index.$recipe);
    }

    #[Test]
    public function under_a_theme_they_print_no_style_of_their_own(): void
    {
        $this->recipe('porridge', attributes: ['gallery' => [['path' => $this->picture()->path], ['path' => $this->picture('media/ab/cd/oats.jpg')->path]]]);
        $this->recipe('borscht');
        $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('site', storage_path('theme'), true)]));

        $index = (string) $this->get('/recipes')->assertOk()->getContent();
        $recipe = (string) $this->get('/recipes/porridge')->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="wx-recipes__grid">', $index);
        $this->assertStringNotContainsString('.wx-recipes__grid {', $index.$recipe);
        $this->assertStringNotContainsString('.wx-recipe__strip {', $recipe);
    }
}
