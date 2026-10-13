<?php

declare(strict_types=1);

namespace WebxUi\Banners\Mcp;

use Illuminate\Contracts\Container\Container;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Models\Place;
use WebxUi\Banners\Panel\BannerNames;
use WebxUi\Banners\Panel\LayerRules;
use WebxUi\Banners\Panel\PlaceEditor;
use WebxUi\Banners\Places;
use WebxUi\Banners\Variants;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\McpResource;

/**
 * What an agent reads before it writes a banner (§5.7): every place with its banners in one
 * message.
 *
 * The looks a button may have and the layouts head it, because a look the site does not have is
 * refused. Banners that are off are listed too — the point of reading this first is not to type a
 * banner in a second time beside the copy that is not on yet. `written_in` says where a banner is
 * seen: one with words only in their languages, one without them everywhere (decision 12).
 *
 * And it says, before anything else, where banners go on a site: nowhere an agent can put them.
 * A template asks for a place; there is no block to look for (decision 7).
 */
final class BannerResources
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return list<McpResource>
     */
    public function all(): array
    {
        return [
            new McpResource(
                'banners://catalog',
                'Banners catalog',
                'Every place banners stand in — declared in the config or made in the panel, with its layout — and '
                .'the banners of each in the order the site shows them: the title, whether it is on, the languages '
                .'its words are written in and whether it has a video; with the button looks and the layouts this '
                .'site has. The site shows a place only where its template asks for it with banners(\'<key>\'): '
                .'there is no block for banners, so do not look for a page to put one on. Read this before adding '
                .'a banner, so that you reuse rather than duplicate.',
                fn (): array => $this->catalog(),
            ),
            new McpResource(
                'banners://layers',
                'Banner layers',
                'How the layers of a banner work — texts, pictures and buttons placed on its picture in a place with '
                .'layers: the unit every number is in, the two layouts, the anchor, the fields a text puts in, the '
                .'limits. Read it before sending layers to banners_create or banners_update.',
                fn (): array => $this->layers(),
            ),
        ];
    }

    /**
     * The layers spec for an agent, short: what each number means and what is refused.
     *
     * @return array<string, mixed>
     */
    private function layers(): array
    {
        return [
            'unit' => 'Every length — x, y, w, size, pad, radius, gap — is a percent of the picture\'s width; y too, '
                .'not of its height. The banner scales as a picture does. tracking is in em, rotate in degrees.',
            'stage' => 'The layers stand on the picture, which the frame of the place crops (object-fit: cover). A layer '
                .'in the cropped part is not seen; the panel shades it.',
            'layouts' => 'frames.desktop always; frames.mobile for the picture for phones, or null — then the phone uses desktop.',
            'origin' => 'Which point of the layer stands at x, y: tl t tr l c r bl b br. A right-aligned number uses tr.',
            'types' => [
                'text' => '{ text: { "<lang>": "…" }, tag } — {title} and {text} put in the banner\'s fields. An empty '
                    .'language shows the default one, else the first written.',
                'image' => '{ image: "<library key>" }',
                'button' => '{ text: { … }, link, variant }',
                'buttons' => 'the banner\'s own buttons, in a row — one such layer at most',
            ],
            'ranges' => LayerRules::FRAME,
            'tags' => array_keys($this->container->make(Places::class)->tags(null)),
            'entrances' => LayerRules::ENTRANCES,
            'loops' => LayerRules::LOOPS,
            'fonts' => [...LayerRules::FONTS, null],
            'colors' => '#rrggbb, or token:<name> of the theme\'s palette (--site-color-<name>).',
            'max' => $this->container->make(Places::class)->maxLayers(),
            'order' => 'The first layer of the list is in front.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $locales = $this->container->make(Locales::class);
        $places = $this->container->make(PlaceEditor::class)->all();
        $rows = Place::query()->pluck('id', 'key');
        $banners = Banner::query()
            ->whereIn('place_id', $rows->values())
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('place_id');

        return [
            'locales' => $locales->codes(),
            'default_locale' => $locales->defaultCode(),
            'layouts' => Places::LAYOUTS,
            'variants' => $this->container->make(Variants::class)->all(),
            'usage' => "banners('<key>')->get() in a template gives the banners of a place; banners_layout('<key>') its layout and options.",
            'places' => array_map(static fn (array $place): array => [
                'key' => $place['key'],
                'title' => $place['title'],
                'declared' => $place['declared'],
                'layout' => $place['layout'],
                'layers' => isset($place['layers']),
                'banners' => ($banners->get($rows->get($place['key'])) ?? collect())
                    ->map(static fn (Banner $banner): array => [
                        'id' => (int) $banner->getKey(),
                        'title' => BannerNames::of($banner, $locales),
                        'enabled' => $banner->enabled,
                        'written_in' => $banner->wordLanguages(),
                        'has_video' => $banner->mediaPath('video') !== null,
                        'layers' => count($banner->layerRows()),
                    ])
                    ->values()
                    ->all(),
            ], $places),
        ];
    }
}
