<?php

declare(strict_types=1);

namespace WebxUi\Banners\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Places;
use WebxUi\Localization\Locales;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

/**
 * The declared places, filled (§5.8 of the banners spec).
 *
 * `hero` gets a slider's worth: three banners that are on, and each past the first shows a rule —
 * one would carry a video if the library had one (the demo library has pictures only, so it
 * carries none and the picture stands in, which is what a phone sees anyway), one has no Russian
 * words and so is not on the Russian pages (decision 12). A fourth is switched off: in the panel,
 * not on the site. `promo` gets one banner, a single layout's worth. `notice` gets one of words
 * only, switched off: switched on, it would stand above the header of every page of the site.
 *
 * Pictures are the library demo's, found through the ledger — somebody's own picture is not the
 * place to hang an example. A button "to a page" points at a page by entity — the pages demo's,
 * else one the site has — so its address comes out of the routing registry; a site without pages
 * gets that button left out rather than pointed at an address that answers 404.
 *
 * No page and no block (decision 7): banners reach the site where its template asks for them, and
 * the demo does not write templates.
 */
final class BannersDemo
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly ModuleRegistry $modules,
        private readonly Places $places,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A site with any banner at all has made its own.
        if (Banner::withTrashed()->exists()) {
            return;
        }

        $media = $this->media($ledger);

        if (! isset($media['demo-wide'])) {
            $ledger->note('no banners were made: the library demo added no pictures to put on them.');

            return;
        }

        $page = $this->page($ledger);
        $video = $this->video($ledger);
        $document = $this->read();

        foreach ((array) ($document['places'] ?? []) as $key => $banners) {
            if (! is_string($key) || ! is_array($banners) || ! $this->places->isDeclared($key)) {
                continue;
            }

            $made = Place::query()->where('key', $key)->doesntExist();
            $place = $this->places->row($key);

            if (! $place instanceof Place) {
                continue;
            }

            if ($made) {
                $ledger->created($place, "the place {$key}");
            }

            foreach ($banners as $input) {
                if (is_array($input)) {
                    $this->banner($place, $input, $media, $page, $video, $ledger);
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    public function requires(): array
    {
        // Pages only when installed: a name `requires()` gives that is not installed skips the
        // whole demo, and banners have something to show without a page to point a button at.
        return $this->modules->has('pages') ? ['media', 'pages'] : ['media'];
    }

    /**
     * Straight to the model rather than through the form: the switched-off banner and the one
     * without Russian are the point, and the form has nothing against either — but it would take
     * a request's language for the words, and a demo has none.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, MediaFile>  $media
     */
    private function banner(Place $place, array $input, array $media, ?int $page, ?MediaFile $video, DemoLedger $ledger): void
    {
        $image = $media[pathinfo((string) ($input['image'] ?? ''), PATHINFO_FILENAME)] ?? null;

        // Only a place of words only (`notice`) takes a banner without its picture.
        if (! $image instanceof MediaFile && $this->places->needsPicture($place->key)) {
            return;
        }

        $mobile = $media[pathinfo((string) ($input['image_mobile'] ?? ''), PATHINFO_FILENAME)] ?? null;
        $buttons = [];

        foreach ((array) ($input['buttons'] ?? []) as $row) {
            $button = is_array($row) ? $this->button($row, $page) : null;

            if ($button !== null) {
                $buttons[] = $button;
            }
        }

        $banner = new Banner([
            'image' => $image instanceof MediaFile ? ['path' => $image->path] : null,
            'image_mobile' => $mobile instanceof MediaFile ? ['path' => $mobile->path] : null,
            'video' => ($input['video'] ?? false) === true && $video instanceof MediaFile ? ['path' => $video->path] : null,
            'title' => $this->words($input['title'] ?? null),
            'text' => $this->words($input['text'] ?? null),
            'buttons' => $buttons === [] ? null : $buttons,
            'enabled' => ($input['enabled'] ?? false) === true,
        ]);
        $banner->moveToEndOf((int) $place->getKey());
        $banner->save();

        $ledger->created($banner, (string) ($input['key'] ?? "banner #{$banner->getKey()}"));
    }

    /**
     * A row of the button repeater: "page" is a page by entity, anything else an address.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function button(array $row, ?int $page): ?array
    {
        $label = $this->words($row['label'] ?? null);
        $link = $row['link'] ?? null;

        if ($label === null || ! is_string($link) || ($link === 'page' && $page === null)) {
            return null;
        }

        return [
            'label' => $label,
            'link' => $link === 'page'
                ? ['target' => 'entity', 'entity_type' => 'page', 'entity_id' => $page, 'url' => null, 'hash' => null, 'new_tab' => false, 'rel' => []]
                : ['target' => 'url', 'entity_type' => null, 'entity_id' => null, 'url' => $link, 'hash' => null, 'new_tab' => false, 'rel' => []],
            'variant' => is_string($row['variant'] ?? null) ? $row['variant'] : null,
        ];
    }

    /**
     * The pictures the library demo made a moment ago, by name without the extension: the
     * library turns a JPEG into a WebP on the way in, so `demo-wide.jpg` is stored as
     * `demo-wide.webp`.
     *
     * @return array<string, MediaFile>
     */
    private function media(DemoLedger $ledger): array
    {
        $ids = $ledger->idsOf('media', MediaFile::class);
        $files = [];

        foreach ($ids === [] ? [] : MediaFile::query()->whereKey($ids)->get() as $file) {
            $files[$file->name] ??= $file;
        }

        return $files;
    }

    /** A video the library demo made, if a future one does; there is none today. */
    private function video(DemoLedger $ledger): ?MediaFile
    {
        $ids = $ledger->idsOf('media', MediaFile::class);

        return $ids === [] ? null : MediaFile::query()->whereKey($ids)->where('mime', 'like', 'video/%')->first();
    }

    /**
     * A page that is not the home page — the home page is where the banners stand: the pages
     * demo's first, else the first page the address registry has. A site that had its pages
     * before the demo gets none from the pages demo, and pointing a button at one of its own
     * pages changes nothing on that page.
     */
    private function page(DemoLedger $ledger): ?int
    {
        if (! in_array('pages', $this->requires(), true) || ! class_exists(Page::class)) {
            return null;
        }

        $ids = $ledger->idsOf('pages', Page::class);
        $id = $ids === [] ? null : Page::query()->whereKey($ids)->whereNotNull('parent_id')->orderBy('lft')->value('id');

        $id ??= Route::query()
            ->where('kind', Route::CANONICAL)
            ->where('entity_type', (new Page)->getMorphClass())
            ->where('path', '!=', '')
            ->orderBy('id')
            ->value('entity_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * The languages of the demo that the site has. A site with neither gets the English under its
     * own default, as the other demos do.
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
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/banners.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('banners.json is not a banners document.');
        }

        return $document;
    }
}
