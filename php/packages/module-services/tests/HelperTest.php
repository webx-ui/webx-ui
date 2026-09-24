<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;

/**
 * `services()` — what a template may show of the section, as cards — and the `wx-collection`
 * source and the block built on it.
 */
final class HelperTest extends TestCase
{
    #[Test]
    public function every_visible_service_in_the_order_of_the_whole_list(): void
    {
        $this->service('crowns');
        $this->service('draft', published: false);
        $this->service('implants');
        $gone = $this->service('veneers');
        $gone->delete();

        $this->assertSame(['Crowns', 'Implants'], $this->titles(services()));
        $this->assertCount(2, services());
        $this->assertFalse(services()->isEmpty());
    }

    #[Test]
    public function a_card_carries_what_a_template_prints_and_nothing_to_query(): void
    {
        $category = $this->category('implants');
        $service = $this->service('crowns');
        $service->syncCategories([$category->getKey()]);
        $service->mergeExtra(['price-from' => '120'])->save();

        $card = services()->first();

        $this->assertIsArray($card);
        $this->assertSame((int) $service->getKey(), $card['id']);
        $this->assertSame('crowns', $card['anchor']);
        $this->assertSame([(int) $category->getKey()], $card['categories']);
        $this->assertSame('Crowns', $card['title']);
        $this->assertSame('http://localhost/services/crowns', $card['url']);
        $this->assertSame('', $card['lead']);
        $this->assertNull($card['cover']);
        $this->assertSame(['price-from' => '120'], $card['fields']);
    }

    #[Test]
    public function a_category_is_named_by_id_by_slug_or_by_itself_and_lists_in_its_own_order(): void
    {
        $implants = $this->category('implants');
        $crowns = $this->service('crowns');
        $bridges = $this->service('bridges');
        $this->service('whitening');
        $crowns->syncCategories([$implants->getKey()]);
        $bridges->syncCategories([$implants->getKey()]);

        // The editor drags bridges to the top of the category, not of the whole list.
        DB::table('service_category')->where('service_id', $bridges->getKey())->update(['item_position' => -1]);

        $this->assertSame(['Bridges', 'Crowns'], $this->titles(services()->in('implants')));
        $this->assertSame(['Bridges', 'Crowns'], $this->titles(services()->in($implants->getKey())));
        $this->assertSame(['Bridges', 'Crowns'], $this->titles(services()->in((string) $implants->getKey())));
        $this->assertSame(['Bridges', 'Crowns'], $this->titles(services()->in($implants)));
        $this->assertSame(['Crowns', 'Bridges', 'Whitening'], $this->titles(services()));
    }

    #[Test]
    public function nothing_chosen_is_everything_and_a_slug_nobody_has_is_nothing(): void
    {
        $this->service('crowns');

        $this->assertCount(1, services()->in(null));
        $this->assertCount(1, services()->in([]));
        $this->assertCount(1, services()->in(''));
        $this->assertCount(0, services()->in('no-such-category'));
    }

    #[Test]
    public function only_keeps_the_order_it_was_given_and_except_and_take_narrow_it(): void
    {
        $crowns = $this->service('crowns');
        $implants = $this->service('implants');
        $veneers = $this->service('veneers');

        $this->assertSame(['Veneers', 'Crowns'], $this->titles(services()->only([$veneers->getKey(), $crowns->getKey()])));
        $this->assertSame(['Crowns', 'Veneers'], $this->titles(services()->except($implants)));
        $this->assertSame(['Crowns'], $this->titles(services()->except([])->take(1)));
        $this->assertSame(['Crowns', 'Implants', 'Veneers'], $this->titles(services()->take(0)));
        $this->assertSame([], $this->titles(services()->only([])));
    }

