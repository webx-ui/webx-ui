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
     * do. `CatalogueTest` holds both to the packages of the monorepo.
     *
     * @var array<string, array{package: string, npm: string, label: string, default: bool, requires: list<string>}>
     */
    private const MODULES = [
        'pages' => [
            'package' => 'webx-ui/module-pages',
            'npm' => '@webx-ui/module-pages',
            'label' => 'Pages — the page tree, its editor and the home page',
            'default' => true,
            'requires' => ['admins', 'blocks', 'seo'],
        ],
        'media' => [
            'package' => 'webx-ui/module-media',
            'npm' => '@webx-ui/module-media',
            'label' => 'Media — the file library',
            'default' => true,
            'requires' => ['admins'],
        ],
        'blocks' => [
            'package' => 'webx-ui/module-blocks',
            'npm' => '@webx-ui/module-blocks',
            'label' => 'Blocks — the block types a page is built out of',
            'default' => true,
            'requires' => ['admins'],
        ],
        'seo' => [
            'package' => 'webx-ui/module-seo',
            'npm' => '@webx-ui/module-seo',
            'label' => 'SEO — metatags, redirects and the sitemap',
            'default' => true,
            'requires' => ['admins', 'settings'],
        ],
        'settings' => [
            'package' => 'webx-ui/module-settings',
            'npm' => '@webx-ui/module-settings',
            'label' => 'Settings — the screens a site keeps its own settings on',
            'default' => true,
            'requires' => ['admins'],
        ],
        'inbox' => [
            'package' => 'webx-ui/module-inbox',
            'npm' => '@webx-ui/module-inbox',
            'label' => 'Inbox — forms and what visitors send through them',
            'default' => true,
            'requires' => ['admins'],
        ],
        'blog' => [
            'package' => 'webx-ui/module-blog',
            'npm' => '@webx-ui/module-blog',
            'label' => 'Blog — articles, rubrics and tags',
            'default' => false,
            'requires' => ['admins', 'blocks', 'media', 'seo'],
        ],
        'services' => [
            'package' => 'webx-ui/module-services',
            'npm' => '@webx-ui/module-services',
            'label' => 'Services — a catalogue of services with categories, each a page of the site',
            'default' => false,
            'requires' => ['blocks', 'media', 'seo'],
        ],
        'faq' => [
            'package' => 'webx-ui/module-faq',
            'npm' => '@webx-ui/module-faq',
            'label' => 'FAQ — questions and answers, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks'],
        ],
        'reviews' => [
            'package' => 'webx-ui/module-reviews',
            'npm' => '@webx-ui/module-reviews',
            'label' => 'Reviews — what clients say, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks', 'media'],
        ],
        'recipes' => [
            'package' => 'webx-ui/module-recipes',
            'npm' => '@webx-ui/module-recipes',
            'label' => 'Recipes — recipes with categories, nutrition and schema.org markup, each a page of the site',
            'default' => false,
            'requires' => ['media', 'seo'],
        ],
        'events' => [
            'package' => 'webx-ui/module-events',
            'npm' => '@webx-ui/module-events',
            'label' => 'Events — workshops and meetings with a date, a place and a link to book, each a page of the site',
            'default' => false,
            'requires' => ['media', 'seo'],
        ],
        'press' => [
            'package' => 'webx-ui/module-press',
            'npm' => '@webx-ui/module-press',
            'label' => 'Press — the outlets that wrote about the site and their articles, a page per outlet and a strip of logos',
            'default' => false,
            'requires' => ['blocks', 'media', 'seo'],
        ],
        'team' => [
            'package' => 'webx-ui/module-team',
            'npm' => '@webx-ui/module-team',
            'label' => 'Team — the people of the organisation with their photos and social links, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks', 'media'],
        ],
        'banners' => [
            'package' => 'webx-ui/module-banners',
            'npm' => '@webx-ui/module-banners',
            'label' => 'Banners — pictures with words and buttons in named places, printed by the site\'s templates',
            'default' => false,
            'requires' => ['media'],
        ],
        'tariffs' => [
            'package' => 'webx-ui/module-tariffs',
            'npm' => '@webx-ui/module-tariffs',
            'label' => 'Tariffs — price cards with what each plan includes, shown on any page as a block',
            'default' => false,
            'requires' => ['blocks'],
        ],
        'vacancies' => [
            'package' => 'webx-ui/module-vacancies',
            'npm' => '@webx-ui/module-vacancies',
            'label' => 'Vacancies — open positions with terms and salary, each a page of the site with schema.org JobPosting',
            'default' => false,
            'requires' => ['seo'],
        ],
        'catalog' => [
            'package' => 'webx-ui/module-catalog',
            'npm' => '@webx-ui/module-catalog',
            'label' => 'Catalog — products with prices and pictures in a tree of categories, a filter and bulk actions',
            'default' => false,
            'requires' => ['media', 'seo'],
        ],
        'catalog-brands' => [
            'package' => 'webx-ui/module-catalog-brands',
            'npm' => '@webx-ui/module-catalog-brands',
            'label' => 'Catalog brands — a brand per product with its own page, logo and description, and a filter (needs the catalog)',
            'default' => false,
            'requires' => ['catalog', 'media', 'seo'],
        ],
        'catalog-labels' => [
            'package' => 'webx-ui/module-catalog-labels',
            'npm' => '@webx-ui/module-catalog-labels',
            'label' => 'Catalog labels — top, sale, new: badges on the cards and a filter (needs the catalog)',
            'default' => false,
            'requires' => ['catalog'],
        ],
        'catalog-stock' => [
            'package' => 'webx-ui/module-catalog-stock',
            'npm' => '@webx-ui/module-catalog-stock',
            'label' => 'Catalog stock — in stock, out of stock, on order, and whether it can be bought (needs the catalog)',
            'default' => false,
            'requires' => ['catalog'],
        ],
        'catalog-properties' => [
            'package' => 'webx-ui/module-catalog-properties',
            'npm' => '@webx-ui/module-catalog-properties',
            'label' => 'Catalog properties — colour, material, weight: sets per category, specifications on the page and filters (needs the catalog)',
            'default' => false,
            'requires' => ['catalog', 'media'],
        ],
        'catalog-manticore' => [
            'package' => 'webx-ui/module-catalog-manticore',
            'label' => 'Catalog on Manticore — search, filters and counts for tens of thousands of products, on a Manticore server you run (needs the catalog)',
            'default' => false,
        ],
        'menu' => [
            'package' => 'webx-ui/module-menu',
            'npm' => '@webx-ui/module-menu',
            'label' => 'Menus — the header and the footer, and what each entry points at',
            'default' => true,
            'requires' => [],
        ],
        'admins' => [
            'package' => 'webx-ui/module-auth',
            'npm' => '@webx-ui/module-auth',
            'label' => 'Administrators — signing in, roles and permissions',
            'default' => true,
            'requires' => [],
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
