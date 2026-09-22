<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Menu\Demo\MenuDemo;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;
use WebxUi\Menu\Panel\MenuModule;
use WebxUi\Menu\Tests\Fixtures\PagesServiceProvider;

/**
 * The demo content: a header and a footer made out of the pages the run has just created.
 *
 * Seeded by hand rather than through `webx:demo`, because the plan would skip this module here
 * — it names `pages` in `requires()` and there is no pages module in these tests. What the
 * order buys is covered where the plan lives; what is covered here is that the journal is where
 * the pages come from, and that nothing is written down that `--remove` could not undo.
 */
final class DemoTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PagesServiceProvider::class];
    }

    #[Test]
    public function the_demo_waits_for_the_pages(): void
    {
        $this->assertSame(['pages'], $this->app->make(MenuModule::class)->requires());
    }

    #[Test]
    public function it_builds_a_header_and_a_footer_showing_all_three_kinds_of_target(): void
    {
        $ledger = $this->seeded();

        $this->app->make(MenuDemo::class)->seed($ledger);

        $header = $this->tree('header');

        $this->assertCount(2, $header);
        $this->assertSame('entity', $header[0]->target);
        $this->assertSame('page', $header[0]->entity_type);
        // No label of its own: the item is called what the page is called, so renaming the
        // page renames the item.
        $this->assertSame([], $header[0]->getTranslations('title'));

        $this->assertSame('url', $header[1]->target);
        $this->assertSame('https://webx-ui.github.io/webx-ui/', $header[1]->url);
        $this->assertTrue($header[1]->new_tab);
        // The header declares `button`, so the call to action gets it.
        $this->assertSame('button', $header[1]->variant);

        $footer = $this->tree('footer');

        $this->assertSame('none', $footer[0]->target);
        $this->assertTrue($footer[0]->is_heading);
        $this->assertSame('About the site', $footer[0]->getTranslation('title', 'en'));
        $this->assertSame(1, MenuItem::query()->where('parent_id', $footer[0]->getKey())->count());
    }

    #[Test]
    public function a_look_the_menu_never_declared_is_not_given_to_the_call_to_action(): void
    {
        // A site whose configuration names no second variant gets an ordinary link rather than
        // a class its markup does not divide by.
        $this->app['config']->set('webx-menu.menus', ['header' => ['title' => 'Header'], 'footer' => ['title' => 'Footer']]);

        $this->app->make(MenuDemo::class)->seed($this->seeded());

        $this->assertSame('link', $this->tree('header')[1]->variant);
    }

    #[Test]
    public function only_the_items_are_written_down_so_that_the_removal_can_undo_every_entry(): void
    {
        $ledger = $this->seeded();

        $this->app->make(MenuDemo::class)->seed($ledger);

        $kinds = array_column(array_filter(
            $ledger->entries(),
            static fn (array $entry): bool => ($entry['module'] ?? null) === 'menu',
        ), 'type');

        // The rows of `menus` are not in the journal on purpose: the only ones this makes are
        // declared, a declared menu refuses to be deleted because a template names it, and an
        // entry the removal cannot undo is worse than a leftover empty menu.
        $this->assertSame([MenuItem::class], array_values(array_unique($kinds)));
        $this->assertSame(2, Menu::query()->count());
    }

    #[Test]
    public function a_site_that_has_arranged_a_menu_already_is_left_alone(): void
    {
        $this->item(['target' => 'none', 'title' => ['en' => 'Theirs']]);

        $ledger = $this->seeded();

        $this->app->make(MenuDemo::class)->seed($ledger);

        $this->assertSame(1, MenuItem::query()->count());
        $this->assertSame(0, $ledger->countFor('menu'));
    }

    #[Test]
    public function with_nothing_to_point_at_it_says_so_instead_of_building_an_empty_header(): void
    {
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('menu');

        $this->app->make(MenuDemo::class)->seed($ledger);

        $this->assertSame(0, MenuItem::query()->count());
        $this->assertStringContainsString('nothing to point them at', implode(' ', $ledger->takeNotes()));
    }

    /** The journal of a run in which the pages module has just made a page. */
    private function seeded(): DemoLedger
    {
        $ledger = $this->app->make(DemoLedger::class);

        $ledger->forModule('pages');
        $ledger->created($this->thing('about', 'About'), 'about');
        $ledger->forModule('menu');

        return $ledger;
    }

    /**
     * @return list<MenuItem>
     */
    private function tree(string $key): array
    {
        $menu = Menu::query()->where('key', $key)->firstOrFail();

        return MenuItem::query()
            ->where('menu_id', $menu->getKey())
            ->whereNull('parent_id')
            ->ordered()
            ->get()
            ->all();
    }
}
