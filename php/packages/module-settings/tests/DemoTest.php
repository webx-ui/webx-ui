<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Settings\Contacts\PhoneNumber;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\Settings;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

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

    /** A site that prints every part of its contacts, as the default theme does. */
    protected function printsAllContacts(Application $app): void
    {
        $app['config']->set('webx-settings.uses', ['contacts' => ['phones', 'emails', 'addresses', 'hours', 'messengers', 'socials']]);
    }

    #[Test]
    #[DefineEnvironment('printsAllContacts')]
    public function it_gives_the_panel_a_name_and_takes_it_back(): void
    {
        $settings = $this->app->make(Settings::class);

        // Read before the demo runs, so the cache holds the empty table.
        $this->assertNull($settings->get('general.project-name'));

        $this->artisan('webx:demo')->assertSuccessful();

        // The site speaks Russian first, and the demo brings its words in Russian too.
        $this->assertSame('Демо-сайт', $settings->get('general.project-name'));
        // The house rules a content agent reads come filled in, so the resource is worth reading.
        $this->assertStringStartsWith('Plain and warm', (string) $settings->get('content.tone'));
        // The Contacts tab too, so the theme's header and footer have numbers, hours and networks to show.
        $this->assertCount(3, contacts()->phones());
        $this->assertSame('Отдел продаж', contacts()->primaryPhone()?->label);
        // Only the languages the site has: no English or Polish left in a Russian and Ukrainian site.
        $this->assertSame(['ru'], array_keys((array) Setting::query()->where('key', 'contacts.phones')->value('value')[0]['label']));
        $this->assertFalse(contacts()->hours()->isEmpty());
        // No country's zone: the demo is nobody's office.
        $this->assertSame('UTC', contacts()->hours()->timezone()->getName());
        $this->assertCount(4, contacts()->socials());

        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Setting::query()->count());
        $this->assertNull($settings->get('general.project-name'));
    }

    /**
     * The demo's numbers are `+800` — international, of no country — and still pass the very check
     * a real number does: the check is not loosened for a demo, `+00…` stays refused.
     */
    #[Test]
    public function the_demo_numbers_are_of_no_country_and_still_valid(): void
    {
        $document = json_decode((string) file_get_contents(__DIR__.'/../resources/demo/settings.json'), true);
        $numbers = array_column($document['contacts.phones'], 'number');

        $this->assertNotSame([], $numbers);

        foreach ($numbers as $number) {
            $this->assertStringStartsWith('+800 ', $number);
            $this->assertNull(PhoneNumber::problem($number), $number);
            $this->assertStringStartsWith('tel:+800-', (string) PhoneNumber::parse($number)?->href);
        }

        $this->assertSame(PhoneNumber::NO_COUNTRY, PhoneNumber::problem('+00 000 000-00-00'));

        $this->artisan('webx:demo')->assertSuccessful();

        $phone = contacts()->primaryPhone();
        $this->assertNotNull($phone);
        $this->assertSame('+800 1234 5678', $phone->number);
        $this->assertSame('tel:+800-1234-5678', $phone->href);
    }

    /** A theme's `demo/settings/settings.json` speaks for the module: the theme knows whose contacts it shows. */
    #[Test]
    public function the_theme_brings_settings_of_its_own(): void
    {
        $theme = sys_get_temp_dir().'/webx-settings-demo-theme-'.getmypid();
        @mkdir($theme.'/demo/settings', 0777, true);
        file_put_contents($theme.'/demo/settings/settings.json', (string) json_encode([
            'general.project-name' => ['en' => 'Theme site', 'ru' => 'Сайт темы'],
            'contacts.phones' => [['number' => '+800 8765 4321', 'label' => ['en' => 'Front desk', 'ru' => 'Приёмная'], 'primary' => true, 'messengers' => []]],
        ]));

        try {
            $this->app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('acme/theme', $theme, false)]));

            $this->artisan('webx:demo')->assertSuccessful();

            $this->assertSame('Сайт темы', $this->app->make(Settings::class)->get('general.project-name'));
            $this->assertCount(1, contacts()->phones());
            $this->assertSame('tel:+800-8765-4321', contacts()->primaryPhone()?->href);
            // The theme's file replaces the module's whole: nothing of the module's own is mixed in.
            $this->assertNull($this->app->make(Settings::class)->get('content.tone'));

            $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();
            $this->assertSame(0, Setting::query()->count());
        } finally {
            @unlink($theme.'/demo/settings/settings.json');
            @rmdir($theme.'/demo/settings');
            @rmdir($theme.'/demo');
            @rmdir($theme);
        }
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
