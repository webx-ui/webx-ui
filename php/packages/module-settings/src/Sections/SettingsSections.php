<?php

declare(strict_types=1);

namespace WebxUi\Settings\Sections;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;
use WebxUi\Admin\Panel\PackageRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Settings\Contacts\ContactSections;
use WebxUi\Settings\Settings;
use WebxUi\Themes\ThemeChain;

/**
 * Which optional tabs of the settings screen this site gets — Branding and Contacts.
 *
 * A tab is offered only when something on the site uses it, and only when what it needs is
 * installed. The users say so themselves, in the same places they already describe themselves:
 *
 * - a theme, under `settings` of its manifest (`extra.webx.theme` / `theme.json`):
 *   `"settings": {"contacts": ["phones", "emails", "hours"]}`. The layer of the chain nearest to
 *   the site that names a tab decides it — a child theme's list replaces its parent's;
 * - a package, under `extra.webx.settings` of its composer.json;
 * - the project, in `webx-settings.uses`.
 *
 * Contacts are `true` or the list of their sections the user prints. The lists of packages and
 * the project join the theme's: they print what they print whatever the theme does. A list in
 * `webx-settings.contacts.sections` joins them too; left null, it is the default sections only
 * where the theme has not listed its own. Branding is used by the panel itself — its corner and the
 * consent screen — so it is always wanted and only its field type can take it away.
 *
 * A tab that is not offered is hidden, not cut out (see {@see ContactSections}): what is stored
 * stays stored and `contacts()` / the panel's logo still read it, and the tab comes back with
 * its values the moment a user appears.
 */
final class SettingsSections
{
    public const string BRANDING = 'branding';

    public const string CONTACTS = 'contacts';

    /** The optional tabs, by the id of their node, with the field types they cannot be drawn without. */
    public const array NEEDS = [
        self::BRANDING => ['wx-media'],
        self::CONTACTS => [],
    ];

    /** Where a missing field type comes from, for the doctor's advice. */
    public const array PROVIDERS = [
        'wx-media' => 'webx-ui/module-media',
    ];

    /** Used by the panel on every site: nobody has to declare it. */
    private const array IMPLICIT = [self::BRANDING];

    public function __construct(
        private readonly Container $app,
        private readonly Config $config,
    ) {}

    /**
     * Who declared each tab, by tab: `config/webx-settings.php`, `theme <name>`, `package <name>`.
     *
     * @return array<string, list<string>>
     */
    public function claimants(): array
    {
        $claimants = [];

        foreach ($this->declarations() as $who => $tabs) {
            foreach (array_keys($tabs) as $tab) {
                $claimants[$tab][] = $who;
            }
        }

        return $claimants;
    }

    /**
     * Why Contacts are kept without a declaration, while the transition lasts — or null.
     *
     * Sites written before declarations existed must not lose the tab on update: one whose theme
     * has not said a word about `settings` yet, and one with no theme but contacts already
     * stored (printed by the project's own views). The rule goes in the next minor.
     *
     * @return 'theme'|'stored'|null
     */
    public function transition(): ?string
    {
        if (isset($this->claimants()[self::CONTACTS])) {
            return null;
        }

        $chain = $this->chain();

        if ($chain !== null && $chain->layers !== []) {
            return $chain->settings() !== null ? null : 'theme';
        }

        return $this->contactsStored() ? 'stored' : null;
    }

    /** Somebody uses the tab — declared, implied by the panel, or kept by the transition. */
    public function used(string $tab): bool
    {
        return in_array($tab, self::IMPLICIT, true)
            || isset($this->claimants()[$tab])
            || ($tab === self::CONTACTS && $this->transition() !== null);
    }

    /** Declared by somebody, rather than implied by the panel or kept by the transition. */
    public function declared(string $tab): bool
    {
        return isset($this->claimants()[$tab]);
    }

    /**
     * The field types the tab needs that nobody registered.
     *
     * @return list<string>
     */
    public function missing(string $tab): array
    {
        $types = $this->app->bound(FieldTypes::class) ? $this->app->make(FieldTypes::class) : null;

        return array_values(array_filter(
            self::NEEDS[$tab] ?? [],
            static fn (string $type): bool => $types === null || ! $types->has($type),
        ));
    }

    public function shown(string $tab): bool
    {
        return $this->used($tab) && $this->missing($tab) === [];
    }

    /**
     * The sections of Contacts the form asks about: the configured ones and every declared list.
     *
     * @return list<string>
     */
    public function contactSections(): array
    {
        return array_values(array_unique(array_merge([], ...array_values($this->contactSources()))));
    }

