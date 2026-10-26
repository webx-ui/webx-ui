<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Configurator\Engine\Engine;
use WebxUi\Configurator\Models\Configurator;
use WebxUi\Configurator\Pricing\Quoter;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Form;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The demo shower configurator (§15): what it is made of, that it is a schema the check takes,
 * that it is on the site with its request form — and that `--remove` takes it all away again.
 */
final class DemoTest extends TestCase
{
    /**
     * With the inbox, so that the demo makes its request form.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [ThemeServiceProvider::class, WidgetsServiceProvider::class, ...parent::getPackageProviders($app), InboxServiceProvider::class];
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_the_shower_configurator_published_with_its_form(): void
    {
        $this->artisan('webx:demo', ['--module' => ['configurators']])->assertSuccessful();

        $configurator = Configurator::query()->sole();
        $schema = $configurator->liveSchema();

        $this->assertSame('shower', $configurator->key);
        $this->assertSame(Configurator::STATUS_PUBLISHED, $configurator->status());
        $this->assertSame('configurator-request', $configurator->form);
        $this->assertSame(['name', 'phone', 'comment'], Form::query()->where('slug', 'configurator-request')->sole()->fields()->orderBy('position')->pluck('name')->all());

        $this->assertIsArray($schema);
        $this->assertSame([], $this->app->make(Quoter::class)->problems($schema));

        $counts = array_map(static fn (array $step): int => count(Engine::options($step)) ?: count(Engine::fields($step)), Engine::steps($schema));
        $this->assertSame([8, 3, 9, 3, 6], $counts);

        // The words in both languages of the site, the pictures in the library by their keys.
        $this->assertSame('Душові кабіни', $configurator->getTranslation('title', 'uk'));
        $this->assertSame('Shower enclosures', $configurator->getTranslation('title', 'en'));
        $this->assertEquals(['uk' => 'Скло', 'en' => 'Glass'], $schema['steps'][2]['label']);
        $this->assertSame(11, MediaFile::query()->where('extension', 'svg')->count());
        $this->assertTrue(MediaFile::query()->where('path', $schema['steps'][0]['options'][0]['image'])->exists());
    }

    #[Test]
    public function its_initial_state_has_a_price(): void
    {
        $this->artisan('webx:demo', ['--module' => ['configurators']])->assertSuccessful();

        $quote = $this->app->make(Quoter::class)->quote(Configurator::query()->sole()->liveSchema() ?? [], []);

        // A corner sliding enclosure 900 × 900 × 2000 in extra clear glass, no frosting, chrome:
        // 8000 + 4420 × 3.6 + 300 × 3.6 = 24 992 → 25 000.
        $this->assertSame(['construction' => 'corner_slide', 'h' => 2000, 'w' => 900, 'd' => 900, 'glass' => 'diamond', 'frost' => 'none', 'hardware' => 'chrome'], $quote['normalized']);
        $this->assertSame(25000, $quote['total']);
    }

    #[Test]
    public function remove_takes_it_all_away(): void
    {
        $this->artisan('webx:demo', ['--module' => ['configurators']])->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Configurator::withTrashed()->count());
        $this->assertSame(0, EntityVersion::query()->count());
        $this->assertSame(0, Form::query()->count());
        $this->assertSame(0, MediaFile::query()->count());
    }
}
