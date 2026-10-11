<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Blocks\LayerBlocks;
use WebxUi\Themes\Blocks\SchemaCompatibility;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeLocator;

/** The block types of the chain as one set (spec §9.2): override by slug, and a schema that may only grow. */
final class LayerBlocksTest extends TestCase
{
    #[Test]
    public function an_upper_layer_replaces_the_type_of_the_same_slug_whole(): void
    {
        $blocks = LayerBlocks::of($this->app->make(ThemeChain::class));
        $set = $blocks->set();

        $this->assertSame(['hero', 'note'], array_keys($set));
        $this->assertSame(self::fixture('site/theme'), $set['hero']->layer);
        $this->assertSame('Hero of the site', $set['hero']->document['title']);
        $this->assertSame('webx-ui/theme-fixture', $set['hero']->below?->layer);
        $this->assertSame('webx-ui/theme-fixture', $set['note']->layer);
        $this->assertNull($set['note']->below);
        $this->assertCount(3, $blocks->all());
        $this->assertSame([], $blocks->problems());
    }

    #[Test]
    public function an_override_that_adds_fields_and_moves_them_into_a_row_is_compatible(): void
    {
        $this->assertSame([], LayerBlocks::of($this->app->make(ThemeChain::class))->incompatible());
    }

    #[Test]
    public function an_override_that_drops_or_retypes_a_field_is_not(): void
    {
        $chain = ThemeChain::resolve(self::fixture('blocks-theme'), $this->app->make(ThemeLocator::class));
        $blocks = LayerBlocks::of($chain);
        $messages = array_column($blocks->incompatible(), 'message');

        $this->assertSame([
            'hero overrides the type of webx-ui/theme-fixture: field "title" was wx-input, is wx-textarea — an override may add fields, never drop or retype one',
            'hero overrides the type of webx-ui/theme-fixture: field "buttons.url" (wx-input) is gone — an override may add fields, never drop or retype one',
        ], $messages);
        $this->assertStringEndsWith('blocks-theme/blocks/list.json', $blocks->problems()[0]['file']);
        $this->assertArrayNotHasKey('list', $blocks->set());
    }

    #[Test]
    public function fields_are_keyed_by_their_ids_under_the_fields_they_sit_in_and_layout_names_nothing(): void
    {
        $this->assertSame(
            ['title' => 'wx-input', 'items' => 'wx-repeater', 'items.label' => 'wx-input'],
            SchemaCompatibility::fields([
                ['id' => 'tabs', 'type' => 'wx-tabs', 'children' => [
                    ['id' => 'first', 'type' => 'wx-tab', 'children' => [['id' => 'title', 'type' => 'wx-input']]],
                ]],
                ['type' => 'wx-divider'],
                ['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'label', 'type' => 'wx-input']]],
            ]),
        );
    }

    #[Test]
    public function a_field_that_becomes_translatable_stays_compatible(): void
    {
        $this->assertSame([], SchemaCompatibility::problems(
            [['id' => 'title', 'type' => 'wx-input']],
            [['id' => 'title', 'type' => 'wx-input', 'localized' => true]],
        ));
    }
}
