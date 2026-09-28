<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Pages\Models\Page;
use WebxUi\Tariffs\Collections\TariffsSource;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Rendering\Amount;

/**
 * `tariffs()` — what a template may show of the tariffs, as cards (§4.1, §4.2) — and the
 * `wx-collection` source that hands over the same cards.
 */
final class HelperTest extends TestCase
{
    #[Test]
    public function every_visible_tariff_in_the_order_of_the_list(): void
    {
        $this->tariff('Starter');
        $this->tariff('Draft', published: false);
        $this->tariff('Growth');
        $this->tariff('Binned')->delete();

        $this->assertSame(['Starter', 'Growth'], $this->names(tariffs()));
        $this->assertCount(2, tariffs());
        $this->assertFalse(tariffs()->isEmpty());
    }

    #[Test]
    public function a_card_carries_what_a_template_prints(): void
    {
        $business = $this->group('For business');
        $tariff = $this->tariff('Combo Starter', categories: [$business], attributes: [
            'badge' => ['en' => '30 HOURS / 25$'],
            'price' => 750,
            'currency' => 'USD',
            'period' => ['en' => '/mo'],
            'features' => [['text' => ['en' => 'Design']], ['text' => ['en' => 'SEO']]],
            'description' => ['en' => 'For a start.'],
            'button_label' => ['en' => 'Get started'],
            'button_link' => self::url('/contacts'),
            'button_variant' => 'secondary',
            'featured' => true,
        ]);
        $tariff->mergeExtra(['note' => 'VAT included'])->save();

        $card = tariffs()->first();

        $this->assertIsArray($card);
        $this->assertSame(
            ['id', 'anchor', 'categories', 'name', 'badge', 'price', 'amount', 'currency', 'symbol', 'period', 'price_text', 'features', 'description', 'button', 'featured', 'service_links', 'fields'],
            array_keys($card),
        );
        $this->assertSame('tariff-'.$tariff->id, $card['anchor']);
        $this->assertSame([$business->id], $card['categories']);
        $this->assertSame('Combo Starter', $card['name']);
        $this->assertSame('30 HOURS / 25$', $card['badge']);
        $this->assertSame(750.0, $card['price']);
        $this->assertSame('750', $card['amount']);
        $this->assertSame('USD', $card['currency']);
        $this->assertSame('$', $card['symbol']);
        $this->assertSame('/mo', $card['period']);
        $this->assertSame('', $card['price_text']);
        $this->assertSame(['Design', 'SEO'], $card['features']);
        $this->assertSame('For a start.', $card['description']);
        $this->assertSame(['label' => 'Get started', 'url' => 'http://localhost/contacts', 'new_tab' => false, 'rel' => null, 'variant' => 'secondary'], $card['button']);
        $this->assertTrue($card['featured']);
        $this->assertSame([], $card['service_links']);
        $this->assertSame(['note' => 'VAT included'], $card['fields']);
    }

    /**
     * Decision 12: nobody is hidden over a language. The short words fall back to the default
     * language, the description does not, a row of the list not written in the language drops
     * out, and a button without a label in the language drops out while the tariff stays.
     */
    #[Test]
    public function in_another_language_the_short_words_fall_back_and_the_rest_drops_out(): void
    {
        $this->tariff('Combo Starter', attributes: [
            'badge' => ['en' => '30 HOURS'],
            'period' => ['en' => '/mo'],
            'price_text' => ['en' => 'On request'],
            'description' => ['en' => 'For a start.'],
            'features' => [
                ['text' => ['en' => 'Design', 'ru' => 'Дизайн']],
                ['text' => ['en' => 'Hosting']],
                ['text' => ['en' => 'SEO', 'ru' => 'SEO']],
            ],
            'button_label' => ['en' => 'Get started'],
            'button_link' => self::url('/contacts'),
        ]);

        $card = tariffs()->locale('ru')->first();

        $this->assertIsArray($card);
        $this->assertSame('Combo Starter', $card['name']);
        $this->assertSame('30 HOURS', $card['badge']);
        $this->assertSame('/mo', $card['period']);
        $this->assertSame('On request', $card['price_text']);
        $this->assertSame('', $card['description']);
        $this->assertSame(['Дизайн', 'SEO'], $card['features']);
        $this->assertNull($card['button']);

        $this->assertNotNull(tariffs()->locale('en')->first()['button'] ?? null);
    }

