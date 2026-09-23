<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\Settings;

/**
 * The general settings filled in — one key, the one the panel puts in its corner.
 *
 * What is worth a test is the cache: the table is read whole and kept, so a value written by
 * anything other than {@see Settings::save()} — a seeder, a removal, a console one-liner — is
 * invisible until something forgets it.
 */
final class DemoTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_gives_the_panel_a_name_and_takes_it_back(): void
    {
        $settings = $this->app->make(Settings::class);

        // Read before the demo runs, so the cache holds the empty table.
        $this->assertNull($settings->get('general.project-name'));

        $this->artisan('webx:demo')->assertSuccessful();

        $this->assertSame('Demo site', $settings->get('general.project-name'));

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Setting::query()->count());
        $this->assertNull($settings->get('general.project-name'));
    }

    #[Test]
    public function a_value_somebody_has_already_written_is_left_alone(): void
    {
        Setting::query()->create(['key' => 'general.project-name', 'value' => ['en' => 'The real site']]);

        $this->artisan('webx:demo')->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame('The real site', $this->app->make(Settings::class)->get('general.project-name'));
    }
}
