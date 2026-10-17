<?php

declare(strict_types=1);

namespace WebxUi\Admin\Manifest;

use Illuminate\Contracts\Config\Repository;
use Throwable;
use WebxUi\Admin\Backups\Backups;
use WebxUi\Admin\Contracts\BrandingSource;
use WebxUi\Admin\Contracts\HasNavSection;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Cache\CacheGroups;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Translations\DictionaryBuilder;

/**
 * The one request the admin front end makes before it can draw anything: what this panel is
 * called, where its API lives, which languages it works in, and which modules it has.
 *
 * Half of it is words and the site — the titles of the modules and of the groups in the
 * administrator's language, the branding from the settings, the address of the home, the list of
 * languages — and that half is the expensive one: under FPM every request loads the lang files of
 * every module to say them. It lives on a versioned key ({@see words()}). Nothing in it depends on
 * who asks, beyond the language: what does (a module's `meta`, the badge of the tasks, the date of
 * the last dump) is worked out on every request, so one administrator never reads another's.
 */
final class ManifestBuilder
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Repository $config,
        private readonly Locales $locales,
        private readonly ScreenRegistry $screens,
        private readonly Backups $backups,
        private readonly ?BrandingSource $brand = null,
        private readonly ?SiteUrls $site = null,
        private readonly ?CacheGroups $cache = null,
        private readonly ?DictionaryBuilder $dictionary = null,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     branding: array{logo: array{url: string, width: int|null, height: int|null}|null, mark: array{url: string, width: int|null, height: int|null}|null},
     *     siteUrl: string,
     *     path: string,
     *     apiPath: string,
     *     locale: string,
     *     locales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     panelLocales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     timezone: string,
     *     groups: list<array{id: string, title: string, icon: string|null, order: int, sections: list<array{id: string, title: string, order: int}>}>,
     *     modules: list<array<string, mixed>>,
     *     screens: list<string>,
     *     backup: array{at: string|null, bytes: int|null}|null,
     * }
     */
    public function build(): array
    {
        $locale = $this->locales->resolvePanel($this->locales->current());
        $modules = $this->registry->all();
        $words = $this->words($locale, $modules);

        return [
            // The name the client gave the panel, falling back to the one it was deployed with.
            // It stays in the payload even when there is a logo: it is what the corner shows
            // until the picture loads, what it shows if the picture is gone, and its alt.
            'title' => $words['title'],
            'branding' => $words['branding'],
            // The way from the panel to the site it runs: the home page in the site's main
            // language rather than the one this administrator reads the panel in — the panel is
            // theirs, the site is its visitors'.
            'siteUrl' => $words['siteUrl'],
            'path' => '/'.ltrim((string) $this->config->get('webx-admin.path'), '/'),
            'apiPath' => '/'.ltrim((string) $this->config->get('webx-admin.api_path'), '/'),
            // The language this administrator reads the panel in — their own choice, not the
            // site's, so two people can work on one site in two languages.
            'locale' => $locale,
            // The languages content is written in. Every editing screen is built around this
            // list: one tab, one column or one card per entry.
            'locales' => $words['locales'],
            // What the interface itself can be switched to.
            'panelLocales' => $words['panelLocales'],
            // The site's clock: a date picker shows and takes a moment in it, so an editor abroad
            // reads an event at the hour the site prints rather than at their own.
            'timezone' => $this->timezone(),
            'groups' => $words['groups'],
            'modules' => array_map(fn (Module $module): array => $this->describe($module, $words['modules'][$module->id()] ?? $module->title()), $modules),
            // Only the names: a screen travels on its own, when the page that needs it opens.
            'screens' => $this->screens->names(),
            'backup' => $this->backup(),
        ];
    }

    /**
     * The half of the manifest that is words and the site, on a versioned key: it moves with the
     * settings (the branding), the languages and the addresses (the home), with the screens'
     * group (reset by hand), and — through the name — with the lang files, the panel's config and
     * the modules installed. In the administrator's language, and with nothing else of theirs.
     *
     * @param  list<Module>  $modules
     * @return array{title: string, branding: array<string, mixed>, siteUrl: string, locales: list<array<string, mixed>>, panelLocales: list<array<string, mixed>>, groups: list<array<string, mixed>>, modules: array<string, string>}
     */
    private function words(string $locale, array $modules): array
    {
        $compute = function () use ($modules): array {
            $brand = $this->brand?->branding() ?? new Branding;
            $titles = [];

            foreach ($modules as $module) {
                $titles[$module->id()] = $module->title();
            }

            return [
                'title' => $this->title($brand),
                'branding' => $brand->toArray(),
                'siteUrl' => $this->siteUrl(),
                'locales' => $this->locales->toPayload(),
                'panelLocales' => $this->locales->panel(),
                'groups' => $this->groups(),
                'modules' => $titles,
            ];
        };

        if ($this->cache === null) {
            return $compute();
        }

        $depends = array_values(array_filter(
            [CacheGroups::LOCALES, CacheGroups::ROUTES, CacheGroups::SCREENS, 'settings'],
            fn (string $group): bool => $this->cache->has($group),
        ));

        $name = 'admin.manifest.'.$locale.'.'.md5((string) json_encode([
            $this->dictionary?->version($locale),
            array_map(static fn (Module $module): string => $module::class.'#'.$module->id(), $modules),
            $this->config->get('webx-admin.title'),
            $this->config->get('webx-admin.groups'),
            $this->config->get('webx-localization'),
            $this->config->get('app.url'),
            // The home is an address of the host this request came to: a site answering on two
            // names must not hand one the other's.
            url('/'),
        ]));

        /** @var array{title: string, branding: array<string, mixed>, siteUrl: string, locales: list<array<string, mixed>>, panelLocales: list<array<string, mixed>>, groups: list<array<string, mixed>>, modules: array<string, string>} $words */
        $words = $this->cache->remember($name, $depends, $compute);

        return $words;
    }

    /**
     * The home page, prefixed the way the site prefixes its main language when the address
     * registry is there to say how; the application's root when it is not, which is the home
     * all the same.
     */
    private function siteUrl(): string
    {
        return $this->site?->to('/', $this->locales->defaultCode()) ?? url('/');
    }

    /** The application's zone, the one every moment is stored and printed in. */
    private function timezone(): string
    {
        $zone = $this->config->get('app.timezone');

        return is_string($zone) && $zone !== '' ? $zone : 'UTC';
    }

    /**
     * When the database was last dumped, for the one line the panel says about it (§6 of the
     * backups spec). `null` for a site that has switched the nightly dump off — there is
     * nothing to report and nothing to warn about — and a pair of nulls for a site that has it
     * on and has never produced a file, which is exactly the case worth showing.
     *
     * It travels in the manifest rather than behind a request of its own because it is one
     * directory listing, it cannot change while a page is open, and a second round trip for a
     * date and a file size is a second round trip.
     *
     * Not filtered by permission here: the panel shows the line to whoever may see the system
     * section, and what is sent is a timestamp and a byte count. The file itself never leaves
     * `storage/app`, and that is where the secrecy in §5 lives.
     *
     * @return array{at: string|null, bytes: int|null}|null
     */
    private function backup(): ?array
    {
        try {
            if (! $this->backups->enabled()) {
                return null;
            }

            $latest = $this->backups->latest();
        } catch (Throwable) {
            // A misconfigured disk is worth an exception in the command, which somebody reads,
            // and is not worth a panel that will not start.
            return null;
        }

        return [
            'at' => $latest?->takenAt->format(DATE_ATOM),
            'bytes' => $latest?->bytes,
        ];
    }

    /**
     * A blank name is no name: a setting saved and then cleared leaves an empty string behind,
     * and an empty corner is worse than the deployed title.
     */
    private function title(Branding $brand): string
    {
        $given = trim($brand->title ?? '');

        return $given === '' ? (string) $this->config->get('webx-admin.title') : $given;
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Module $module, string $title): array
    {
        return [
            'id' => $module->id(),
            'title' => $title,
            'icon' => $module->icon(),
            'order' => $module->order(),
            'group' => $module->group(),
            'section' => $module instanceof HasNavSection ? $module->navSection() : null,
            'permissions' => $module->permissions(),
            // Whatever the module itself wants to say, kept in its own room so it can never
            // shadow the fields above.
            'meta' => $module->manifest(),
        ];
    }

    /**
     * The navigation groups, translated: `webx-admin.groups` maps an id to a title key, an icon
     * and an order, and a module names the id.
     *
     * The icon is optional and stays `null` when nobody named one — the front end then draws
     * the branch with the picture it has always drawn, so a group written before this existed
     * looks exactly as it looked.
     *
     * A group may split its entries with captions: `sections` maps an id to a title key and an
     * order, the same way groups themselves are described, and a module names one through
     * {@see HasNavSection}.
     *
     * @return list<array{id: string, title: string, icon: string|null, order: int, sections: list<array{id: string, title: string, order: int}>}>
     */
    private function groups(): array
    {
        $configured = $this->config->get('webx-admin.groups', []);
        $groups = [];

        foreach (is_array($configured) ? $configured : [] as $id => $group) {
            $title = is_array($group) ? (string) ($group['title'] ?? $id) : (string) $group;
            $icon = is_array($group) && isset($group['icon']) ? (string) $group['icon'] : null;

            $groups[] = [
                'id' => (string) $id,
                'title' => (string) __($title),
                'icon' => $icon,
                'order' => is_array($group) ? (int) ($group['order'] ?? 0) : 0,
                'sections' => $this->sections(is_array($group) ? ($group['sections'] ?? []) : []),
            ];
        }

        usort($groups, static fn (array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);

        return $groups;
    }

    /**
     * @return list<array{id: string, title: string, order: int}>
     */
    private function sections(mixed $configured): array
    {
        $sections = [];

        foreach (is_array($configured) ? $configured : [] as $id => $section) {
            $title = is_array($section) ? (string) ($section['title'] ?? $id) : (string) $section;

            $sections[] = [
                'id' => (string) $id,
                'title' => (string) __($title),
                'order' => is_array($section) ? (int) ($section['order'] ?? 0) : 0,
            ];
        }

        usort($sections, static fn (array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);

        return $sections;
    }
}
