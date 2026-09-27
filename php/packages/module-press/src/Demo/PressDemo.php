<?php

declare(strict_types=1);

namespace WebxUi\Press\Demo;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\BlockOffers;
use WebxUi\Blocks\Models\Block;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Storage\FileStore;
use WebxUi\Pages\Models\Page;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Panel\OutletForm;
use WebxUi\Press\Panel\PressModule;
use WebxUi\Press\PressServiceProvider;
use WebxUi\Routing\Reserved;
use WebxUi\Seo\Models\SeoMeta;

/**
 * Four outlets and eight articles (§4.12), and the page a site puts them on.
 *
 * Each article past the first few is there to show one rule: one leads to a PDF only and one to an
 * address with the PDF beside it (decision 8), one is hidden (decision 9), one has a title in
 * English only and so is not on the Russian site at all (decision 7), two are dated to the month
 * and one to the year (decision 10) — and every kind of the default four is there. Of the outlets,
 * one is not published and one is left out of the strip of logos (decision 13).
 *
 * The logos are drawings, not somebody's trademarks, and the PDF is one page that says what it is.
 * They go into the library through {@see FileStore}, into a folder of their own, the way an editor's
 * upload would.
 *
 * The outlets are written through {@see OutletForm} — the door the panel and an agent use — so the
 * demo checks its own rows the way theirs are checked.
 *
 * With `module-pages`, the page at the prefix (decision 12): the strip of the marked logos, and
 * under it the catalogue in groups by kind.
 */