    /**
     * Who brought each section of Contacts, by who: `config/webx-settings.php`, `theme <name>`
     * (the nearest layer that lists them), `package <name>`, or `default` when nobody listed any
     * and the config leaves the sections to them.
     *
     * @return array<string, list<string>>
     */
    public function contactSources(): array
    {
        $configured = $this->config->get('webx-settings.contacts.sections');
        $declarations = $this->declarations();
        $sources = [];

        if (is_array($configured)) {
            $sources['config/webx-settings.php'] = ContactSections::configured($configured);
        }

        foreach ($declarations as $who => $tabs) {
            $declared = $tabs[self::CONTACTS] ?? true;

            if (is_array($declared)) {
                // A theme or package from somewhere else may name a section this version has not
                // got; the form simply has nothing to show for it.
                $known = array_values(array_intersect($declared, array_keys(ContactSections::NODES)));
                $sources[$who] = [...($sources[$who] ?? []), ...$known];
            }
        }

        // The default fills in only for a site whose theme has not said what it prints: a theme
        // that listed its sections would otherwise get phones and addresses it never shows.
        if (! is_array($configured) && ! $this->themeListed($declarations)) {
            $sources = ['default' => ContactSections::DEFAULT, ...$sources];
        }

        return $sources;
    }

    /**
     * The patch on the settings screen: a tab nobody uses, or one that cannot be drawn, is
     * hidden; the Contacts tab shows the sections asked for.
     *
     * @return list<array<string, mixed>>
     */
    public function patch(): array
    {
        $patch = [];

        foreach (array_keys(self::NEEDS) as $tab) {
            if (! $this->shown($tab)) {
                $patch[] = ['op' => 'set', 'target' => $tab, 'visible' => false];
            }
        }

        if ($this->shown(self::CONTACTS)) {
            array_push($patch, ...ContactSections::patch($this->contactSections()));
        }

        return $patch;
    }

    /**
     * Every declaration, by who made it: the project's config, the theme's chain, the packages.
     *
     * @return array<string, array<string, true|list<string>>>
     */
    private function declarations(): array
    {
        $declarations = [];

        $config = $this->fromConfig($this->config->get('webx-settings.uses', []));

        if ($config !== []) {
            $declarations['config/webx-settings.php'] = $config;
        }

        // Each tab is credited to the layer it was taken from, not to the top one.
        foreach ($this->chain()?->settingsBy() ?? [] as $layer => $tabs) {
            $declarations['theme '.$layer] = $this->known($tabs);
        }

        foreach ($this->app->make(PackageRegistry::class)->packages() as $package) {
            if ($package->settings !== []) {
                $declarations['package '.$package->name] = $this->known($package->settings);
            }
        }

        return array_filter($declarations, static fn (array $tabs): bool => $tabs !== []);
    }

    /**
     * `webx-settings.uses`: a list of tabs, or tabs mapped to `true` or their sections. Loud on
     * a tab it does not know, like `contacts.sections`: a misspelling would hide the tab silently.
     *
     * @return array<string, true|list<string>>
     */
    private function fromConfig(mixed $uses): array
    {
        if (! is_array($uses)) {
            return [];
        }

        $tabs = [];

        foreach ($uses as $key => $value) {
            [$tab, $sections] = is_int($key) ? [(string) $value, true] : [$key, $value];

            if (! array_key_exists($tab, self::NEEDS)) {
                throw new InvalidArgumentException(sprintf(
                    'webx-settings.uses: unknown tab [%s]; known are %s.',
                    $tab,
                    implode(', ', array_keys(self::NEEDS)),
                ));
            }

            $tabs[$tab] = is_array($sections) ? ContactSections::configured($sections) : true;
        }

        return $tabs;
    }

    /**
     * Only the tabs this version has: a theme made for a newer one must not break the screen.
     *
     * @param  array<string, true|list<string>>  $tabs
     * @return array<string, true|list<string>>
     */
    private function known(array $tabs): array
    {
        return array_intersect_key($tabs, self::NEEDS);
    }

    /**
     * Whether a layer of the theme listed the sections of Contacts — `true` leaves them to the config.
     *
     * @param  array<string, array<string, true|list<string>>>  $declarations
     */
    private function themeListed(array $declarations): bool
    {
        foreach ($declarations as $who => $tabs) {
            if (str_starts_with($who, 'theme ') && is_array($tabs[self::CONTACTS] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function chain(): ?ThemeChain
    {
        if (! class_exists(ThemeChain::class) || ! $this->app->bound(ThemeChain::class)) {
            return null;
        }

        return $this->app->make(ThemeChain::class);
    }

    /** Any contact written down — a site that prints them from its own views, before declarations. */
    private function contactsStored(): bool
    {
        try {
            $raw = $this->app->make(Settings::class)->raw();
        } catch (Throwable) {
            // Before the migrations ran there is no table, and nothing stored either.
            return false;
        }

        foreach ($raw as $key => $value) {
            if (str_starts_with((string) $key, self::CONTACTS.'.') && $value !== null && $value !== '' && $value !== []) {
                return true;
            }
        }

        return false;
    }
}
