<?php

declare(strict_types=1);

namespace WebxUi\Pages\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\ThemeDemo;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Models\SeoMeta;

/**
 * The home page filled in, and one page under it (§9 of the new-site spec) — plus whatever pages
 * the site's theme brings in its `demo/pages/` (§15.1 of the themes spec), which may also stand
 * in for either of the two.
 *
 * The two halves are not alike, and that is the point of the journal: `about` is created and
 * removing it deletes it, while the home page comes from a migration and has to survive the
 * removal — so what is written down for it is what its columns held before, and `--remove`
 * puts those back. A site left with a blank home page is still a working site; a site left
 * with no home page is not.
 *
 * The title of the home page is left alone: the migration wrote it in every language the site
 * publishes in, and a demo has no business replacing a translation with an English word.
 */
final class PagesDemo
{
    /** The documents the module seeds itself; a theme may replace them, not add them twice. */
    private const OWN = ['home', 'about'];

    /** How a document names a picture of the media demo: `demo:<its file name, no extension>`. */
    private const PICTURE = 'demo:';

    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly Reserved $reserved,
        private readonly ModuleRegistry $modules,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $home = Page::home();

        if (! $home instanceof Page) {
            throw new RuntimeException('There is no home page; run the migrations first.');
        }

        // Anything beyond the home page means the site already has pages of its own, and demo
        // pages among them are clutter rather than an example.
        $fresh = Page::withTrashed()->count() <= 1;

        $this->fillHome($home, $ledger);

