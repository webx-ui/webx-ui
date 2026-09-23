<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests;

use Illuminate\Support\Facades\DB;
use WebxUi\Menu\MenuCache;
use WebxUi\Routing\Models\Route;

/**
 * The cache, which is the part of this package with the most ways to be quietly wrong.
 *
 * Every test here works the same way: build the menu, change something **behind** the models so
 * that no event fires, and confirm the menu is still the old one — then do the thing that is
 * supposed to forget it and confirm it is not. That way each test proves the subscription
 * rather than proving that reading the database twice gives the same answer.
 *
 * One test per source of a change in §8, because the sources are what is easy to miss: two of
 * the three live in other packages.
 */
class CacheTest extends TestCase
{
    public function test_a_built_menu_is_kept(): void
    {
        $item = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);

        $this->assertSame(['About'], $this->labels());

        $this->rename($item->getKey(), 'Renamed');

        $this->assertSame(['About'], $this->labels(), 'Nothing raised an event, so nothing was forgotten.');
    }

    /**
     * Dragging an item is the one that would have been missed. A move rewrites bounds with one
     * `update` per pass and never raises `updated` — so on `updated` alone this test fails, and
     * reordering a menu would never have forgotten its cache at all.
     */
    public function test_moving_an_item_forgets_the_menu(): void
    {
        $header = $this->menu('header');

        $a = $this->item(['title' => ['en' => 'A'], 'target' => 'url', 'url' => '/a'], $header);
        $b = $this->item(['title' => ['en' => 'B'], 'target' => 'url', 'url' => '/b'], $header);

        $this->assertSame(['A', 'B'], $this->labels());

        $b->insertBefore($a);

        $this->assertSame(['B', 'A'], $this->labels());
    }

    public function test_saving_an_item_forgets_the_menu(): void
    {
        $item = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);

        $this->assertSame(['About'], $this->labels());

        $item->update(['title' => ['en' => 'About us']]);

        $this->assertSame(['About us'], $this->labels());
    }

    public function test_deleting_an_item_forgets_the_menu(): void
    {
        $item = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);

        $this->assertSame(['About'], $this->labels());

        $item->delete();

        $this->assertSame([], $this->labels());
    }

    public function test_saving_the_menu_forgets_it(): void
    {
        $header = $this->menu('header');
        $item = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $header);

        $this->assertSame(['About'], $this->labels());

        $this->rename($item->getKey(), 'Renamed');
        $header->update(['title' => ['en' => 'Top']]);

        $this->assertSame(['Renamed'], $this->labels());
    }

    /**
     * The address registry. A page renamed last week took its menu item with it, and so did
     * every page under it — whose models nobody saved and whose rows were rewritten one by one.
     */
    public function test_a_row_of_the_address_registry_forgets_the_menus_of_its_entity(): void
    {
        $thing = $this->thing('services', 'Services');
        $item = $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()]);

        $this->assertSame(['Services'], $this->labels());

        $this->rename($item->getKey(), 'Renamed');

        Route::query()->create([
            'locale' => 'en',
            'path' => 'services',
            'kind' => Route::CANONICAL,
            'entity_type' => 'thing',
            'entity_id' => $thing->getKey(),
        ]);

        $this->assertSame(['Renamed'], $this->labels());
    }

    /**
     * Publishing changes no address at all, so a subscription that watched only `routes` would
     * pass every other test in this file and leave a freshly published page out of the menu
     * until something unrelated was edited.
     */
    public function test_publishing_an_entity_forgets_the_menu(): void
    {
        $thing = $this->thing('services', 'Services', published: false);
        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()]);

        $this->assertSame([], $this->labels());

        $thing->update(['published' => true]);

        $this->assertSame(['Services'], $this->labels());
        $this->assertSame(0, Route::query()->count(), 'Nothing about the address changed.');
    }

    public function test_renaming_an_entity_without_touching_its_slug_forgets_the_menu(): void
    {
        $thing = $this->thing('services', 'Services');
        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()]);

        $this->assertSame(['Services'], $this->labels());

        $thing->update(['title' => ['en' => 'What we do']]);

        $this->assertSame(['What we do'], $this->labels());
    }

    public function test_forgetting_one_menu_leaves_the_other_alone(): void
    {
        $header = $this->menu('header');
        $footer = $this->menu('footer');

        $inHeader = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $header);
        $inFooter = $this->item(['title' => ['en' => 'Terms'], 'target' => 'url', 'url' => '/terms'], $footer);

        $this->assertSame(['About'], $this->labels('header'));
        $this->assertSame(['Terms'], $this->labels('footer'));

        $this->rename($inHeader->getKey(), 'About us');
        $this->rename($inFooter->getKey(), 'Terms of use');

        // One item saved in the header: the header is rebuilt, the footer is not touched.
        $inHeader->touch();

        $this->assertSame(['About us'], $this->labels('header'));
        $this->assertSame(['Terms'], $this->labels('footer'));
    }

    public function test_an_entity_only_forgets_the_menus_it_stands_in(): void
    {
        $header = $this->menu('header');
        $footer = $this->menu('footer');

        $thing = $this->thing('services', 'Services');
        $this->item(['target' => 'entity', 'entity_type' => 'thing', 'entity_id' => $thing->getKey()], $header);
        $inFooter = $this->item(['title' => ['en' => 'Terms'], 'target' => 'url', 'url' => '/terms'], $footer);

        $this->assertSame(['Services'], $this->labels('header'));
        $this->assertSame(['Terms'], $this->labels('footer'));

        $this->rename($inFooter->getKey(), 'Terms of use');
        $thing->update(['title' => ['en' => 'What we do']]);

        $this->assertSame(['What we do'], $this->labels('header'));
        $this->assertSame(['Terms'], $this->labels('footer'));
    }

    public function test_a_record_expires_on_its_own(): void
    {
        $item = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);

        $this->assertSame(['About'], $this->labels());

        $this->rename($item->getKey(), 'Renamed');
        $this->travel(61)->minutes();

        $this->assertSame(['Renamed'], $this->labels(), 'Anything a subscription missed grows out on its own.');
    }

    /**
     * The switch, and the reason it exists: "my change has not arrived" never looks like a
     * cache, it looks like a broken save.
     */
    public function test_the_cache_can_be_switched_off(): void
    {
        $this->app['config']->set('webx-menu.cache.enabled', false);

        $item = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);

        $this->assertSame(['About'], $this->labels());

        $this->rename($item->getKey(), 'Renamed');

        $this->assertSame(['Renamed'], $this->labels());
        $this->assertNull($this->cache()->builtAt('header'));
    }

    public function test_a_published_config_that_names_one_key_does_not_lose_the_other(): void
    {
        // `mergeConfigFrom` merges one level deep, so a site that published this file before
        // `ttl` existed has a `cache` section with `enabled` in it and nothing else.
        $this->app['config']->set('webx-menu.cache', ['enabled' => true]);

        $this->assertSame(3600, $this->cache()->ttl());
    }

    public function test_the_whole_cache_can_be_dropped_by_hand(): void
    {
        $header = $this->menu('header');
        $footer = $this->menu('footer');

        $inHeader = $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $header);
        $inFooter = $this->item(['title' => ['en' => 'Terms'], 'target' => 'url', 'url' => '/terms'], $footer);

        $this->assertSame(['About'], $this->labels('header'));
        $this->assertSame(['Terms'], $this->labels('footer'));

        $this->rename($inHeader->getKey(), 'About us');
        $this->rename($inFooter->getKey(), 'Terms of use');

        $this->cache()->flush();

        $this->assertSame(['About us'], $this->labels('header'));
        $this->assertSame(['Terms of use'], $this->labels('footer'));
    }

    public function test_the_record_says_when_it_was_built(): void
    {
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about']);

        $this->assertNull($this->cache()->builtAt('header'));

        $this->labels();

        $this->assertNotNull($this->cache()->builtAt('header'), 'The reset button is labelled with this.');

        $this->cache()->forget('header');

        $this->assertNull($this->cache()->builtAt('header'));
    }

    public function test_a_menu_is_forgotten_in_every_language_at_once(): void
    {
        $this->useLocales('en', 'uk');

        $item = $this->item(['title' => ['en' => 'About', 'uk' => 'Про нас'], 'target' => 'url', 'url' => '/about']);

        $this->assertSame(['About'], $this->labels('header', 'en'));
        $this->assertSame(['Про нас'], $this->labels('header', 'uk'));

        $item->update(['title' => ['en' => 'About us', 'uk' => 'Про компанію']]);

        // An administrator thinks about a menu, not about the pair "this menu and Ukrainian".
        $this->assertSame(['About us'], $this->labels('header', 'en'));
        $this->assertSame(['Про компанію'], $this->labels('header', 'uk'));
    }

    public function test_a_menu_nobody_has_made_is_not_cached_as_somebody_elses(): void
    {
        $this->item(['title' => ['en' => 'About'], 'target' => 'url', 'url' => '/about'], $this->menu('header'));

        $this->assertSame([], $this->labels('footer'));
        $this->assertSame(['About'], $this->labels('header'));
    }

    /** A write that goes round the model, which is what every one of these tests needs. */
    private function rename(mixed $id, string $title): void
    {
        DB::table('menu_items')->where('id', $id)->update([
            'title' => (string) json_encode(['en' => $title]),
        ]);
    }

    private function cache(): MenuCache
    {
        return $this->app->make(MenuCache::class);
    }
}