    #[Test]
    public function a_service_with_no_address_in_the_language_is_not_shown_in_it(): void
    {
        $this->useLocales('en', 'uk');
        $crowns = $this->service('crowns');
        $crowns->setTranslation('title', 'uk', 'Коронки')->setTranslation('slug', 'uk', 'koronky')->save();
        $this->service('implants');

        $this->assertSame(['Crowns', 'Implants'], $this->titles(services()));
        $this->assertSame(['Коронки'], $this->titles(services()->locale('uk')));
        $this->assertSame('http://localhost/uk/services/koronky', services()->locale('uk')->first()['url'] ?? null);
    }

    #[Test]
    public function the_catalogue_groups_by_visible_category_in_each_ones_order(): void
    {
        $implants = $this->category('implants');
        $hidden = $this->category('hidden', visible: false);
        $empty = $this->category('empty');
        $crowns = $this->service('crowns');
        $bridges = $this->service('bridges');
        $crowns->syncCategories([$implants->getKey(), $hidden->getKey()]);
        $bridges->syncCategories([$implants->getKey()]);

        $groups = services()->categories();

        $this->assertSame(['Implants'], array_column($groups, 'title'), 'hidden and empty categories are no group');
        $this->assertSame('http://localhost/services/implants', $groups[0]['url']);
        $this->assertSame(['Crowns', 'Bridges'], array_column($groups[0]['services'], 'title'));

        $this->assertSame(['Bridges'], array_column(services()->except($crowns)->categories()[0]['services'], 'title'));
        $this->assertSame(['Crowns'], array_column(services()->take(1)->categories()[0]['services'], 'title'));
        $this->assertSame([], services()->in((int) $empty->getKey())->categories());
    }

    #[Test]
    public function the_number_of_queries_does_not_grow_with_the_list(): void
    {
        $category = $this->category('implants');

        foreach (['a', 'b'] as $slug) {
            $this->service($slug)->syncCategories([$category->getKey()]);
        }

        $few = $this->queries(static fn () => services()->get());

        foreach (['c', 'd', 'e', 'f'] as $slug) {
            $this->service($slug)->syncCategories([$category->getKey()]);
        }

        $this->assertSame($few, $this->queries(static fn () => services()->get()));
    }

    #[Test]
    public function the_collection_source_hands_over_the_same_cards(): void
    {
        $implants = $this->category('implants');
        $this->service('crowns')->syncCategories([$implants->getKey()]);
        $this->service('whitening');

        $source = $this->app->make(CollectionSources::class)->find('services');

        $this->assertNotNull($source);
        $this->assertSame('services/categories', $source->categories());
        $this->assertSame(services()->get(), $source->items(new Selection, 'en'));
        $this->assertSame(['Crowns'], array_column($source->items(new Selection([(int) $implants->getKey()]), 'en'), 'title'));
    }

    #[Test]
    public function the_offered_block_prints_the_cards_with_their_filter(): void
    {
        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['services']])->assertSuccessful();
        $block = Block::query()->where('slug', 'services')->firstOrFail()->load('publishedVersion');
        $this->assertSame('wx-collection', $block->publishedVersion?->schema[1]['type'] ?? null);

        $implants = $this->category('implants');
        $this->service('crowns')->syncCategories([$implants->getKey()]);

        $html = (string) $this->app->make(Renderer::class)->render([[
            'key' => 'k1',
            'type' => 'services',
            'values' => ['title' => ['en' => 'What we do'], 'services' => ['filter' => true]],
        ]]);

        $this->assertStringContainsString('<h2 class="b-services__title">What we do</h2>', $html);
        $this->assertStringContainsString('<a class="b-services__link" href="http://localhost/services/crowns">', $html);
        $this->assertStringContainsString('data-services-categories="'.$implants->getKey().'"', $html);
        $this->assertStringContainsString('data-services-group="'.$implants->getKey().'"', $html);
    }

    /**
     * @param  iterable<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function titles(iterable $cards): array
    {
        $titles = [];

        foreach ($cards as $card) {
            $titles[] = (string) $card['title'];
        }

        return $titles;
    }

    private function queries(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
