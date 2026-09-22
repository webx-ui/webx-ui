<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use WebxUi\Menu\Exceptions\MenuException;
use WebxUi\Menu\Menus;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * The menus a site has: the ones its templates ask for, and the ones an administrator made.
 */
class MenusTest extends TestCase
{
    public function test_a_declared_menu_is_listed_before_it_has_a_row(): void
    {
        $this->assertSame(0, Menu::query()->count());
        $this->assertSame(['header', 'footer'], $this->menus()->keys());
        $this->assertTrue($this->menus()->isDeclared('header'));
        $this->assertFalse($this->menus()->isDeclared('sidebar'));
    }

    public function test_a_template_asking_for_a_menu_with_no_row_gets_an_empty_collection(): void
    {
        $this->assertTrue(menu('header')->isEmpty());
        $this->assertSame(0, Menu::query()->count());
    }

    public function test_the_row_of_a_declared_menu_appears_on_first_save(): void
    {
        $menu = $this->menus()->ensure('header');

        $this->assertTrue($menu->exists);
        $this->assertSame('Header', $menu->label());
        $this->assertSame(1, Menu::query()->where('key', 'header')->count());

        // And once, not once per call.
        $this->assertTrue($this->menus()->ensure('header')->is($menu));
        $this->assertSame(1, Menu::query()->count());
    }

    public function test_a_declared_menu_cannot_be_deleted(): void
    {
        $menu = $this->menus()->ensure('footer');

        $this->expectException(MenuException::class);

        try {
            $menu->delete();
        } finally {
            $this->assertSame(1, Menu::query()->where('key', 'footer')->count());
        }
    }

    public function test_a_declared_menu_can_be_emptied(): void
    {
        $menu = $this->menus()->ensure('header');
        $item = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $menu);

        $item->delete();

        $this->assertSame(0, $menu->items()->count());
        $this->assertTrue(Menu::query()->where('key', 'header')->exists());
    }

    public function test_the_key_of_a_declared_menu_cannot_be_renamed(): void
    {
        $menu = $this->menus()->ensure('header');

        $this->expectException(MenuException::class);

        try {
            $menu->update(['key' => 'top']);
        } finally {
            $this->assertTrue(Menu::query()->where('key', 'header')->exists());
        }
    }

    public function test_a_menu_of_ones_own_is_made_and_removed_with_its_items(): void
    {
        $menu = Menu::query()->create(['key' => 'sidebar', 'title' => ['en' => 'Sidebar']]);
        $this->item(['title' => ['en' => 'Contacts'], 'target' => 'url', 'url' => '/contacts'], $menu);

        $this->assertContains('sidebar', $this->menus()->keys());

        $menu->delete();

        $this->assertSame(0, Menu::query()->where('key', 'sidebar')->count());
        $this->assertSame(0, MenuItem::query()->count(), 'Deleting a menu takes its items with it.');
    }

    public function test_a_menu_names_the_looks_it_offers(): void
    {
        $this->assertSame(['link', 'button'], $this->menus()->variants('header'));

        // One that names none gets the site's list, and `link` is always in it.
        $this->assertSame(['link'], $this->menus()->variants('footer'));

        $this->app['config']->set('webx-menu.variants', ['plain', 'button']);

        $this->assertSame(['link', 'plain', 'button'], $this->menus()->variants('footer'));
    }

    private function menus(): Menus
    {
        return $this->app->make(Menus::class);
    }
}
