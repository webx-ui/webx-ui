<?php

declare(strict_types=1);

namespace WebxUi\Settings\Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Admin\Screens\Types\StringType;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Settings\Models\Setting;
use WebxUi\Settings\Sections\SettingsSections;
use WebxUi\Settings\Sections\SettingsSectionsCheck;
use WebxUi\Settings\Settings;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeManifest;

/**
 * Branding and Contacts are offered only where something uses them and they can be drawn: a
 * task tracker with no public theme and no file library gets neither, a site on the default
 * theme gets both, as before.
 */
final class SettingsSectionsTest extends TestCase
{
    protected bool $printsContacts = false;

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    /** No theme, no package and no project asking for anything — the tracker. */
    protected function nobodyUsesThem(Application $app): void
    {
        $app['config']->set('webx-settings.uses', []);
    }

    /** The file library is installed: `wx-media` is a type the server knows. */
    protected function withMedia(Application $app): void
    {
        $app->afterResolving(FieldTypes::class, static function (FieldTypes $types): void {
            $types->register('wx-media', new StringType);
        });
    }

    /** A theme that prints contacts and says so, like `theme-default`. */
    protected function themeThatPrintsContacts(Application $app): void
    {
        $this->nobodyUsesThem($app);
        $this->withMedia($app);
        $app->instance(ThemeChain::class, new ThemeChain([
            new ThemeManifest('acme/theme', __DIR__, false, settings: ['contacts' => ['phones', 'emails', 'addresses', 'hours', 'socials']]),
        ]));
    }