    #[Test]
    public function a_price_is_written_the_way_the_card_prints_it(): void
    {
        $this->assertSame('750', Amount::format(750.0, 'en'));
        $this->assertSame('12.50', Amount::format(12.5, 'en'));
        $this->assertSame('0', Amount::format(0.0, 'en'), 'zero is a number');
        $this->assertSame('', Amount::format(null, 'en'));
        $this->assertSame('1380', preg_replace('/\D/u', '', Amount::format(1380.0, 'ru')), 'a thousands separator is the language\'s');

        $this->tariff('Enterprise', attributes: ['price_text' => ['en' => 'On request']]);
        $card = tariffs()->first();

        $this->assertIsArray($card);
        $this->assertNull($card['price']);
        $this->assertSame('', $card['amount']);
        $this->assertSame('On request', $card['price_text']);
    }

    #[Test]
    public function a_currency_taken_out_of_the_config_prints_as_its_code(): void
    {
        $this->tariff('Swiss', attributes: ['price' => 10, 'currency' => 'CHF']);
        $this->tariff('Bare', attributes: ['price' => 10]);

        $cards = tariffs()->get();

        $this->assertSame('CHF', $cards[0]['symbol']);
        $this->assertSame('', $cards[1]['symbol']);
        $this->assertNull($cards[1]['currency']);
    }

    #[Test]
    public function a_currency_the_site_added_is_printed_with_its_own_symbol(): void
    {
        config()->set('webx-tariffs.currencies', [...(array) config('webx-tariffs.currencies'), 'GBP' => '£']);

        $this->tariff('London', attributes: ['price' => 99, 'currency' => 'GBP']);

        $this->assertSame('£', tariffs()->first()['symbol'] ?? null);
    }

    #[Test]
    public function the_steps_narrow_the_way_the_spec_says(): void
    {
        $private = $this->group('For individuals');
        $business = $this->group('For business');

        $a = $this->tariff('A', categories: [$business]);
        $b = $this->tariff('B', categories: [$private, $business]);
        $c = $this->tariff('C', categories: [$private]);

        $this->assertSame(['A', 'B'], $this->names(tariffs()->in($business->id)));
        $this->assertSame(['A', 'B'], $this->names(tariffs()->in($business)));
        $this->assertSame(['A', 'B', 'C'], $this->names(tariffs()->in([$private, $business])));
        $this->assertSame(['A', 'B', 'C'], $this->names(tariffs()->in(null)), 'an untouched field is every tariff');
        $this->assertSame([], $this->names(tariffs()->in('business')), 'groups have no slugs: a word is a filter nothing passes');
        $this->assertSame(['C', 'A'], $this->names(tariffs()->only([$c->id, $a->id])));
        $this->assertSame(['A', 'C'], $this->names(tariffs()->except($b)));
        $this->assertSame(['A', 'B'], $this->names(tariffs()->take(2)));
        $this->assertSame(['A', 'B', 'C'], $this->names(tariffs()->take(0)));
        $this->assertSame([], $this->names(tariffs()->relatedTo('service', [])), 'the tariffs of no service are none');

        // One group asked for is its own order, not the order of the whole list.
        Ordering::move(Tariff::class, [$b->id, $a->id], $business->id);
        $this->assertSame(['B', 'A'], $this->names(tariffs()->in($business)));
        $this->assertSame(['A', 'B', 'C'], $this->names(tariffs()));
    }

