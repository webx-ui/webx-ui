<?php

declare(strict_types=1);

namespace WebxUi\Banners\Rendering;

use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Admin\Shortcodes\PageText;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Places;
use WebxUi\Banners\Variants;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Media\Screens\MediaValues;

/**
 * A banner as a template reads it — plain data, not the model (§5.2 of the banners spec).
 *
 *     id, anchor, place        the banner, `banner-5`, and the key of its place
 *     title, text              in the language asked for, else '' — no fallback (decision 12); the
 *                              text a string, or a ShortcodeText when it holds a shortcode
 *                              ({@see PageText::text()}) — the title is a heading, kept as typed
 *     image                    what a `wx-media` field hands over: url, thumb, width, height, alt…;
 *                              null only in a place of words only (`'image' => false`)
 *     image_mobile             the same, or null — the template then takes `image`
 *     video                    url and mime, or null — the picture stays as the poster
 *     buttons                  label, url, new_tab, rel, variant — in the editor's order
 *     fields                   the project's own fields (a patch on `banners.form`), by name
 *     layers                   in a place with layers, the layers ({@see Layers}); else []
 *     ratio, ratio_mobile      width / height of image and of image_mobile (else image) — the
 *                              stage the layers stand on; null when the library does not know
 *
 * A button without a label in the language, or with a link to something that is not on the site
 * now (a page in the bin or in a draft), drops out: a button to a 404 is worse than none. A banner
 * whose picture is gone from the library never gets here — {@see BannerQuery::models()} leaves it
 * out before the limit is counted.
 *
 * Every card is built from what was loaded with the list, so a list of any length is the same few
 * queries: the library once, the entities behind the links once per kind.
 */
final class Cards
{
    public function __construct(
        private readonly MediaFiles $files,
        private readonly MediaValues $media,
        private readonly LinkUrls $urls,
        private readonly Variants $variants,
        private readonly Places $places,
        private readonly Layers $layers,
        private readonly PageText $text,
    ) {}

    /**
     * @param  list<Banner>  $banners
     * @return list<array<string, mixed>>
     */
    public function banners(array $banners, string $locale): array
    {
        $paths = [];
        $links = [];

        foreach ($banners as $banner) {
            foreach (['image', 'image_mobile', 'video'] as $field) {
                $path = $banner->mediaPath($field);

                if ($path !== null) {
                    $paths[] = $path;
                }
            }

            foreach ($banner->buttonRows() as $row) {
                if (is_array($row['link'] ?? null)) {
                    $links[] = Link::fromArray($row['link']);
                }
            }
        }

        [$layerPaths, $layerLinks] = Layers::wants($banners);
        $this->files->load([...$paths, ...$layerPaths]);
        $links = [...$links, ...$layerLinks];
        $candidates = $this->urls->candidates($links, $locale);

        $cards = [];

        foreach ($banners as $banner) {
            $card = $this->banner($banner, $locale, $candidates);

            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @return array<string, mixed>|null
     */
    private function banner(Banner $banner, string $locale, array $candidates): ?array
    {
        $image = $this->picture($banner->image, $locale);

        if ($image === null && $this->places->needsPicture($banner->place?->key)) {
            return null;
        }

        $fields = [];

        foreach (array_keys((array) ($banner->extraRaw() ?? [])) as $name) {
            $fields[(string) $name] = $banner->extra((string) $name, $locale);
        }

        $mobile = $this->picture($banner->image_mobile, $locale);
        $buttons = $this->buttons($banner, $locale, $candidates);
        $layers = $this->places->hasLayers($banner->place?->key)
            ? $this->layers->of($banner, $locale, $candidates, $buttons)
            : [];

        return [
            'id' => (int) $banner->getKey(),
            'anchor' => 'banner-'.$banner->getKey(),
            'place' => $banner->place?->key,
            'title' => $banner->wordsIn('title', $locale),
            'text' => $this->text->text($banner->wordsIn('text', $locale)),
            'image' => $image,
            'image_mobile' => $mobile,
            'video' => $this->video($banner->video),
            'buttons' => $buttons,
            'fields' => $fields,
            'layers' => $layers,
            'ratio' => self::ratio($image),
            'ratio_mobile' => self::ratio($mobile ?? $image),
        ];
    }

    /**
     * A picture as the library knows it now, or null — also when its file was deleted.
     *
     * @return array<string, mixed>|null
     */
    private function picture(mixed $value, string $locale): ?array
    {
        $picture = $this->media->resolve($value, $locale);

        return is_array($picture) && is_string($picture['url'] ?? null) ? $picture : null;
    }

    /**
     * Only what a `<video>` needs; the picture is its poster.
     *
     * @return array{url: string, mime: string|null}|null
     */
    private function video(mixed $value): ?array
    {
        $video = $this->media->resolve($value);

        if (! is_array($video) || ! is_string($video['url'] ?? null)) {
            return null;
        }

        return ['url' => $video['url'], 'mime' => is_string($video['mime'] ?? null) ? $video['mime'] : null];
    }

    /**
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @return list<array{label: string, url: string, new_tab: bool, rel: string|null, variant: string|null}>
     */
    private function buttons(Banner $banner, string $locale, array $candidates): array
    {
        $buttons = [];

        foreach ($banner->buttonRows() as $row) {
            $label = self::label($row['label'] ?? null, $locale);

            if ($label === '' || ! is_array($row['link'] ?? null)) {
                continue;
            }

            $link = Link::fromArray($row['link']);
            $candidate = $link->entityType !== null && $link->entityId !== null
                ? ($candidates[$link->entityType][$link->entityId] ?? null)
                : null;

            // An entity nothing answers for, or one that is not on the site now.
            if ($link->target === LinkTarget::Entity && ($candidate === null || ! $candidate->available)) {
                continue;
            }

            $url = $this->urls->hrefWith($link, $candidate, $locale);

            if ($url === null) {
                continue;
            }

            $buttons[] = [
                'label' => $label,
                'url' => $url,
                'new_tab' => $link->newTab,
                'rel' => $link->relAttribute(),
                'variant' => $this->variant($row['variant'] ?? null),
            ];
        }

        return $buttons;
    }

    /**
     * Width over height of a picture, for the stage of its layers — null when the library has no
     * size for it (an SVG, a file uploaded before sizes were read).
     *
     * @param  array<string, mixed>|null  $picture
     */
    private static function ratio(?array $picture): ?float
    {
        $width = $picture['width'] ?? null;
        $height = $picture['height'] ?? null;

        return is_numeric($width) && is_numeric($height) && (float) $height > 0
            ? round((float) $width / (float) $height, 4)
            : null;
    }

    /** A variant the site still has, else its first one (decision 11): a button outranks its look. */
    private function variant(mixed $variant): ?string
    {
        return is_string($variant) && $this->variants->has($variant) ? $variant : $this->variants->first();
    }

    /** The label in this language and only this one — a plain string is the label everywhere. */
    private static function label(mixed $label, string $locale): string
    {
        $words = is_array($label) ? ($label[$locale] ?? null) : $label;

        return is_string($words) ? trim($words) : '';
    }
}
