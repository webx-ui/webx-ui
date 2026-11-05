<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;

/**
 * §3 and §11 of the feeds spec: the API the section of the panel reads and writes, its refusals
 * and who sees the key.
 */
final class ApiTest extends TestCase
{
    /** No brands, stock or properties here: a preset has to stand on what the core alone knows. */
    #[Test]
    public function every_preset_is_kept_on_a_site_with_no_satellites(): void
    {
        foreach (['google', 'json', 'xml', 'csv'] as $preset) {
            $this->actingAs($this->editor(), 'cms')->postJson($this->api(), ['preset' => $preset])
                ->assertCreated()
                ->assertJsonPath('data.preset', $preset);
        }
    }

    #[Test]
    public function a_feed_is_created_with_the_defaults_of_its_format_and_read_back(): void
    {
        $id = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            ...$this->fields(),
            'format' => 'json',
            'filter' => ['price' => ['min' => 10]],
            'schedule' => 'daily',
            'schedule_at' => '03:30',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Spare parts')
            ->assertJsonPath('data.locale', 'en')
            ->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.separator', ',')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sources.mpn.type', 'field')
            ->json('data.id');

        $this->getJson($this->api((string) $id))
            ->assertOk()
            ->assertJsonPath('data.filter.price.min', 10)
            ->assertJsonPath('data.schedule_at', '03:30');
    }

    #[Test]
    public function a_save_changes_what_it_carries_and_leaves_the_key_alone(): void
    {
        $feed = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);
        $key = $feed->access_key;

        $this->actingAs($this->editor(), 'cms')->putJson($this->api((string) $feed->id), [
            'name' => 'Campaign',
            'locale' => 'de',
            'is_active' => false,
            'access_key' => 'chosen-by-the-client',
            'current_build_id' => 7,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Campaign')
            ->assertJsonPath('data.locale', 'de')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.item', '<item>{{name}}</item>');

        $feed->refresh();
        $this->assertSame($key, $feed->access_key);
        $this->assertNull($feed->current_build_id);
    }

    #[Test]
    public function a_feed_gets_a_key_of_forty_characters_and_two_never_share_one(): void
    {
        $first = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);
        $second = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);

        $this->assertSame(40, strlen($first->access_key));
        $this->assertNotSame($first->access_key, $second->access_key);
        $this->assertNotNull($first->key_issued_at);
    }

    #[Test]
    public function the_address_goes_to_a_manager_and_not_to_a_reader(): void
    {
        $feed = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);
        $address = url('feeds/'.$feed->access_key.'.xml');

        $this->actingAs($this->editor(), 'cms')->getJson($this->api((string) $feed->id))
            ->assertJsonPath('data.address', $address);

        $reader = $this->actingAs($this->editor(['catalog.view']), 'cms');

        $one = $reader->getJson($this->api((string) $feed->id))->assertOk()->assertJsonPath('data.address', null);
        $list = $reader->getJson($this->api())->assertOk()->assertJsonPath('data.0.address', null);

        $this->assertStringNotContainsString($feed->access_key, (string) $one->getContent());
        $this->assertStringNotContainsString($feed->access_key, (string) $list->getContent());
    }

    #[Test]
    public function a_reader_cannot_write_and_a_stranger_cannot_read(): void
    {
        $feed = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);

        $this->actingAs($this->editor(['catalog.view']), 'cms');
        $this->postJson($this->api(), $this->fields())->assertForbidden();
        $this->putJson($this->api((string) $feed->id), ['name' => 'No'])->assertForbidden();
        $this->deleteJson($this->api((string) $feed->id))->assertForbidden();

        $this->actingAs($this->editor([]), 'cms')->getJson($this->api())->assertForbidden();
    }

    #[Test]
    public function the_journal_follows_the_settings_and_never_says_the_key(): void
    {
        $this->actingAs($this->editor(), 'cms');
        $id = $this->postJson($this->api(), $this->fields())->assertCreated()->json('data.id');
        $this->putJson($this->api((string) $id), ['name' => 'Campaign'])->assertOk();

        $key = Feed::query()->findOrFail($id)->access_key;
        $rows = DB::table('cms_history')->where('subject_type', Feed::TYPE)->where('subject_id', $id)->get();

        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Campaign', (string) json_encode($rows));
        $this->assertStringNotContainsString($key, (string) json_encode($rows));
    }

    #[Test]
    public function the_list_is_empty_at_first_and_then_shows_the_last_build(): void
    {
        $this->actingAs($this->editor(), 'cms');
        $this->getJson($this->api())->assertOk()->assertJsonPath('data', [])->assertJsonPath('total', 0);

        $feed = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);
        FeedBuild::query()->create(['feed_id' => $feed->id, 'status' => FeedBuild::FAILED]);
        FeedBuild::query()->create(['feed_id' => $feed->id, 'status' => FeedBuild::DONE, 'written' => 12]);

        $this->getJson($this->api())
            ->assertOk()
            ->assertJsonPath('data.0.last_build.status', 'done')
            ->assertJsonPath('data.0.last_build.written', 12);
    }

    #[Test]
    public function a_save_with_a_field_out_of_its_list_is_refused_under_the_field(): void
    {
        $this->actingAs($this->editor(), 'cms');

        $this->postJson($this->api(), [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'format', 'item']);
        $this->postJson($this->api(), [...$this->fields(), 'format' => 'yaml'])->assertUnprocessable()->assertJsonValidationErrors('format');
        $this->postJson($this->api(), [...$this->fields(), 'locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors('locale');
        $this->postJson($this->api(), [...$this->fields(), 'schedule' => 'weekly'])->assertUnprocessable()->assertJsonValidationErrors('schedule');
    }

    #[Test]
    public function the_currency_is_the_sites_and_no_other(): void
    {
        $this->actingAs($this->editor(), 'cms');

        $this->postJson($this->api(), [...$this->fields(), 'currency' => 'eur'])->assertCreated()->assertJsonPath('data.currency', 'EUR');

        $this->postJson($this->api(), [...$this->fields(), 'currency' => 'USD'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.currency.0', 'A feed is in the site’s currency: EUR.');

        config(['webx-catalog.price.currency' => null]);

        $this->postJson($this->api(), [...$this->fields(), 'currency' => 'EUR'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.currency.0', 'The site has no currency: set WEBX_CATALOG_CURRENCY.');
    }

    #[Test]
    public function a_save_brings_the_feed_to_the_currency_the_site_moved_to(): void
    {
        $feed = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);
        config(['webx-catalog.price.currency' => 'USD']);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api((string) $feed->id), ['name' => 'Again'])
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD');
    }

    #[Test]
    public function a_deleted_feed_takes_its_builds_with_it(): void
    {
        $feed = Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);
        FeedBuild::query()->create(['feed_id' => $feed->id]);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api((string) $feed->id))->assertNoContent();

        $this->assertSame(0, Feed::query()->count());
        $this->assertSame(0, FeedBuild::query()->count());
    }

    #[Test]
    public function the_section_stands_in_the_catalogues_group_for_whoever_reads_the_catalogue(): void
    {
        $module = $this->app->make(ModuleRegistry::class)->get('catalog-feeds');

        $this->assertSame('Feeds', $module->title());
        $this->assertSame([], $module->permissions());
    }
}