    /** A theme written before declarations: it says nothing about `settings`. */
    protected function themeThatSaysNothing(Application $app): void
    {
        $this->nobodyUsesThem($app);
        $app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('acme/old-theme', __DIR__, false)]));
    }

    /** A theme that declared it prints none of the tabs. */
    protected function themeThatPrintsNone(Application $app): void
    {
        $this->nobodyUsesThem($app);
        $app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('acme/bare-theme', __DIR__, false, settings: [])]));
    }

    /** A local theme on the default one that prints no office: no phones, addresses or hours. */
    protected function childThemeThatNarrows(Application $app): void
    {
        $this->nobodyUsesThem($app);
        $this->withMedia($app);
        $app->instance(ThemeChain::class, new ThemeChain([
            new ThemeManifest('theme', __DIR__, true, settings: ['contacts' => ['emails', 'messengers', 'socials']]),
            $this->defaultTheme(),
        ]));
    }

    /** A local theme on the default one that says nothing about settings: it prints the parent's. */
    protected function childThemeThatInherits(Application $app): void
    {
        $this->nobodyUsesThem($app);
        $this->withMedia($app);
        $app->instance(ThemeChain::class, new ThemeChain([new ThemeManifest('theme', __DIR__, true), $this->defaultTheme()]));
    }

    /** The narrowing theme, and a package that prints the main number itself. */
    protected function childThemeThatNarrowsAndAPackageWithPhones(Application $app): void
    {
        $this->childThemeThatNarrows($app);

        $installed = sys_get_temp_dir().'/webx-settings-installed-'.getmypid().'.json';
        file_put_contents($installed, (string) json_encode(['packages' => [
            ['name' => 'acme/call-bar', 'extra' => ['webx' => ['settings' => ['contacts' => ['phones']]]]],
        ]]));
        $app->instance(PackageRegistry::class, new PackageRegistry(new Filesystem, $installed));
    }

    /** The narrowing theme, and a project that still lists addresses in its config. */
    protected function childThemeThatNarrowsAndConfiguredSections(Application $app): void
    {
        $this->childThemeThatNarrows($app);
        $app['config']->set('webx-settings.contacts.sections', ['addresses']);
    }

    /** The project asks for its logo on the panel to be editable, but has no file library. */
    protected function brandingWithoutMedia(Application $app): void
    {
        $app['config']->set('webx-settings.uses', ['branding']);
    }

    #[Test]
    #[DefineEnvironment('nobodyUsesThem')]
    public function a_site_nobody_prints_contacts_on_gets_neither_tab(): void
    {
        $this->assertSame(['branding' => false, 'contacts' => false], $this->visibility());

        $keys = array_column($this->invoke('list'), 'key');
        $this->assertSame([], array_values(array_filter($keys, static fn (string $key): bool => str_starts_with($key, 'contacts.') || str_starts_with($key, 'branding.'))));
        $this->assertContains('general.project-name', $keys);

        $this->assertSame(['ok' => false, 'reason' => 'No setting is named [contacts.phones].'], $this->invoke('get', ['key' => 'contacts.phones']));
        $this->assertFalse($this->invoke('set', ['key' => 'contacts.phones', 'value' => [['number' => '+800 1234 5678']]])['ok']);

        // No demo for a tab nobody sees.
        $this->artisan('webx:demo')->assertSuccessful();
        $this->assertFalse(Setting::query()->where('key', 'like', 'contacts.%')->exists());
        $this->assertTrue(Setting::query()->where('key', 'general.project-name')->exists());

        // Nothing to act on: the doctor says why the tabs are hidden and warns about none.
        $this->assertSame([], array_filter($this->doctor(), static fn (Diagnosis $one): bool => $one->warned()));
    }

    #[Test]
    #[DefineEnvironment('themeThatPrintsContacts')]
    public function a_theme_that_prints_contacts_gets_the_tabs_and_the_sections_it_prints(): void
    {
        $this->assertSame(['branding' => true, 'contacts' => true], $this->visibility());

        $keys = array_column($this->invoke('list'), 'key');

        foreach (['branding.logo', 'contacts.phones', 'contacts.addresses', 'contacts.hours', 'contacts.timezone', 'contacts.socials'] as $key) {
            $this->assertContains($key, $keys);
        }

        // Messengers are neither configured nor declared: hidden from the form and from MCP alike.
        $this->assertNotContains('contacts.messengers', $keys);

        $this->artisan('webx:demo')->assertSuccessful();
        $this->assertFalse(contacts()->hours()->isEmpty());
        $this->assertCount(4, contacts()->socials());

        $this->assertStringContainsString('theme acme/theme', $this->said('Settings: contacts'));
    }

    #[Test]
    #[DefineEnvironment('themeThatSaysNothing')]
    public function a_theme_that_says_nothing_keeps_contacts_while_the_transition_lasts(): void
    {
        $this->assertTrue($this->visibility()['contacts']);
        $this->assertContains('contacts.phones', array_column($this->invoke('list'), 'key'));
        $this->assertStringContainsString('has not declared', $this->said('Settings: contacts'));
    }

    #[Test]
    #[DefineEnvironment('nobodyUsesThem')]
    public function stored_contacts_without_a_theme_keep_the_tab_and_the_doctor_asks_for_a_declaration(): void
    {
        Setting::query()->create(['key' => 'contacts.phones', 'value' => [['number' => '+800 1234 5678']]]);
        $this->rebuild();

        $this->assertTrue($this->visibility()['contacts']);

        $warning = $this->warning('Settings: contacts');
        $this->assertNotNull($warning);
        $this->assertStringContainsString("'uses' => ['contacts']", $warning->detail);
    }

    #[Test]
    #[DefineEnvironment('childThemeThatNarrows')]
    public function a_child_theme_that_lists_its_sections_replaces_its_parents(): void
    {
        $keys = array_column($this->invoke('list'), 'key');

        foreach (['contacts.emails', 'contacts.messengers', 'contacts.socials'] as $key) {
            $this->assertContains($key, $keys);
        }

        // The parent's phones, addresses and hours — with special dates and the time zone — are gone.
        foreach (['contacts.phones', 'contacts.addresses', 'contacts.hours', 'contacts.hours-exceptions', 'contacts.timezone'] as $key) {
            $this->assertNotContains($key, $keys);
        }

        $tree = $this->app->make(ScreenRegistry::class)->tree(Settings::SCREEN);
        $this->assertFalse(Tree::find($tree, 'contacts-addresses-card')['visible'] ?? true);
        $this->assertFalse(Tree::find($tree, 'contacts-hours-card')['visible'] ?? true);

        $said = $this->said('Settings: contacts');
        $this->assertStringContainsString('used by theme theme.', $said);
        $this->assertStringContainsString('emails, messengers, socials (theme theme)', $said);
        $this->assertStringNotContainsString('webx-ui/theme-default', $said);
    }

    #[Test]
    #[DefineEnvironment('childThemeThatInherits')]
    public function a_child_theme_that_says_nothing_keeps_its_parents_sections(): void
    {
        $this->assertSame(
            ['phones', 'emails', 'addresses', 'hours', 'socials'],
            $this->app->make(SettingsSections::class)->contactSections(),
        );

        $keys = array_column($this->invoke('list'), 'key');
        $this->assertContains('contacts.addresses', $keys);
        $this->assertContains('contacts.timezone', $keys);
        $this->assertStringContainsString('(theme webx-ui/theme-default)', $this->said('Settings: contacts'));
    }

    #[Test]
    #[DefineEnvironment('childThemeThatNarrowsAndAPackageWithPhones')]
    public function a_package_adds_its_sections_to_a_narrowing_theme(): void
    {
        $this->assertSame(
            ['emails', 'messengers', 'socials', 'phones'],
            $this->app->make(SettingsSections::class)->contactSections(),
        );

        $keys = array_column($this->invoke('list'), 'key');
        $this->assertContains('contacts.phones', $keys);
        $this->assertNotContains('contacts.addresses', $keys);
        $this->assertStringContainsString('phones (package acme/call-bar)', $this->said('Settings: contacts'));
    }

    #[Test]
    #[DefineEnvironment('childThemeThatNarrowsAndConfiguredSections')]
    public function the_projects_listed_sections_are_added_to_a_narrowing_theme(): void
    {
        $this->assertSame(
            ['addresses', 'emails', 'messengers', 'socials'],
            $this->app->make(SettingsSections::class)->contactSections(),
        );
        $this->assertStringContainsString('addresses (config/webx-settings.php)', $this->said('Settings: contacts'));
    }

    #[Test]
    #[DefineEnvironment('brandingWithoutMedia')]
    public function branding_without_the_file_library_is_hidden_and_a_declared_one_is_warned_about(): void
    {
        $this->assertFalse($this->visibility()['branding']);
        $this->assertNotContains('branding.logo', array_column($this->invoke('list'), 'key'));

        $warning = $this->warning('Settings: branding');
        $this->assertNotNull($warning);
        $this->assertStringContainsString('config/webx-settings.php', $warning->detail);
        $this->assertStringContainsString('webx-ui/module-media', $warning->detail);
    }

    #[Test]
    #[DefineEnvironment('nobodyUsesThem')]
    public function branding_the_site_never_asked_for_is_hidden_without_a_warning(): void
    {
        $this->assertFalse($this->visibility()['branding']);
        $this->assertNull($this->warning('Settings: branding'));
    }

    #[Test]
    #[DefineEnvironment('themeThatPrintsNone')]
    public function a_hidden_tab_keeps_its_values_and_brings_them_back(): void
    {
        Setting::query()->create(['key' => 'contacts.phones', 'value' => [['number' => '+800 1234 5678', 'label' => ['ru' => 'Отдел продаж'], 'primary' => true, 'messengers' => []]]]);
        Setting::query()->create(['key' => 'general.project-name', 'value' => ['ru' => 'Акме']]);
        $this->rebuild();

        // The theme said it prints none: stored contacts do not bring the tab back.
        $this->assertFalse($this->visibility()['contacts']);

        // The code still reads them, by their type: the label in the site's language.
        $this->assertSame('Отдел продаж', contacts()->primaryPhone()?->label);

        $this->actingAs($this->editor(), 'cms');

        $this->assertArrayNotHasKey('contacts.phones', $this->getJson('/api/cms/settings')->assertOk()->json('data.values'));

        // Saving the form leaves the hidden value alone.
        $this->putJson('/api/cms/settings', ['values' => ['general.project-name' => ['ru' => 'Акме 2']]])->assertOk();
        $this->assertSame('+800 1234 5678', Setting::query()->where('key', 'contacts.phones')->value('value')[0]['number']);

        // A consumer turns up — the project declares contacts — and the tab is back with them.
        config()->set('webx-settings.uses', ['contacts']);
        $this->rebuild();

        $this->assertTrue($this->visibility()['contacts']);
        $this->assertSame('+800 1234 5678', $this->getJson('/api/cms/settings')->assertOk()->json('data.values')['contacts.phones'][0]['number'] ?? null);
    }

    /** What `theme-default` declares, as the base of a chain. */
    private function defaultTheme(): ThemeManifest
    {
        return new ThemeManifest('webx-ui/theme-default', __DIR__, false, settings: ['contacts' => ['phones', 'emails', 'addresses', 'hours', 'socials']]);
    }

    /**
     * Whether each optional tab is drawn: the node is in the tree either way.
     *
     * @return array{branding: bool, contacts: bool}
     */
    private function visibility(): array
    {
        $tree = $this->app->make(ScreenRegistry::class)->tree(Settings::SCREEN);

        return [
            'branding' => (Tree::find($tree, 'branding')['visible'] ?? true) !== false,
            'contacts' => (Tree::find($tree, 'contacts')['visible'] ?? true) !== false,
        ];
    }

    /**
     * What the site starts with is worked out when the tree is built; a test that changes it after
     * boot asks for the tree again, as the next request of a deployed site would.
     */
    private function rebuild(): void
    {
        $this->app->make(Settings::class)->forget();
        $this->app->make(ScreenRegistry::class)->extend(Settings::SCREEN, []);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function invoke(string $name, array $arguments = []): mixed
    {
        return ($this->app->make(ToolRegistry::class)->tool("settings_{$name}")->tool->handler)($arguments);
    }

    /** @return list<Diagnosis> */
    private function doctor(): array
    {
        return $this->app->make(SettingsSectionsCheck::class)->run();
    }

    private function said(string $subject): string
    {
        foreach ($this->doctor() as $diagnosis) {
            if ($diagnosis->subject === $subject) {
                return $diagnosis->detail;
            }
        }

        return '';
    }

    private function warning(string $subject): ?Diagnosis
    {
        foreach ($this->doctor() as $diagnosis) {
            if ($diagnosis->subject === $subject && $diagnosis->warned()) {
                return $diagnosis;
            }
        }

        return null;
    }
}