    #[Test]
    public function related_to_a_service_is_only_its_tariffs(): void
    {
        $seo = $this->service('seo');
        $design = $this->service('design');

        $this->tariff('A')->syncRelated(Tariff::SERVICES, 'service', [$seo->id]);
        $this->tariff('B')->syncRelated(Tariff::SERVICES, 'service', [$design->id]);

        $this->assertSame(['A'], $this->names(tariffs()->relatedTo('service', $seo)));

        $card = tariffs()->relatedTo('service', $seo)->first();
        $this->assertSame([['id' => $seo->id, 'title' => 'Seo', 'url' => 'http://localhost/services/seo']], $card['service_links'] ?? null);
    }

    #[Test]
    public function the_catalogue_is_the_visible_groups_in_order_without_empty_ones(): void
    {
        $private = $this->group('For individuals');
        $business = $this->group('For business');
        $this->group('Hidden', visible: false);
        $empty = $this->group('Empty');

        $this->tariff('A', categories: [$business]);
        $this->tariff('B', categories: [$private]);
        $this->tariff('Draft', published: false, categories: [$empty]);

        $groups = tariffs()->categories();

        $this->assertSame(['For individuals', 'For business'], array_column($groups, 'title'));
        $this->assertSame(['B'], array_column($groups[0]['tariffs'], 'name'));
        $this->assertSame(['A'], array_column($groups[1]['tariffs'], 'name'));

        $this->assertSame(['For business'], array_column(tariffs()->in($business)->categories(), 'title'));
        $this->assertSame([], tariffs()->in('nothing')->categories());
    }

    #[Test]
    public function a_button_to_an_entity_that_is_not_on_the_site_is_no_button(): void
    {
        $draft = $this->page('draft', published: false);
        $live = $this->page('live');

        $entity = static fn (Page $page): array => ['target' => 'entity', 'entity_type' => 'page', 'entity_id' => $page->id, 'url' => null, 'hash' => null, 'new_tab' => true, 'rel' => []];

        $this->tariff('To a draft', attributes: ['button_label' => ['en' => 'Go'], 'button_link' => $entity($draft)]);
        $this->tariff('To a page', attributes: ['button_label' => ['en' => 'Go'], 'button_link' => $entity($live)]);

        $cards = tariffs()->get();

        $this->assertNull($cards[0]['button'], 'a button to a 404 is worse than none');
        $this->assertSame('To a draft', $cards[0]['name'], 'the tariff stays');
        $this->assertIsArray($cards[1]['button']);
        $this->assertStringEndsWith('/live', $cards[1]['button']['url']);
        $this->assertTrue($cards[1]['button']['new_tab']);
        $this->assertSame('noopener noreferrer', $cards[1]['button']['rel']);
    }

    #[Test]
    public function a_look_taken_out_of_the_config_falls_back_to_the_first(): void
    {
        $this->tariff('Old', attributes: ['button_label' => ['en' => 'Go'], 'button_link' => self::url('/go'), 'button_variant' => 'outline']);

        $this->assertSame('primary', tariffs()->first()['button']['variant'] ?? null);
    }

    #[Test]
    public function the_source_hands_over_the_same_cards_and_offers_groups_and_services(): void
    {
        $business = $this->group('For business');
        $this->tariff('A', categories: [$business]);
        $this->tariff('B');

        $source = $this->app->make(CollectionSources::class)->find('tariffs');

        $this->assertInstanceOf(TariffsSource::class, $source);
        $this->assertSame('tariffs/categories', $source->categories());
        $this->assertSame(['service'], $source->relations());
        $this->assertFalse($source->supportsMarkup());
        $this->assertSame('tariffs.view', $source->permission());

        $items = $source->items(Selection::of(['categories' => [$business->id]], $source), 'en');
        $this->assertSame(['A'], array_column($items, 'name'));
    }

    /**
     * @param  iterable<array<string, mixed>>  $cards
     * @return list<string>
     */
    private function names(iterable $cards): array
    {
        $names = [];

        foreach ($cards as $card) {
            $names[] = (string) $card['name'];
        }

        return $names;
    }
}
