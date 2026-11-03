<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Mcp;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Models\Cart;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliveryPopularLocality;
use WebxUi\Delivery\Sync\SyncDirectory;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * The delivery by its other door (§15): the tools, their scopes and permissions, the quote that
 * explains, the keys that never leave, the dry runs that write nothing, the geography by hand and
 * the popular towns — the panel's services underneath, so the two doors agree.
 */
final class McpTest extends McpTestCase
{
    /** @var list<string> */
    private const array TOOLS = [
        'delivery_methods_list', 'delivery_methods_save', 'delivery_methods_reorder', 'delivery_quote',
        'delivery_points_search', 'delivery_points_save', 'delivery_points_deactivate',
        'delivery_localities_search', 'delivery_countries_list', 'delivery_countries_set', 'delivery_regions_list',
        'delivery_regions_save', 'delivery_localities_save', 'delivery_localities_remove', 'delivery_popular_list',
        'delivery_popular_set', 'delivery_bindings_list', 'delivery_bindings_resolve',
        'delivery_carriers_list', 'delivery_carriers_update', 'delivery_carriers_options', 'delivery_sync_status', 'delivery_sync_run', 'delivery_sync_confirm',
    ];

    #[Test]
    public function the_section_offers_its_tools_behind_its_scopes_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);
        $names = array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('delivery'));

        $this->assertSame(self::TOOLS, $names);
        // Without module-orders there are no parcels, and no tools for them (§19).
        $this->assertNotContains('delivery_shipments_list', $names);

        $this->assertSame(['delivery:read'], $registry->tool('delivery_quote')->scopes());
        $this->assertSame(['delivery:write'], $registry->tool('delivery_localities_save')->scopes());
        $this->assertSame(['delivery.view', 'delivery.manage'], $registry->tool('delivery_popular_list')->permissions());
        $this->assertSame(['delivery.manage'], $registry->tool('delivery_popular_set')->permissions());

        foreach ($registry->toolsOf('delivery') as $tool) {
            if ($tool->tool->mutating) {
                $this->assertArrayHasKey('dry_run', $tool->tool->inputSchema['properties'], $tool->fullName());
            }
        }

        $this->assertContains('delivery://catalog', array_map(static fn (McpResource $resource): string => $resource->uri, $registry->resources()));

        Artisan::call('webx:mcp-tools', ['--module' => 'delivery']);
        $this->assertStringContainsString('delivery_localities_save', Artisan::output());
    }

    #[Test]
    public function a_reader_reads_and_is_refused_a_write(): void
    {
        $reader = $this->signIn(['delivery.view']);

        $this->agent('delivery_popular_list', [], $reader)->assertOk();
        $this->agent('delivery_popular_set', ['localities' => []], $reader)->assertHasErrors(['[delivery.manage]']);
    }

    #[Test]
    public function another_sections_right_is_refused_reads_and_writes(): void
    {
        $stranger = $this->signIn(['payments.view']);

        $this->agent('delivery_popular_list', [], $stranger)->assertHasErrors(['[delivery.view]']);
        $this->agent('delivery_carriers_list', [], $stranger)->assertHasErrors(['[delivery.view]']);
        $this->agent('delivery_popular_set', ['localities' => []], $stranger)->assertHasErrors(['[delivery.manage]']);

        $this->agent('delivery_popular_set', ['localities' => []], $this->signIn(['delivery.manage']))->assertOk();
    }

    #[Test]
    public function the_catalogue_comes_from_the_registries(): void
    {
        $this->app->make(Carriers::class)->register(new FakeCarrier(localities: 0, points: 0));
        $resource = collect($this->app->make(ToolRegistry::class)->resources())->firstWhere('uri', 'delivery://catalog');
        $catalog = ($resource->handler)();

        $this->assertSame([StorePickup::KEY, StoreCourier::KEY], array_column($catalog['types'], 'key'));
        $this->assertSame('point', $catalog['types'][0]['asks']);
        $this->assertContains(FakeCarrier::KEY, array_column($catalog['carriers'], 'key'));
        $this->assertSame('shipping.paid_by_store', $catalog['privileges'][0]['key']);
        $this->assertSame(0.1, $catalog['removal']['threshold_share']);
    }

    #[Test]
    public function a_key_of_a_carrier_never_leaves_and_is_never_taken(): void
    {
        $this->app->make(Carriers::class)->register(new FakeCarrier(localities: 0, points: 0));
        DeliveryCarrier::query()->create(['key' => FakeCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => 'secret-123']]);

        foreach (['delivery_carriers_list' => [], 'delivery_sync_status' => [], 'delivery_carriers_update' => ['carrier' => FakeCarrier::KEY, 'enabled' => true]] as $tool => $arguments) {
            $response = $this->agent($tool, $arguments);
            $response->assertOk()->assertDontSee('secret-123');
        }

        $listed = $this->use('delivery_carriers_list');
        $this->assertSame(['api_key: set'], $listed['carriers'][0]['credentials']);

        // No tool takes a key: an argument it does not name is refused, not dropped.
        $this->agent('delivery_carriers_update', ['carrier' => FakeCarrier::KEY, 'credentials' => ['api_key' => 'x']])->assertHasErrors(['credentials']);
        $this->assertSame('secret-123', DeliveryCarrier::query()->find(FakeCarrier::KEY)?->credentials['api_key']);
    }

    #[Test]
    public function the_quote_says_why_a_method_is_not_offered(): void
    {
        $townA = $this->town('Town A');
        $townB = $this->town('Town B');
        $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']);
        $this->method('our-courier', StoreCourier::KEY, ['price' => 1500, 'position' => 1])->syncLocalities([$townA->id]);
        $this->method('abroad', StoreCourier::KEY, ['price' => 900, 'countries' => ['YY'], 'position' => 2]);
        $this->point($townA);

        $inA = $this->byKey($this->use('delivery_quote', ['locality' => $townA->id, 'items' => [['type' => 'test.goods', 'ref' => 1]]])['methods']);

        $this->assertTrue($inA['store-pickup']['offered']);
        $this->assertSame(1, $inA['store-pickup']['points']);
        $this->assertTrue($inA['our-courier']['offered']);
        $this->assertSame(1500, $inA['our-courier']['quote']['price']);
        $this->assertFalse($inA['abroad']['offered']);
        $this->assertSame('country', $inA['abroad']['reason']);
        $this->assertStringContainsString('country', (string) $inA['abroad']['explanation']);

        $inB = $this->byKey($this->use('delivery_quote', ['locality' => 'Town B'])['methods']);

        $this->assertSame('no_points', $inB['store-pickup']['reason']);
        $this->assertSame('No active point of the method in the locality.', $inB['store-pickup']['explanation']);
        $this->assertFalse($inB['our-courier']['offered']);

        // Nothing of the trial is kept: the lines' cart is rolled back.
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame($townB->id, $this->use('delivery_quote', ['locality' => 'Town B'])['locality']['id']);
    }

    #[Test]
    public function a_dry_run_writes_nothing(): void
    {
        $town = $this->town('Town A');
        $method = $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']);
        $point = $this->point($town);

        $calls = [
            'delivery_methods_save' => ['key' => 'store-pickup', 'values' => ['titles' => 'Renamed', 'price' => 3]],
            'delivery_methods_reorder' => ['methods' => ['store-pickup']],
            'delivery_points_save' => ['values' => ['titles' => 'Store B', 'address' => '2 Example street', 'locality' => $town->id]],
            'delivery_points_deactivate' => ['point' => $point->id],
            'delivery_countries_set' => ['code' => 'XY', 'active' => true],
            'delivery_regions_save' => ['country' => 'XX', 'names' => 'Region C'],
            'delivery_localities_save' => ['values' => ['names' => 'Town C', 'kind' => 'town', 'country' => 'XX', 'region_id' => $town->region_id]],
            'delivery_localities_remove' => ['locality' => $town->id],
            'delivery_popular_set' => ['add' => $town->id],
        ];

        $before = $this->snapshot();

        foreach ($calls as $tool => $arguments) {
            $answer = $this->use($tool, [...$arguments, 'dry_run' => true]);
            $this->assertTrue($answer['dry_run'], $tool);
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(['en' => 'Store pickup'], $method->refresh()->titles);
        $this->assertTrue($point->refresh()->active);
    }

    #[Test]
    public function a_town_like_one_already_there_comes_back_with_the_similar_ones(): void
    {
        $town = $this->town('O’Town');
        $values = ['names' => "O'town", 'kind' => 'town', 'country' => 'XX', 'region_id' => $town->region_id];

        $rehearsed = $this->use('delivery_localities_save', ['values' => $values, 'dry_run' => true]);
        $this->assertTrue($rehearsed['duplicate']);
        $this->assertSame([$town->id], array_column($rehearsed['similar'], 'id'));

        $refused = $this->use('delivery_localities_save', ['values' => $values]);
        $this->assertFalse($refused['created']);
        $this->assertSame([$town->id], array_column($refused['similar'], 'id'));
        $this->assertSame(1, GeoLocality::query()->count());

        $forced = $this->use('delivery_localities_save', ['values' => $values, 'force' => true]);
        $this->assertTrue($forced['created']);
        $this->assertSame('manual', $forced['locality']['created_by']);
        $this->assertSame(2, GeoLocality::query()->count());
    }

    #[Test]
    public function a_shop_without_a_carrier_is_set_up_by_hand(): void
    {
        $this->use('delivery_countries_set', ['code' => 'XX', 'active' => true, 'position' => 0]);
        $region = $this->use('delivery_regions_save', ['country' => 'XX', 'names' => ['en' => 'Region A', 'pl' => 'Region A']])['region'];
        $this->assertSame([$region['id']], array_column($this->use('delivery_regions_list', ['country' => 'XX'])['regions'], 'id'));

        $made = $this->use('delivery_localities_save', ['values' => ['names' => 'Town A', 'kind' => 'town', 'country' => 'XX', 'region_id' => $region['id'], 'weight' => 50]]);
        $id = $made['locality']['id'];
        $this->assertTrue($made['created']);

        $renamed = $this->use('delivery_localities_save', ['locality' => $id, 'values' => ['names' => ['pl' => 'Miasto A']]]);
        $this->assertSame(['en' => 'Town A', 'pl' => 'Miasto A'], $renamed['values']['names']);

        $point = $this->use('delivery_points_save', ['values' => ['titles' => 'Store A', 'address' => '1 Example street', 'locality' => 'Town A', 'phone' => '+800 1234 5678']]);
        $this->assertTrue($point['created']);
        $this->assertSame(1, $this->use('delivery_points_search', ['locality' => $id, 'carrier' => Carriers::STORE])['total']);
        $this->assertSame(1, $this->use('delivery_localities_search', ['q' => 'tow', 'country' => 'XX'])['localities'][0]['points']);

        $method = $this->use('delivery_methods_save', ['type' => StorePickup::KEY, 'values' => ['titles' => 'Pick up at a store', 'enabled' => true]]);
        $this->assertTrue($method['created']);
        $this->assertTrue($this->byKey($this->use('delivery_quote', ['locality' => $id])['methods'])[$method['method']['key']]['offered']);

        // Held by the point: switched off, not deleted — and the answer says who holds it.
        $removed = $this->use('delivery_localities_remove', ['locality' => $id]);
        $this->assertSame('deactivated', $removed['result']);
        $this->assertNotSame([], $removed['held_by']);
        $this->assertFalse(GeoLocality::query()->findOrFail($id)->active);

        $this->assertSame('activated', $this->use('delivery_localities_remove', ['locality' => $id, 'action' => 'activate'])['result']);

        // The point off: kept, out of the checkout.
        $this->assertTrue($this->use('delivery_points_deactivate', ['point' => $point['point']['id']])['deactivated']);
        $this->assertFalse(DeliveryPoint::query()->findOrFail($point['point']['id'])->active);
    }

    #[Test]
    public function the_popular_towns_are_the_panels_list(): void
    {
        $a = $this->town('Town A');
        $b = $this->town('Town B');
        $c = $this->town('Town C');

        $set = $this->use('delivery_popular_set', ['localities' => [$b->id, 'Town A']]);
        $this->assertSame([$b->id, $a->id], array_column($set['popular'], 'id'));

        $rehearsed = $this->use('delivery_popular_set', ['add' => $c->id, 'position' => 0, 'dry_run' => true]);
        $this->assertSame([$b->id, $a->id], array_column($rehearsed['before'], 'id'));
        $this->assertSame([$c->id, $b->id, $a->id], array_column($rehearsed['after'], 'id'));
        $this->assertSame(2, DeliveryPopularLocality::query()->count());

        $this->use('delivery_popular_set', ['remove' => $b->id]);

        // The panel reads what the agent wrote.
        $this->signIn();
        $this->getJson($this->api('popular'))->assertOk()->assertJsonPath('data.0.id', $a->id);
        $this->assertSame([$a->id], array_column($this->use('delivery_popular_list')['popular'], 'id'));
    }

    #[Test]
    public function the_methods_are_saved_and_ordered_by_the_form(): void
    {
        $this->method('store-pickup', StorePickup::KEY, ['point_kind' => 'store']);
        $this->method('our-courier', StoreCourier::KEY, ['price' => 1500, 'position' => 1]);

        $this->agent('delivery_methods_save', ['key' => 'nope', 'values' => []])->assertHasErrors(['Method nope does not exist']);
        $this->agent('delivery_methods_save', ['type' => 'nope', 'values' => []])->assertHasErrors(['Known: store.pickup, store.courier']);

        $saved = $this->use('delivery_methods_save', ['key' => 'our-courier', 'values' => ['price' => 12.5, 'titles' => ['pl' => 'Kurier']]]);
        $this->assertSame(1250, $saved['method']['price']);
        $this->assertSame(['en' => 'Our courier', 'pl' => 'Kurier'], $saved['method']['titles']);

        $this->assertSame(['our-courier', 'store-pickup'], $this->use('delivery_methods_reorder', ['methods' => ['our-courier']])['order']);
        $this->assertSame(['our-courier', 'store-pickup'], array_column($this->use('delivery_methods_list')['methods'], 'key'));
    }

    #[Test]
    public function a_run_goes_to_the_queue_and_a_dry_run_says_what_would(): void
    {
        $this->app->make(Carriers::class)->register(new FakeCarrier(localities: 2, points: 2));
        DeliveryCarrier::query()->create(['key' => FakeCarrier::KEY, 'enabled' => true]);
        Bus::fake();

        $this->assertSame(['localities', 'points'], $this->use('delivery_sync_run', ['carrier' => FakeCarrier::KEY, 'dry_run' => true])['would_run']);
        Bus::assertNothingDispatched();

        $this->assertSame(['points'], $this->use('delivery_sync_run', ['carrier' => FakeCarrier::KEY, 'directory' => 'points'])['queued']);
        Bus::assertDispatched(SyncDirectory::class, static fn (SyncDirectory $job): bool => $job->directories === ['points'] && $job->trigger === 'mcp');

        $this->agent('delivery_sync_run', ['carrier' => 'carrier-z'])->assertHasErrors(['Carrier carrier-z does not exist']);
        $this->agent('delivery_sync_confirm', ['carrier' => FakeCarrier::KEY, 'directory' => 'points'])->assertHasErrors(['not held']);
    }

    /**
     * The methods of a quote by their keys.
     *
     * @return array<string, array<string, mixed>>
     */
    private function byKey(mixed $methods): array
    {
        $this->assertIsArray($methods);
        $keyed = [];

        foreach ($methods as $method) {
            $this->assertIsArray($method);
            $keyed[(string) $method['key']] = $method;
        }

        return $keyed;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'methods' => DeliveryMethod::query()->orderBy('id')->get(['id', 'key', 'titles', 'price', 'position'])->toArray(),
            'points' => DeliveryPoint::query()->orderBy('id')->get(['id', 'active'])->toArray(),
            'countries' => GeoCountry::query()->orderBy('code')->get(['code', 'active'])->toArray(),
            'regions' => GeoRegion::query()->count(),
            'localities' => GeoLocality::query()->orderBy('id')->get(['id', 'active'])->toArray(),
            'popular' => DeliveryPopularLocality::query()->count(),
        ];
    }
}
