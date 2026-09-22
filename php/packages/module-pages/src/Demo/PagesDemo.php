<?php

declare(strict_types=1);

namespace WebxUi\Pages\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Fields;
use WebxUi\Seo\Models\SeoMeta;

/**
 * The home page filled in, and one page under it (§9 of the new-site spec).
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
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly Reserved $reserved,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $home = Page::home();

        if (! $home instanceof Page) {
            throw new RuntimeException('There is no home page; run the migrations first.');
        }

        $this->fillHome($home, $ledger);
        $this->addChild($home, $ledger);
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

        $home->setAttribute('blocks', $document['blocks'] ?? []);
        $home->save();
        $home->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $this->describe($home, $document, $ledger);
        $ledger->createdVersionsOf($home);
    }

    private function addChild(Page $home, DemoLedger $ledger): void
    {
        // Anything beyond the home page means the site already has pages of its own, and a
        // demo page among them is clutter rather than an example.
        if (Page::withTrashed()->count() > 1) {
            return;
        }

        $document = $this->read('about');
        $slug = (string) ($document['slug'] ?? 'about');

        $page = new Page([
            'title' => [$this->locale() => (string) ($document['title'] ?? 'About')],
            'slug' => [$this->locale() => $slug],
            'blocks' => $document['blocks'] ?? [],
        ]);

        // Through the tree rather than through `save()`: the bounds are what put the page
        // under the home page, and the address is built out of the slugs above it.
        $page->appendTo($home);
        $ledger->created($page, $slug);

        $page->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $this->describe($page, $document, $ledger);
        $ledger->createdVersionsOf($page);
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
        $document = json_decode((string) $this->files->get(__DIR__."/../../resources/demo/{$name}.json"), true);

        if (! is_array($document)) {
            throw new RuntimeException("{$name}.json is not a page document.");
        }

        return $document;
    }
}