        if ($fresh) {
            $this->addPage($this->read('about'), $home, $ledger);

            foreach ($this->themePages() as $document) {
                $this->addPage($document, $home, $ledger);
            }
        }
    }

    private function fillHome(Page $home, DemoLedger $ledger): void
    {
        // A site whose home page somebody has already written is not one to write over.
        if ($home->blocksTree() !== [] || $home->hasDraft()) {
            return;
        }

        // The application still answers `/` itself — the welcome page of the Laravel skeleton,
        // usually. The registry refuses the address and undoes the save with it, so there is
        // no filling the home page at all until that route goes (CLAUDE.md §4). Said out loud
        // rather than thrown: the rest of the demo is fine, and this is one line to fix.
        if ($this->reserved->taken('')) {
            $ledger->note(
                'the home page was left alone: this application answers / with a route of its own. '
                .'Give / to module-pages and run webx:demo again.',
            );

            return;
        }

        $document = $this->read('home');

        $ledger->changed($home, ['blocks', 'draft', 'published_at'], 'the home page');

        $home->setAttribute('blocks', $this->pictures($document['blocks'] ?? [], $ledger));
        $home->save();
        $home->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $this->describe($home, $document, $ledger);
        $ledger->createdVersionsOf($home);
    }

    /**
     * A page under `$parent`, and the pages its document nests under `children`.
     *
     * @param  array<string, mixed>  $document
     */
    private function addPage(array $document, Page $parent, DemoLedger $ledger): void
    {
        $slug = (string) ($document['slug'] ?? 'about');

        $page = new Page([
            'title' => [$this->locale() => (string) ($document['title'] ?? ucfirst($slug))],
            'slug' => [$this->locale() => $slug],
            'blocks' => $this->pictures($document['blocks'] ?? [], $ledger),
        ]);

        // Through the tree rather than through `save()`: the bounds are what put the page
        // under its parent, and the address is built out of the slugs above it.
        $page->appendTo($parent);
        $ledger->created($page, $slug);

        $page->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $this->describe($page, $document, $ledger);
        $ledger->createdVersionsOf($page);

        foreach (is_array($document['children'] ?? null) ? $document['children'] : [] as $child) {
            if (is_array($child)) {
                $this->addPage($child, $page, $ledger);
            }
        }
    }

    /**
     * The pages the theme brings beside the module's two — its showcase, on `theme-default`
     * (§18.3 of the themes spec): every document in its `demo/pages/` but the two the module
     * itself names, in the order of their file names.
     *
     * @return list<array<string, mixed>>
     */
    private function themePages(): array
    {
        $directory = ThemeDemo::directory('pages');

        if ($directory === null) {
            return [];
        }

        $documents = [];

        foreach ($this->files->glob($directory.'/*.json') as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            if (! in_array($name, self::OWN, true)) {
                $documents[$name] = $this->decode($file, $name);
            }
        }

        ksort($documents);

        return array_values($documents);
    }

    /**
     * Fill in the page's SEO card.
     *
     * Not decoration: a page with an empty card has no `<title>` at all, because the title is
     * something an editor writes rather than something the module invents from the name in the
     * tree. A demo whose front page is untitled in every tab is the wrong first impression,
     * and the card is the place where that is fixed on a real site too.
     *
     * The row goes in the journal even for a page that is deleted whole — `HasSeo` takes it
     * with the entity, so the removal simply finds it gone — because the home page is not
     * deleted, and its card would otherwise outlive the demo.
     *
     * @param  array<string, mixed>  $document
     */
    private function describe(Page $page, array $document, DemoLedger $ledger): void
    {
        $seo = $document['seo'] ?? null;

        if (! is_array($seo) || $seo === []) {
            return;
        }

        $card = [];

        foreach (Fields::TRANSLATED as $field) {
            if (is_string($seo[$field] ?? null) && $seo[$field] !== '') {
                $card[$field] = [$this->locale() => $seo[$field]];
            }
        }

        if ($card === []) {
            return;
        }

        $page->saveSeo($card);

        $meta = $page->seo()->first();

        if ($meta instanceof SeoMeta) {
            $ledger->created($meta, 'what '.$page->routePath().' says about itself');
        }
    }

    /**
     * The library first when it is installed: a theme's page names the pictures its media demo
     * puts there. A name `requires()` gives that is not installed skips the whole demo, and the
     * pages have plenty to show without a picture.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return $this->modules->has('media') ? ['blocks', 'media'] : ['blocks'];
    }

    /**
     * The blocks of a page with every `{ "path": "demo:<name>" }` made a picture of the library:
     * the one the media demo stored under that name. The library gives a file its key when it
     * stores it, so a document written beforehand can only name it. A picture that is not there —
     * no library, or a site that had the same bytes already under another name — is left out of
     * its list, or leaves its field empty.
     */
    private function pictures(mixed $value, DemoLedger $ledger): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $path = $value['path'] ?? null;

        if (is_string($path) && str_starts_with($path, self::PICTURE)) {
            $found = $this->picture(substr($path, strlen(self::PICTURE)), $ledger);

            return $found === null ? null : ['path' => $found] + $value;
        }

        $list = array_is_list($value);
        $done = [];

        foreach ($value as $key => $item) {
            $made = $this->pictures($item, $ledger);

            // A picture that is not there drops out of a gallery rather than leaving a hole.
            if ($list && $made === null && is_array($item)) {
                continue;
            }

            $done[$key] = $made;
        }

        return $list ? array_values($done) : $done;
    }

    /** The key of the demo's picture by this name: the one this run stored, else any by the name. */
    private function picture(string $name, DemoLedger $ledger): ?string
    {
        if (! class_exists(MediaFile::class)) {
            return null;
        }

        $ids = $ledger->idsOf('media', MediaFile::class);
        $file = ($ids === [] ? null : MediaFile::query()->whereKey($ids)->where('name', $name)->first())
            ?? MediaFile::query()->where('name', $name)->orderByDesc('id')->first();

        return $file instanceof MediaFile ? $file->path : null;
    }

    /** The language the demo is written in: one, and the site's own default. */
    private function locale(): string
    {
        return $this->locales->defaultCode();
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $name): array
    {
        // The theme's copy of a document wins; the module's own fills in what it leaves out.
        $theme = ThemeDemo::directory('pages');
        $file = $theme !== null && $this->files->exists("{$theme}/{$name}.json")
            ? "{$theme}/{$name}.json"
            : __DIR__."/../../resources/demo/{$name}.json";

        return $this->decode($file, $name);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $file, string $name): array
    {
        $document = json_decode((string) $this->files->get($file), true);

        if (! is_array($document)) {
            throw new RuntimeException("{$name}.json is not a page document.");
        }

        return $document;
    }
}
