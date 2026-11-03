<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryPopularLocality;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\SyncDirectory;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Geography\Models\GeoLocality;

/**
 * The carriers, the binding queue and the popular towns from the panel: a key is never sent
 * back, a run goes to the queue, the queue is worked by the filter, the towns are dragged.
 */
final class CarriersPanelTest extends PanelTestCase
{
    private FakeCarrier $carrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrier = new FakeCarrier(localities: 6, points: 6, pageSize: 10);
        $this->app->make(Carriers::class)->register($this->carrier);
        DeliveryCarrier::query()->create(['key' => FakeCarrier::KEY, 'enabled' => true]);
    }

    /**
     * Another carrier leads `XX`: «Carrier A» follows it, so what it cannot place waits for a person.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-geography.countries.XX.source', 'carrier-lead');
    }

    #[Test]
    public function a_key_is_said_to_be_set_and_never_sent_back(): void
    {
        $this->signIn();

        $this->putJson($this->api('carriers/carrier-a'), ['credentials' => ['api_key' => 'secret-123']])
            ->assertOk()
            ->assertJsonPath('data.credentials.0.set', true)
            ->assertDontSee('secret-123');

        // Blank is «leave it as it is».
        $this->putJson($this->api('carriers/carrier-a'), ['credentials' => ['api_key' => '']])->assertOk();
        $this->assertSame('secret-123', DeliveryCarrier::query()->find(FakeCarrier::KEY)?->credentials['api_key']);

        $this->getJson($this->api('carriers/carrier-a'))->assertOk()->assertDontSee('secret-123');
        $this->getJson($this->api('carriers'))->assertOk()->assertDontSee('secret-123');

        $this->putJson($this->api('carriers/carrier-a'), ['clear' => ['api_key']])
            ->assertOk()
            ->assertJsonPath('data.credentials.0.set', false);
    }

    #[Test]
    public function run_sends_the_directories_to_the_queue_localities_first(): void
    {
        $this->signIn();
        Bus::fake();

        $this->postJson($this->api('carriers/carrier-a/sync/localities'))
            ->assertStatus(202)
            ->assertJsonPath('data.queued', ['localities', 'points']);

        Bus::assertDispatched(SyncDirectory::class, static fn (SyncDirectory $job): bool => $job->directories === ['localities', 'points'] && $job->trigger === 'panel');

        $this->postJson($this->api('carriers/store/sync/localities'))->assertNotFound();
    }

    #[Test]
    public function the_queue_is_worked_by_the_filter_and_by_the_rows_ticked(): void
    {
        $this->signIn();
        $this->app->make(Synchronizer::class)->run(FakeCarrier::KEY, Directory::Localities, Trigger::Command);

        $pending = DeliveryCarrierLocality::query()->where('status', 'pending')->count();
        $this->assertGreaterThan(2, $pending);

        $list = $this->getJson($this->api('bindings?carrier=carrier-a'))->assertOk();
        $this->assertSame($pending, $list->json('total'));
        $first = $list->json('data.0.id');

        $this->postJson($this->api('bindings/batch'), ['action' => 'ignore', 'ids' => [$first]])
            ->assertOk()
            ->assertJsonPath('data.done', 1);

        $this->postJson($this->api('bindings/batch'), ['action' => 'create', 'all' => true, 'carrier' => 'carrier-a'])
            ->assertOk()
            ->assertJsonPath('data.done', $pending - 1)
            ->assertJsonPath('data.left', 0);

        $this->assertSame(0, DeliveryCarrierLocality::query()->where('status', 'pending')->count());
        $this->assertSame('ignored', DeliveryCarrierLocality::query()->find($first)?->status);
        $this->assertSame($pending - 1, GeoLocality::query()->count());
    }

    #[Test]
    public function the_popular_towns_are_added_dragged_and_removed(): void
    {
        $this->signIn();
        $a = $this->town('Town A');
        $b = $this->town('Town B');
        $c = $this->town('Town C');

        $this->postJson($this->api('popular'), ['locality_id' => $a->id])->assertCreated();
        $this->postJson($this->api('popular'), ['locality_id' => $b->id])->assertCreated();
        $this->postJson($this->api('popular'), ['locality_id' => $c->id])->assertCreated();

        $this->putJson($this->api('popular'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertJsonPath('data.*.name', ['Town C', 'Town A', 'Town B']);

        $this->deleteJson($this->api('popular/'.$a->id))->assertOk()->assertJsonPath('data.*.id', [$c->id, $b->id]);

        $this->getJson($this->api('popular'))->assertOk()->assertJsonPath('data.*.id', [$c->id, $b->id]);
        $this->assertSame(2, DeliveryPopularLocality::query()->count());

        $this->postJson($this->api('popular'), ['locality_id' => 999999])->assertUnprocessable();
    }

    /** A reason the base knows by its words; one nobody names — still words, never its key. */
    #[Test]
    public function the_remarks_of_a_pass_are_said_in_words_whatever_the_reason(): void
    {
        $this->signIn();
        DeliverySyncRun::query()->create([
            'carrier' => FakeCarrier::KEY,
            'directory' => Directory::Points,
            'status' => RunStatus::Succeeded,
            'trigger' => Trigger::Panel,
            'warnings' => ['skipped' => ['unknown_locality' => ['count' => 2], 'carrier_a_odd_point' => ['count' => 5]]],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $directories = $this->getJson($this->api('carriers'))->assertOk()->json('data.0.directories');
        $this->assertIsArray($directories);

        $this->assertSame([
            ['key' => 'skipped.unknown_locality', 'count' => 2, 'label' => 'Skipped, of unknown localities: 2'],
            ['key' => 'skipped.carrier_a_odd_point', 'count' => 5, 'label' => 'Skipped, for another reason: 5'],
        ], array_column($directories, 'remarks', 'directory')['points'] ?? null);
    }
}
