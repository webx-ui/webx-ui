<?php

declare(strict_types=1);

namespace WebxUi\Admin\Setup;

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

/**
 * The modules a site can be built out of, by the name they go by in the panel.
 *
 * Written down here rather than read from anywhere, and that is the honest answer: the registry
 * behind `webx:panel --sync` knows what is *installed*, and choosing what to install is a
 * question about packages that are not. The list is short, it changes about once a release, and
 * a name it does not know is a refusal rather than a `composer require` of whatever turns up.
 *
 * The ids are the panel's, not the packages': administrators are `admins`, because `users` is
 * left for the people who visit the site.
 *
 * What is installed is read off disk on every call rather than from `Composer\InstalledVersions`,
 * because `webx:setup` installs packages halfway through its own run: the static map this
 * process booted with stops being true the moment `composer require` returns.
 */
final class Catalogue
{
    /**
     * Id, the Composer package behind it, its npm half, what it is, whether a new site gets it
     * unasked, and the modules it cannot work without.
     *
     * The npm name is written here although every package also says it under `extra.webx`:
     * that block is on disk only once the package is installed, and this list is mostly about
     * packages that are not. `requires` is the package's own `require` read as panel ids —
     * Composer installs those anyway, and saying so lets whoever chooses see it before they
     * do. `demo` is the languages the module's demo content is written in — for the same reason:
     * `webx:setup` warns about a language it lacks before the package is there to be read (spec
     * WEBX_UI_THEMES.md §15.2); a demo of plain strings is `en`, a module that seeds no words has
     * none. `ModulesCommandTest` holds all three to the packages of the monorepo. `public` is false for
     * a module with nothing a visitor of the site sees — the panel, its settings, the task queue: a site
     * built of those alone has no public part ({@see self::hasPublicPart()}).
     *
     * @var array<string, array{package: string, npm: string, label: string, default: bool, requires: list<string>, demo: list<string>, public?: bool}>
     */
    private const MODULES = [
        'pages' => [
            'package' => 'webx-ui/module-pages',
            'npm' => '@webx-ui/module-pages',
            'label' => 'Pages — the page tree, its editor and the home page',
            'default' => true,
            'requires' => ['admins', 'blocks', 'seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'media' => [
            'package' => 'webx-ui/module-media',
            'npm' => '@webx-ui/module-media',
            'label' => 'Media — the file library',
            'default' => true,
            'requires' => ['admins'],
            'demo' => [],
        ],
        'blocks' => [
            'package' => 'webx-ui/module-blocks',
            'npm' => '@webx-ui/module-blocks',
            'label' => 'Blocks — the block types a page is built out of',
            'default' => true,
            'requires' => ['admins'],
            'demo' => ['en'],
        ],
        'seo' => [
            'package' => 'webx-ui/module-seo',
            'npm' => '@webx-ui/module-seo',
            'label' => 'SEO — metatags, redirects and the sitemap',
            'default' => true,
            'requires' => ['admins', 'settings'],
            'demo' => ['en'],
        ],
        'settings' => [
            'package' => 'webx-ui/module-settings',
            'npm' => '@webx-ui/module-settings',
            'label' => 'Settings — the screens a site keeps its own settings on',
            'default' => true,
            'requires' => ['admins'],
            'demo' => ['en', 'pl', 'ru'],
            'public' => false,
        ],
        'audit' => [
            'package' => 'webx-ui/module-audit',
            'npm' => '@webx-ui/module-audit',
            'label' => 'Audit — the production config, the host, and links to a development stand in the content',
            'default' => true,
            'requires' => ['admins', 'settings'],
            'demo' => [],
        ],
        'inbox' => [
            'package' => 'webx-ui/module-inbox',
            'npm' => '@webx-ui/module-inbox',
            'label' => 'Inbox — forms and what visitors send through them',
            'default' => true,
            'requires' => ['admins'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'blog' => [
            'package' => 'webx-ui/module-blog',
            'npm' => '@webx-ui/module-blog',
            'label' => 'Blog — articles, rubrics and tags',
            'default' => false,
            'requires' => ['admins', 'blocks', 'media', 'seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'services' => [
            'package' => 'webx-ui/module-services',
            'npm' => '@webx-ui/module-services',
            'label' => 'Services — a catalogue of services with categories, each a page of the site',
            'default' => false,
            'requires' => ['blocks', 'media', 'seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'faq' => [
            'package' => 'webx-ui/module-faq',
            'npm' => '@webx-ui/module-faq',
            'label' => 'FAQ — questions and answers, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks'],
            'demo' => ['en', 'ru'],
        ],
        'reviews' => [
            'package' => 'webx-ui/module-reviews',
            'npm' => '@webx-ui/module-reviews',
            'label' => 'Reviews — what clients say, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks', 'media'],
            'demo' => ['en', 'ru'],
        ],
        'recipes' => [
            'package' => 'webx-ui/module-recipes',
            'npm' => '@webx-ui/module-recipes',
            'label' => 'Recipes — recipes with categories, nutrition and schema.org markup, each a page of the site',
            'default' => false,
            'requires' => ['media', 'seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'events' => [
            'package' => 'webx-ui/module-events',
            'npm' => '@webx-ui/module-events',
            'label' => 'Events — workshops and meetings with a date, a place and a link to book, each a page of the site',
            'default' => false,
            'requires' => ['media', 'seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'press' => [
            'package' => 'webx-ui/module-press',
            'npm' => '@webx-ui/module-press',
            'label' => 'Press — the outlets that wrote about the site and their articles, a page per outlet and a strip of logos',
            'default' => false,
            'requires' => ['blocks', 'media', 'seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'team' => [
            'package' => 'webx-ui/module-team',
            'npm' => '@webx-ui/module-team',
            'label' => 'Team — the people of the organisation with their photos and social links, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks', 'media'],
            'demo' => ['en', 'ru'],
        ],
        'banners' => [
            'package' => 'webx-ui/module-banners',
            'npm' => '@webx-ui/module-banners',
            'label' => 'Banners — pictures with words and buttons in named places, printed by the site\'s templates',
            'default' => false,
            'requires' => ['media'],
            'demo' => ['en', 'ru'],
        ],
        'tariffs' => [
            'package' => 'webx-ui/module-tariffs',
            'npm' => '@webx-ui/module-tariffs',
            'label' => 'Tariffs — price cards with what each plan includes, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks'],
            'demo' => ['en', 'ru'],
        ],
        'configurators' => [
            'package' => 'webx-ui/module-configurator',
            'npm' => '@webx-ui/module-configurator',
            'label' => 'Configurators — a product built by steps with sizes and a price counted on the server',
            'default' => false,
            'requires' => ['media'],
            'demo' => ['en', 'ru', 'uk'],
        ],
        'vacancies' => [
            'package' => 'webx-ui/module-vacancies',
            'npm' => '@webx-ui/module-vacancies',
            'label' => 'Vacancies — open positions with terms and salary, each a page of the site with schema.org JobPosting',
            'default' => false,
            'requires' => ['seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'tasks' => [
            'package' => 'webx-ui/module-tasks',
            'npm' => '@webx-ui/module-tasks',
            'label' => 'Tasks — a queue of work between people and AI agents: stages handed out one at a time, reported and reviewed',
            'default' => false,
            'requires' => ['admins', 'settings'],
            'demo' => [],
            'public' => false,
        ],
        'catalog' => [
            'package' => 'webx-ui/module-catalog',
            'npm' => '@webx-ui/module-catalog',
            'label' => 'Catalog — products with prices and pictures in a tree of categories, a filter and bulk actions',
            'default' => false,
            'requires' => ['media', 'seo'],
            'demo' => ['en'],
        ],
        'catalog-brands' => [
            'package' => 'webx-ui/module-catalog-brands',
            'npm' => '@webx-ui/module-catalog-brands',
            'label' => 'Catalog brands — a brand per product with its own page, logo and description, and a filter (needs the catalog)',
            'default' => false,
            'requires' => ['catalog', 'media', 'seo'],
            'demo' => ['en'],
        ],
        'catalog-labels' => [
            'package' => 'webx-ui/module-catalog-labels',
            'npm' => '@webx-ui/module-catalog-labels',
            'label' => 'Catalog labels — top, sale, new: badges on the cards and a filter (needs the catalog)',
            'default' => false,
            'requires' => ['catalog'],
            'demo' => ['en'],
        ],
        'catalog-stock' => [
            'package' => 'webx-ui/module-catalog-stock',
            'npm' => '@webx-ui/module-catalog-stock',
            'label' => 'Catalog stock — in stock, out of stock, on order, and whether it can be bought (needs the catalog)',
            'default' => false,
            'requires' => ['catalog'],
            'demo' => ['en'],
        ],
        'catalog-properties' => [
            'package' => 'webx-ui/module-catalog-properties',
            'npm' => '@webx-ui/module-catalog-properties',
            'label' => 'Catalog properties — colour, material, weight: sets per category, specifications on the page and filters (needs the catalog)',
            'default' => false,
            'requires' => ['catalog', 'media'],
            'demo' => ['en'],
        ],
        'catalog-landings' => [
            'package' => 'webx-ui/module-catalog-landings',
            'npm' => '@webx-ui/module-catalog-landings',
            'label' => 'Catalog landings — «Apple laptops», «under 50 000»: a list with filters chosen in advance under its own address, texts and SEO, made one by one or in bulk (needs the catalog)',
            'default' => false,
            'requires' => ['catalog', 'seo'],
            'demo' => ['en'],
        ],
        'catalog-manticore' => [
            'package' => 'webx-ui/module-catalog-manticore',
            'npm' => '@webx-ui/module-catalog-manticore',
            'label' => 'Catalog on Manticore — search, filters and counts for tens of thousands of products, on a Manticore server you run (needs the catalog)',
            'default' => false,
            'requires' => ['catalog'],
            'demo' => [],
        ],
        'menu' => [
            'package' => 'webx-ui/module-menu',
            'npm' => '@webx-ui/module-menu',
            'label' => 'Menus — the header and the footer, and what each entry points at',
            'default' => true,
            'requires' => [],
            'demo' => ['en', 'pl', 'ru'],
        ],
        'snippets' => [
            'package' => 'webx-ui/module-snippets',
            'npm' => '@webx-ui/module-snippets',
            'label' => 'Codes — a tag manager, a counter or a chat pasted into the head or the body of every page',
            'default' => false,
            'requires' => [],
            'demo' => ['en', 'ru'],
            'public' => false,
        ],
        'notifications' => [
            'package' => 'webx-ui/module-notifications',
            'npm' => '@webx-ui/module-notifications',
            'label' => 'Notifications — channels to Telegram and e-mail, with templates and a log of what was sent',
            'default' => false,
            'requires' => ['admins'],
            'demo' => ['en', 'ru'],
            'public' => false,
        ],
        'admins' => [
            'package' => 'webx-ui/module-auth',
            'npm' => '@webx-ui/module-auth',
            'label' => 'Administrators — signing in, roles and permissions',
            'default' => true,
            'requires' => [],
            'demo' => [],
            'public' => false,
        ],
    ];

    /**
     * The demo a packaged theme brings in place of a module's own (spec WEBX_UI_THEMES.md §15.1):
     * the modules it has a `demo/<module>/` for, and the languages it is written in. Written down
     * for the reason `demo` above is; only the themes of the monorepo — nobody has said what a
     * stranger's theme speaks, and its modules then count with their own demo.
     *
     * @var array<string, array{modules: list<string>, demo: list<string>}>
     */
    private const THEMES = [
        'webx-ui/theme-default' => [
            'modules' => ['blocks', 'inbox', 'media', 'pages', 'seo'],
            'demo' => ['en', 'pl', 'ru'],
        ],
    ];

    /** @var array<string, string>|null */
    private ?array $versions = null;

    public function __construct(
        private readonly Filesystem $files,
        private ?string $installed = null,
    ) {}

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys(self::MODULES);
    }

    public function knows(string $id): bool
    {
        return array_key_exists($id, self::MODULES);
    }

    public function packageFor(string $id): string
    {
        return self::MODULES[$id]['package'];
    }

    /** The id a Composer package goes by in the panel, or null when the catalogue does not list it. */
    public function idFor(string $package): ?string
    {
        foreach (self::MODULES as $id => $module) {
            if ($module['package'] === $package) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Every module as the outside world reads it — `webx:modules --json`.
     *
     * @return list<array{id: string, package: string, npm: string, label: string, default: bool, requires: list<string>, installed: bool}>
     */
    public function describe(): array
    {
        $described = [];

        foreach (self::MODULES as $id => $module) {
            $described[] = [
                'id' => $id,
                'package' => $module['package'],
                'npm' => $module['npm'],
                'label' => $module['label'],
                'default' => $module['default'],
                'requires' => $module['requires'],
                'installed' => $this->has($module['package']),
            ];
        }

        return $described;
    }

    /**
     * Whether a site of these modules has anything a visitor sees. Not when every one of them is
     * only the panel's — then `/` leads to the panel and the site keeps out of search engines
     * (`webx-admin.public`). A module the catalogue does not know is somebody else's, and assumed to
     * show something.
     *
     * @param  list<string>  $ids
     */
    public function hasPublicPart(array $ids): bool
    {
        foreach ($ids as $id) {
            if ((self::MODULES[$id]['public'] ?? true) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The ids given, with everything they require, however deep.
     *
     * Composer would install the rest anyway; closing the list here is what lets the steps
     * after it know — `--modules=pages` brings the sign-in module, and with it an administrator.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function withRequirements(array $ids): array
    {
        $closed = [];
        $queue = $ids;

        while ($queue !== []) {
            $id = array_shift($queue);

            if (in_array($id, $closed, true)) {
                continue;
            }

            $closed[] = $id;
            $queue = [...$queue, ...(self::MODULES[$id]['requires'] ?? [])];
        }

        return $closed;
    }

    /**
     * The site's languages the demo of these modules is not written in, each with the modules
     * that lack it. Under a theme of the monorepo its demo stands in for the modules it covers,
     * as it does when the demo is seeded. A module that seeds no words lacks nothing.
     *
     * Such a module still seeds: a missing default language gets the English, a missing other
     * one shows the default language's words under its prefix — which is what the warning says.
     *
     * @param  list<string>  $locales
     * @param  list<string>  $ids
     * @return array<string, list<string>>
     */
    public function demoGaps(array $locales, array $ids, ?string $theme = null): array
    {
        $themed = self::THEMES[$theme ?? ''] ?? null;
        $gaps = [];

        foreach ($ids as $id) {
            $demo = $themed !== null && in_array($id, $themed['modules'], true)
                ? $themed['demo']
                : (self::MODULES[$id]['demo'] ?? []);

            if ($demo === []) {
                continue;
            }

            foreach ($locales as $locale) {
                if (! in_array($locale, $demo, true)) {
                    $gaps[$locale][] = $id;
                }
            }
        }

        return $gaps;
    }

    /**
     * The languages a module's own demo is written in.
     *
     * @return list<string>
     */
    public function demoOf(string $id): array
    {
        return self::MODULES[$id]['demo'] ?? [];
    }

    /**
     * What a theme of the monorepo brings as demo — for the test that holds it to the theme.
     *
     * @return array<string, array{modules: list<string>, demo: list<string>}>
     */
    public function themeDemos(): array
    {
        return self::THEMES;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return array_map(static fn (array $module): string => $module['label'], self::MODULES);
    }

    /**
     * What a site gets when nobody says otherwise: everything but the blog, plus whatever is
     * already installed.
     *
     * A site that turns out to need a blog installs it the same way six months later — the
     * point of `webx:panel --sync` is that installing a module late costs what installing it
     * early would have.
     *
     * @return list<string>
     */
    public function suggested(): array
    {
        return array_values(array_unique([
            ...array_keys(array_filter(self::MODULES, static fn (array $module): bool => $module['default'])),
            ...$this->installed(),
        ]));
    }

    /**
     * What `--modules` asks for, closed over requirements, plus whatever is installed: `all`,
     * a list of ids, or a list after `+` — the suggested set and those on top of it, which is
     * how the starter site gets a blog without naming every module of a company site.
     *
     * Whatever is installed stays installed: setup adds, it does not uninstall. What the chosen
     * ones require comes along, as Composer would bring it anyway.
     *
     * @return list<string>
     *
     * @throws SetupFailed when an id is not in the catalogue.
     */
    public function chosen(string $given): array
    {
        $given = trim($given);
        $added = str_starts_with($given, '+');

        $ids = $given === 'all'
            ? $this->ids()
            : array_values(array_filter(array_map(trim(...), explode(',', ltrim($given, '+')))));

        foreach ($ids as $id) {
            if (! $this->knows($id)) {
                throw SetupFailed::unknownModule($id, implode(', ', $this->ids()));
            }
        }

        return array_values(array_unique([
            ...$this->withRequirements($added ? [...$this->suggested(), ...$ids] : $ids),
            ...$this->installed(),
        ]));
    }

    /**
     * The ids whose package is in `vendor` right now.
     *
     * @return list<string>
     */
    public function installed(): array
    {
        return array_values(array_filter(
            $this->ids(),
            fn (string $id): bool => $this->has($this->packageFor($id)),
        ));
    }

    /** Whether any Composer package at all is installed, module of the panel or not. */
    public function has(string $package): bool
    {
        return array_key_exists($package, $this->readVersions());
    }

    /**
     * What to hand `composer require`, for the ids that are not installed yet.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function toRequire(array $ids): array
    {
        $constraint = $this->constraint();

        return array_values(array_map(
            fn (string $id): string => $this->packageFor($id).':'.$constraint,
            array_filter($ids, fn (string $id): bool => ! $this->has($this->packageFor($id))),
        ));
    }

    /**
     * The version range to ask for, read off the frame that is already installed.
     *
     * Every Composer package here ships on one shared version, so a site is never asked to work
     * out which release of `module-blog` goes with the `module-admin` it already has. A checkout
     * installed through a path repository can call itself anything — `dev-main`, a branch alias
     * — and there is no range to build out of that, so the answer is `*` and Composer resolves
     * it against the same repository.
     */
    public function constraint(): string
    {
        $version = $this->readVersions()['webx-ui/module-admin'] ?? '';

        return preg_match('/^v?\d+\.\d+\.\d+/', $version) === 1 ? '^'.ltrim($version, 'v') : '*';
    }

    /**
     * Forget what was read: `composer require` has run and the answer has changed.
     */
    public function refresh(): void
    {
        $this->versions = null;
    }

    /** @return array<string, string> */
    private function readVersions(): array
    {
        if ($this->versions !== null) {
            return $this->versions;
        }

        $path = $this->installed ??= $this->locate();
        $versions = [];

        if ($path !== null && $this->files->exists($path)) {
            $decoded = json_decode((string) $this->files->get($path), true);
            $packages = is_array($decoded) ? ($decoded['packages'] ?? $decoded) : [];

            foreach (is_array($packages) ? $packages : [] as $package) {
                if (is_array($package) && is_string($package['name'] ?? null)) {
                    $versions[$package['name']] = is_string($package['version'] ?? null)
                        ? $package['version']
                        : '';
                }
            }
        }

        return $this->versions = $versions;
    }

    private function locate(): ?string
    {
        // vendor/composer/ClassLoader.php sits beside installed.json, wherever vendor is.
        $file = (new ReflectionClass(ClassLoader::class))->getFileName();

        return $file === false ? null : dirname($file).'/installed.json';
    }
}
