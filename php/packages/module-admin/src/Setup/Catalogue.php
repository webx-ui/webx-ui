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
     * Id, the Composer package behind it, what it is, and whether a new site gets it unasked.
     *
     * @var array<string, array{package: string, label: string, default: bool}>
     */
    private const MODULES = [
        'pages' => [
            'package' => 'webx-ui/module-pages',
            'label' => 'Pages — the page tree, its editor and the home page',
            'default' => true,
        ],
        'media' => [
            'package' => 'webx-ui/module-media',
            'label' => 'Media — the file library',
            'default' => true,
        ],
        'blocks' => [
            'package' => 'webx-ui/module-blocks',
            'label' => 'Blocks — the block types a page is built out of',
            'default' => true,
        ],
        'seo' => [
            'package' => 'webx-ui/module-seo',
            'label' => 'SEO — metatags, redirects and the sitemap',
            'default' => true,
        ],
        'settings' => [
            'package' => 'webx-ui/module-settings',
            'label' => 'Settings — the screens a site keeps its own settings on',
            'default' => true,
        ],
        'inbox' => [
            'package' => 'webx-ui/module-inbox',
            'label' => 'Inbox — forms and what visitors send through them',
            'default' => true,
        ],
        'blog' => [
            'package' => 'webx-ui/module-blog',
            'label' => 'Blog — articles, rubrics and tags',
            'default' => false,
        ],
        'services' => [
            'package' => 'webx-ui/module-services',
            'label' => 'Services — a catalogue of services with categories, each a page of the site',
            'default' => false,
        ],
        'faq' => [
            'package' => 'webx-ui/module-faq',
            'label' => 'FAQ — questions and answers, shown on any page as a block',
            'default' => false,
        ],
        'reviews' => [
            'package' => 'webx-ui/module-reviews',
            'label' => 'Reviews — what clients say, shown on any page as a block',
            'default' => false,
        ],
        'recipes' => [
            'package' => 'webx-ui/module-recipes',
            'label' => 'Recipes — recipes with categories, nutrition and schema.org markup, each a page of the site',
            'default' => false,
        ],
        'events' => [
            'package' => 'webx-ui/module-events',
            'label' => 'Events — workshops and meetings with a date, a place and a link to book, each a page of the site',
            'default' => false,
        ],
        'menu' => [
            'package' => 'webx-ui/module-menu',
            'label' => 'Menus — the header and the footer, and what each entry points at',
            'default' => true,
        ],
        'admins' => [
            'package' => 'webx-ui/module-auth',
            'label' => 'Administrators — signing in, roles and permissions',
            'default' => true,
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
