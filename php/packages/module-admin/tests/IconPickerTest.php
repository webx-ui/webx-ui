<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Admin\Icons\IconSet;
use WebxUi\Admin\Icons\IconSets;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Types\RepeaterType;
use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeIcons;
use WebxUi\Themes\ThemeManifest;

/**
 * `wx-icon-picker` with a set of its own: the icons of the site's theme or a list, spelled out for
 * the panel when the screen is served and held to on save — the panel's set when it names none.
 */
final class IconPickerTest extends TestCase
{
    private const SPRITE = '/themes/icons/abc/icons.svg';

    protected function setUp(): void
    {
        parent::setUp();

        // What the theme of a site-showcase of a property would have: its own names, its own sprite.
        $this->app->make(IconSets::class)->register('theme', static fn (): IconSet => new IconSet(['area', 'bath', 'bed'], self::SPRITE));

        Screens::register('flats.form', [
            ['id' => 'icon', 'type' => 'wx-icon-picker', 'name' => 'icon'],
            ['id' => 'mark', 'type' => 'wx-icon-picker', 'name' => 'mark', 'props' => ['source' => 'theme', 'clearable' => true]],
            ['id' => 'pick', 'type' => 'wx-icon-picker', 'name' => 'pick', 'props' => ['icons' => ['search', 'trash']]],
            ['id' => 'lost', 'type' => 'wx-icon-picker', 'name' => 'lost', 'props' => ['source' => 'nowhere']],
            ['id' => 'features', 'type' => 'wx-repeater', 'name' => 'features', 'children' => [
                ['id' => 'feature-icon', 'type' => 'wx-icon-picker', 'name' => 'icon', 'props' => ['source' => 'theme']],
                ['id' => 'feature-label', 'type' => 'wx-input', 'name' => 'label'],
            ]],
        ]);
    }

    #[Test]
    public function the_panel_gets_the_names_and_the_sprite_in_place_of_the_source(): void
    {
        $nodes = collect(Screens::render('flats.form'))->keyBy('id');

        $this->assertSame(['clearable' => true, 'icons' => ['area', 'bath', 'bed'], 'sprite' => self::SPRITE], $nodes['mark']['props']);
        // Inside a repeater's row as well: the row's fields are the repeater's children.
        $this->assertSame(['icons' => ['area', 'bath', 'bed'], 'sprite' => self::SPRITE], $nodes['features']['children'][0]['props']);
        // Without a source the node is drawn as written: the panel's set, or the list it gives.
        $this->assertArrayNotHasKey('props', $nodes['icon']);
        $this->assertSame(['icons' => ['search', 'trash']], $nodes['pick']['props']);
        // A source nobody registered is an empty set, not the panel's set in disguise.
        $this->assertSame(['icons' => []], $nodes['lost']['props']);

        // What validation reads is the tree as it was written.
        $this->assertSame('theme', collect(Screens::tree('flats.form'))->keyBy('id')['mark']['props']['source']);
    }

    #[Test]
    public function any_short_name_is_kept_without_a_set_as_before(): void
    {
        $stored = $this->save(['icon' => 'anything-a-project-registered'], []);

        $this->assertSame(['icon' => 'anything-a-project-registered'], $stored);
        $this->assertArrayHasKey('icon', $this->refused(['icon' => str_repeat('a', 256)], []));
    }

    #[Test]
    public function a_new_name_outside_the_set_is_refused(): void
    {
        $this->assertSame(['mark' => 'bed', 'pick' => 'trash'], $this->save(['mark' => 'bed', 'pick' => 'trash'], []));

        $errors = $this->refused(['mark' => 'search', 'pick' => 'bed'], []);

        $this->assertSame(['There is no icon "search" in this set. The set has: area, bath, bed.'], $errors['mark']);
        $this->assertSame(['There is no icon "bed" in this set. The set has: search, trash.'], $errors['pick']);
        $this->assertSame(['There is no icon set "nowhere" on this site.'], $this->refused(['lost' => 'bed'], [])['lost']);
        // Emptying the field is always allowed.
        $this->assertSame(['mark' => null], $this->save(['mark' => null], []));
    }

    /* The theme dropped an icon: the field marks it, and the record still saves until somebody
       picks another one. */
    #[Test]
    public function a_name_the_record_already_held_is_let_through(): void
    {
        $this->assertSame(['mark' => 'pool'], $this->save(['mark' => 'pool'], ['mark' => 'pool']));
    }

    #[Test]
    public function a_row_takes_only_the_sets_names_for_a_new_icon(): void
    {
        $held = ['features' => [['icon' => 'pool', 'label' => 'Pool']]];

        // The row that held a dropped icon keeps it, moved or not; a new row with a name from the set is fine.
        $stored = $this->save(['features' => [['icon' => 'bed', 'label' => 'Bedrooms'], ['icon' => 'pool', 'label' => 'Pool']]], $held);
        $this->assertSame([['icon' => 'bed', 'label' => 'Bedrooms'], ['icon' => 'pool', 'label' => 'Pool']], $stored['features']);

        $errors = $this->refused(['features' => [['icon' => 'pool'], ['icon' => 'search']]], $held);
        $this->assertSame(['features.1.icon'], array_keys($errors));
    }

    #[Test]
    public function an_agent_writing_a_row_is_told_the_names(): void
    {
        $repeater = $this->app->make(FieldTypes::class)->get('wx-repeater');
        $this->assertInstanceOf(RepeaterType::class, $repeater);

        $node = collect(Screens::tree('flats.form'))->keyBy('id')['features'];

        $this->assertSame(['area', 'bath', 'bed'], $repeater->itemShape($node)['icon']['options']);
        $this->assertArrayNotHasKey('options', $repeater->itemShape($node)['label']);
    }

    /* The set the frame registers itself: the theme chain's icons, with the sprite once sync published it. */
    #[Test]
    public function the_theme_set_is_the_chains_icons_and_its_published_sprite(): void
    {
        $files = new Filesystem;
        $root = sys_get_temp_dir().'/webx-icon-picker-'.uniqid();
        $files->ensureDirectoryExists($root.'/theme/icons');
        $files->put($root.'/theme/icons/bed.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M3 18h18" stroke="currentColor"/></svg>');
        $files->put($root.'/theme/icons/terrace.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 4h16" stroke="currentColor"/></svg>');

        $icons = new ThemeIcons(new ThemeChain([new ThemeManifest('local', $root.'/theme', true)]), new ThemeAssets($root.'/public'));
        $this->app->instance(ThemeIcons::class, $icons);

        try {
            // A fresh registry: the one this test's setUp() changed is not the frame's.
            $this->app->forgetInstance(IconSets::class);
            $sets = $this->app->make(IconSets::class);

            $before = $sets->get('theme');
            $this->assertNotNull($before);
            $this->assertSame(['bed', 'terrace'], $before->names);
            $this->assertNull($before->sprite, 'nothing to point at before the first sync');

            $icons->publish();
            $sprite = $sets->get('theme')?->sprite;

            $this->assertIsString($sprite);
            $this->assertStringEndsWith('/icons.svg', $sprite);
        } finally {
            $files->deleteDirectory($root);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $held
     * @return array<string, mixed>
     */
    private function save(array $input, array $held): array
    {
        return $this->app->make(ScreenValues::class)->validate('flats.form', $input, null, $held);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $held
     * @return array<string, list<string>>
     */
    private function refused(array $input, array $held): array
    {
        try {
            $this->save($input, $held);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Validation should have failed.');
    }
}