final class PressDemo
{
    /** The types of the files the demo brings: all it takes to hand them to the library. */
    private const MIME = ['svg' => 'image/svg+xml', 'pdf' => 'application/pdf'];

    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
        private readonly BlockOffers $offers,
        private readonly FileStore $store,
        private readonly OutletForm $form,
        private readonly Config $config,
    ) {}

    /**
     * The library the logos go into, and — when installed — the page the blocks go onto.
     *
     * @return list<string>
     */
    public function requires(): array
    {
        return [
            'media',
            'blocks',
            ...($this->modules->has('pages') ? ['pages'] : []),
        ];
    }

    public function seed(DemoLedger $ledger): void
    {
        // Outlets with anything in them are somebody's press.
        if (Outlet::withTrashed()->exists()) {
            return;
        }

        $document = $this->read();
        $files = $this->library($document, $ledger);

        foreach ($this->list($document['outlets'] ?? null) as $input) {
            $this->outlet($input, $files, $ledger);
        }

        if (! $this->blockTypes($ledger)) {
            return;
        }

        $page = $document['page'] ?? null;

        if (is_array($page) && (bool) $this->config->get('webx-press.pages', true) && $this->modules->has('pages') && class_exists(Page::class)) {
            $this->page($page, $ledger);
        }
    }

    /**
     * The logos and the PDF in a folder of their own, by the names the document calls them.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, string> name in the document → key in the library
     */
    private function library(array $document, DemoLedger $ledger): array
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->orderBy('lft')->first();

        if (! $root instanceof MediaDirectory) {
            throw new RuntimeException('The library has no root folder; run the migrations first.');
        }

        $folder = new MediaDirectory(['title' => $this->name($document['folder'] ?? null) ?? 'Press']);
        $folder->appendTo($root);
        $ledger->created($folder, 'the Press folder of the library');

        $keys = [];

        foreach (is_array($document['files'] ?? null) ? $document['files'] : [] as $name => $file) {
            $path = __DIR__.'/../../resources/demo/'.$file;
            $mime = self::MIME[strtolower(pathinfo((string) $file, PATHINFO_EXTENSION))] ?? null;

            if (! is_string($file) || $mime === null || ! $this->files->exists($path)) {
                continue;
            }

            // Test mode, because this is a file of the package and not something that came up a
            // socket: without it `UploadedFile` refuses anything PHP did not receive itself.
            $stored = $this->store->store(new UploadedFile($path, $file, $mime, null, true), $folder);

            // The same bytes were already in the library — a second run, or somebody's own copy.
            // Its key serves as well; it is just not the demo's to remove.
            if ($stored->wasRecentlyCreated) {
                $ledger->created($stored, $stored->name);
            }

            $keys[(string) $name] = (string) $stored->path;
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $files
     */
    private function outlet(array $input, array $files, DemoLedger $ledger): void
    {
        $key = is_string($input['key'] ?? null) ? $input['key'] : '';
        $title = $this->words($input['title'] ?? null);

        if ($key === '' || $title === null) {
            return;
        }

        $logo = $files[(string) ($input['logo'] ?? '')] ?? null;
        $rows = [];

        foreach ($this->list($input['articles'] ?? null) as $article) {
            $row = $this->article($article, $files);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $outlet = $this->form->save(new Outlet, [
            'title' => $title,
            // The same slug in every language: the addresses are then the ones a reader of either
            // version would guess, and a language without articles has none anyway.
            'slug' => array_fill_keys(array_keys($title), $key),
            'summary' => $this->words($input['summary'] ?? null) ?? [],
            'logo' => $logo === null ? null : ['path' => $logo],
            'website_url' => is_string($input['website_url'] ?? null) ? $input['website_url'] : null,
            'featured' => ($input['featured'] ?? false) === true,
            'published' => ($input['published'] ?? true) !== false,
            'articles' => $rows,
        ]);

        // One entry: removing the outlet for good takes its articles with it.
        $ledger->created($outlet, $key);
    }

    /**
     * A row of the articles repeater, in the languages of the site that the demo has.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $files
     * @return array<string, mixed>|null
     */
    private function article(array $input, array $files): ?array
    {
        $title = $this->words($input['title'] ?? null);
        $file = $files[(string) ($input['file'] ?? '')] ?? null;
        $url = is_string($input['url'] ?? null) ? $input['url'] : null;

        // Without its PDF (a library that refused the file) an article with nothing else to lead
        // to would be refused by the form — and the whole outlet with it.
        if ($title === null || ($url === null && $file === null)) {
            return null;
        }

        return [
            'title' => $title,
            'excerpt' => $this->words($input['excerpt'] ?? null) ?? [],
            'kind' => is_string($input['kind'] ?? null) ? $input['kind'] : null,
            'published_on' => is_string($input['published_on'] ?? null) ? $input['published_on'] : null,
            'date_precision' => is_string($input['date_precision'] ?? null) ? $input['date_precision'] : 'day',
            'url' => $url,
            'file' => $file === null ? null : ['path' => $file],
            'is_hidden' => ($input['is_hidden'] ?? false) === true,
        ];
    }

    /**
     * The offered types, installed now if the site has not taken them — `webx:setup` usually has.
     * A type by that slug the site already has is left as it is, whatever it was made into.
     */
    private function blockTypes(DemoLedger $ledger): bool
    {
        foreach ($this->offers->documents([PressModule::ID]) as $offer) {
            if ($this->offers->install($offer) !== BlockOffers::PRESENT) {
                $block = Block::query()->where('slug', $offer['slug'])->first();

                if ($block instanceof Block) {
                    $ledger->created($block, 'the '.$offer['slug'].' block type');
                }
            }
        }

        if (! Block::query()->whereIn('slug', ['press-logos', 'press-outlets'])->where('is_enabled', true)->exists()) {
            $ledger->note('the outlets are in the panel, but no page shows them: the site has no enabled block type "press-logos" or "press-outlets".');

            return false;
        }

        return true;
    }

    /**
     * The page at the prefix, under the home page: the strip of logos, and the catalogue in groups.
     *
     * @param  array<string, mixed>  $input
     */
    private function page(array $input, DemoLedger $ledger): void
    {
        $slug = PressServiceProvider::prefix($this->config);
        $home = Page::home();

        if (! $home instanceof Page) {
            return;
        }

        if (app(Reserved::class)->taken($slug) || Page::withTrashed()->whereTranslationLikeAny('slug', $slug)->exists()) {
            $ledger->note("no page of the press was made: /{$slug} is already taken on this site.");

            return;
        }

        $enabled = Block::query()->where('is_enabled', true)->pluck('slug')->all();
        $logos = is_array($input['logos'] ?? null) ? $input['logos'] : [];
        $catalog = is_array($input['catalog'] ?? null) ? $input['catalog'] : [];
        $blocks = [];

        if (in_array('press-logos', $enabled, true)) {
            $blocks[] = ['key' => 'press-logos', 'type' => 'press-logos', 'values' => array_filter([
                'title' => $this->words($logos['title'] ?? null),
                'featured' => true,
            ], static fn (mixed $value): bool => $value !== null)];
        }

        if (in_array('press-outlets', $enabled, true)) {
            $blocks[] = ['key' => 'press-outlets', 'type' => 'press-outlets', 'values' => array_filter([
                'title' => $this->words($catalog['title'] ?? null),
                'group' => true,
                'kinds' => array_values(array_filter(is_array($catalog['kinds'] ?? null) ? $catalog['kinds'] : [], is_string(...))),
            ], static fn (mixed $value): bool => $value !== null)];
        }

        $title = $this->words($input['title'] ?? null) ?? [$this->locales->defaultCode() => 'Press'];

        $page = new Page([
            'title' => $title,
            // The same slug in every language the page is written in: a page with no slug in a
            // language has no address in it.
            'slug' => array_fill_keys(array_keys($title), $slug),
            'blocks' => $blocks,
        ]);

        try {
            $page->appendTo($home);
        } catch (Throwable $refused) {
            $ledger->note("no page of the press was made: {$refused->getMessage()}");

            return;
        }

        $ledger->created($page, $slug);
        $page->publish(null, EntityVersion::SOURCE_IMPORT, 'Demo content');

        $seo = $input['seo'] ?? null;

        if (is_array($seo)) {
            // A page with an empty card has no <title> at all (CLAUDE.md §4).
            $card = array_filter([
                'title' => $this->words($seo['title'] ?? null),
                'description' => $this->words($seo['description'] ?? null),
            ]);

            if ($card !== []) {
                $page->saveSeo($card);
                $meta = $page->seo()->first();

                if ($meta instanceof SeoMeta) {
                    $ledger->created($meta, "what /{$slug} says about itself");
                }
            }
        }

        $ledger->createdVersionsOf($page);
    }

    /** One name for a folder: the default language's, else English, else none. */
    private function name(mixed $text): ?string
    {
        $words = $this->words($text);

        return $words === null ? null : ($words[$this->locales->defaultCode()] ?? reset($words));
    }

    /**
     * The languages of the demo that the site has. A site with neither gets the English under its
     * own default, as the other demos do, rather than nameless outlets.
     *
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        if (! is_array($text)) {
            return null;
        }

        $codes = $this->locales->codes();
        $words = [];

        foreach ($text as $locale => $value) {
            if (is_string($value) && trim($value) !== '' && in_array((string) $locale, $codes, true)) {
                $words[(string) $locale] = trim($value);
            }
        }

        if ($words === [] && is_string($text['en'] ?? null) && trim($text['en']) !== '') {
            return [$this->locales->defaultCode() => trim($text['en'])];
        }

        return $words === [] ? null : $words;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_array(...))) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/press.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('press.json is not a set of outlets.');
        }

        return $document;
    }
}
