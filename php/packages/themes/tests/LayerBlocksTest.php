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
    public function an_override_that_retypes_a_field_is_not_and_one_that_leaves_a_field_out_inherits_it(): void
    {
        $chain = ThemeChain::resolve(self::fixture('blocks-theme'), $this->app->make(ThemeLocator::class));
        $blocks = LayerBlocks::of($chain);

        $this->assertSame([
            'hero overrides the type of webx-ui/theme-fixture: field "title" was wx-input, is wx-textarea — an override may add fields, never retype one',
        ], array_column($blocks->incompatible(), 'message'));
        $this->assertSame([
            'hero inherits field "buttons.url" (wx-input) from webx-ui/theme-fixture with its default, and this template does not use it — print it in the template if the block should show it',
        ], array_column($blocks->inherited(), 'message'));
        $this->assertSame(['buttons.url'], $blocks->set()['hero']->inherited);
        $this->assertStringEndsWith('blocks-theme/blocks/list.json', $blocks->problems()[0]['file']);
        $this->assertArrayNotHasKey('list', $blocks->set());
    }

    /**
     * A package adds a field to its type in a minor release; a site's theme overrode the type
     * before. The override goes on working: the field comes with its default and its sample, and
     * the file of the theme is not touched.
     */
    #[Test]
    public function a_field_of_the_package_type_the_override_does_not_describe_is_inherited_with_its_default_and_sample(): void
    {
        $chain = ThemeChain::resolve(self::fixture('inherit-theme'), $this->app->make(ThemeLocator::class));
        $blocks = LayerBlocks::of($chain);
        $hero = $blocks->set()['hero'];

        $this->assertSame([], $blocks->incompatible());
        $this->assertSame(['buttons'], $hero->inherited);
        $this->assertSame(
            ['title' => 'wx-input', 'buttons' => 'wx-repeater', 'buttons.label' => 'wx-input', 'buttons.url' => 'wx-input'],
            SchemaCompatibility::fields($hero->schema()),
        );
        $this->assertSame('Heading', $hero->schema()[0]['label'], 'what the override describes is its own');
        $this->assertSame(['title' => 'Welcome', 'buttons' => []], $hero->document['sample']);
        $this->assertCount(1, $blocks->inherited());
        $this->assertStringNotContainsString('buttons', (string) file_get_contents($hero->file));
    }

    #[Test]
    public function an_inherited_field_stands_after_the_field_it_follows_in_the_lower_schema_or_at_the_end(): void
    {
        $lower = [
            ['id' => 'heading', 'type' => 'wx-input'],
            ['id' => 'text', 'type' => 'wx-textarea'],
            ['id' => 'show_form_description', 'type' => 'wx-switch', 'default' => false],
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [
                ['id' => 'label', 'type' => 'wx-input'],
                ['id' => 'icon', 'type' => 'wx-icon'],
            ]],
        ];
        $upper = [
            ['id' => 'main', 'type' => 'wx-row', 'children' => [
                ['id' => 'heading', 'type' => 'wx-input', 'label' => 'Own heading'],
            ]],
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'label', 'type' => 'wx-input']]],
            ['id' => 'tone', 'type' => 'wx-select'],
        ];

        $result = SchemaCompatibility::inherit($lower, $upper);

        $this->assertSame(['text', 'show_form_description', 'items.icon'], $result['inherited']);
        $this->assertSame([
            ['id' => 'main', 'type' => 'wx-row', 'children' => [
                ['id' => 'heading', 'type' => 'wx-input', 'label' => 'Own heading'],
                ['id' => 'text', 'type' => 'wx-textarea'],
                ['id' => 'show_form_description', 'type' => 'wx-switch', 'default' => false],
            ]],
            ['id' => 'items', 'type' => 'wx-repeater', 'children' => [
                ['id' => 'label', 'type' => 'wx-input'],
                ['id' => 'icon', 'type' => 'wx-icon'],
            ]],
            ['id' => 'tone', 'type' => 'wx-select'],
        ], $result['schema']);

        // The first field of the lower schema has nothing before it: the end of the list.
        $this->assertSame(
            [['id' => 'body', 'type' => 'wx-textarea'], ['id' => 'title', 'type' => 'wx-input']],
            SchemaCompatibility::inherit([['id' => 'title', 'type' => 'wx-input'], ['id' => 'body', 'type' => 'wx-textarea']], [['id' => 'body', 'type' => 'wx-textarea']])['schema'],
        );
    }

    #[Test]
    public function a_described_field_wins_and_under_a_retyped_field_nothing_is_brought(): void
    {
        $lower = [['id' => 'items', 'type' => 'wx-repeater', 'children' => [['id' => 'label', 'type' => 'wx-input']]], ['id' => 'on', 'type' => 'wx-switch', 'default' => true]];
        $upper = [['id' => 'items', 'type' => 'wx-textarea'], ['id' => 'on', 'type' => 'wx-switch', 'default' => false]];

        $result = SchemaCompatibility::inherit($lower, $upper);

        $this->assertSame([], $result['inherited']);
        $this->assertSame($upper, $result['schema']);
        $this->assertSame(['field "items" was wx-repeater, is wx-textarea', 'field "items.label" (wx-input) is gone'], SchemaCompatibility::problems($lower, $result['schema']));
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
