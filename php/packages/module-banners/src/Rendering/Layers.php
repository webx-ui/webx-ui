<?php

declare(strict_types=1);

namespace WebxUi\Banners\Rendering;

use WebxUi\Admin\Links\Link;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Admin\Shortcodes\PageText;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Panel\LayerRules;
use WebxUi\Banners\Variants;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;

/**
 * The layers of a banner as a template prints them (§4.1 of the layers spec): words in the
 * language of the page, fields put in, pictures and links resolved, and the geometry turned into
 * custom properties — numbers and checked colours only, so nothing a person typed reaches CSS.
 *
 *     id, type, tag            the layer, text|image|button|buttons, and the tag of a text
 *     text                     the words, shortcodes resolved — a string or a ShortcodeText;
 *                              a template prints them through nl2br(e())
 *     image                    a picture as the library resolves it
 *     button                   label, url, new_tab, rel, variant — as a button of the banner
 *     buttons                  the banner's buttons, as the card has them
 *     z                        its place in the stack: the top of the editor's list is in front
 *     style, class             the custom properties and the classes of its element
 *
 * In reading order (decision 7): top to bottom, then left to right, by the computer's layout.
 */
final class Layers
{
    /** How an origin moves the box off its point: translate of x and of y, in percent of the box. */
    private const ORIGINS = [
        'tl' => [0, 0], 't' => [-50, 0], 'tr' => [-100, 0],
        'l' => [0, -50], 'c' => [-50, -50], 'r' => [-100, -50],
        'bl' => [0, -100], 'b' => [-50, -100], 'br' => [-100, -100],
    ];

    /** The geometry each layout writes, and the short name of its custom property. */
    private const FRAME_VARS = [
        'x' => 'x', 'y' => 'y', 'w' => 'w', 'size' => 'size', 'pad' => 'pad',
        'radius' => 'radius', 'tracking' => 'tracking', 'rotate' => 'rotate', 'gap' => 'gap',
    ];

    public function __construct(
        private readonly MediaValues $media,
        private readonly LinkUrls $urls,
        private readonly Variants $variants,
        private readonly Locales $locales,
        private readonly PageText $text,
    ) {}

    /**
     * Every library key and every link the layers of these banners need — loaded with the rest of
     * the list, so layers cost no query of their own.
     *
     * @param  list<Banner>  $banners
     * @return array{0: list<string>, 1: list<Link>}
     */
    public static function wants(array $banners): array
    {
        $paths = [];
        $links = [];

        foreach ($banners as $banner) {
            foreach ($banner->layerRows() as $layer) {
                $path = $layer['image']['path'] ?? null;

                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }

                if (is_array($layer['link'] ?? null)) {
                    $links[] = Link::fromArray($layer['link']);
                }
            }
        }

        return [$paths, $links];
    }

    /**
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @param  list<array<string, mixed>>  $buttons  the banner's buttons, as its card has them
     * @return list<array<string, mixed>>
     */
    public function of(Banner $banner, string $locale, array $candidates, array $buttons): array
    {
        $rows = $banner->layerRows();
        $count = count($rows);
        $fields = [
            'title' => $banner->wordsIn('title', $locale),
            'text' => $banner->wordsIn('text', $locale),
        ];
        $layers = [];

        foreach ($rows as $index => $row) {
            $layer = $this->layer($row, $locale, $candidates, $buttons, $fields);

            if ($layer === null) {
                continue;
            }

            $layer['z'] = $count - $index;
            $layer['style'] .= ';z-index:'.$layer['z'];
            $layers[] = $layer;
        }

        usort($layers, static fn (array $a, array $b): int => [$a['reading'][1], $a['reading'][0]] <=> [$b['reading'][1], $b['reading'][0]]);

        return array_map(static function (array $layer): array {
            unset($layer['reading']);

            return $layer;
        }, $layers);
    }

    /**
     * The words of a layer in this language: the page's, else the site's default, else the first
     * one written in the order of the site's languages (decision 6) — then the banner's fields put
     * in, in the page's language.
     *
     * @param  array<string, string>  $fields
     */
    public function words(mixed $text, string $locale, array $fields): string
    {
        $map = is_array($text) ? $text : [];
        $order = array_values(array_unique([$locale, $this->locales->defaultCode(), ...$this->locales->codes(), ...array_map('strval', array_keys($map))]));
        $words = '';

        foreach ($order as $code) {
            if (is_string($map[$code] ?? null) && trim($map[$code]) !== '') {
                $words = $map[$code];

                break;
            }
        }

        return trim(strtr($words, ['{title}' => $fields['title'] ?? '', '{text}' => $fields['text'] ?? '']));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @param  list<array<string, mixed>>  $buttons
     * @param  array<string, string>  $fields
     * @return array<string, mixed>|null
     */
    private function layer(array $row, string $locale, array $candidates, array $buttons, array $fields): ?array
    {
        $type = $row['type'] ?? null;
        $frames = is_array($row['frames'] ?? null) ? $row['frames'] : [];
        $desktop = is_array($frames['desktop'] ?? null) ? $frames['desktop'] : [];
        $mobile = is_array($frames['mobile'] ?? null) ? $frames['mobile'] : $desktop;

        if (($desktop['hidden'] ?? false) === true && ($mobile['hidden'] ?? false) === true) {
            return null;
        }

        $layer = [
            'id' => (string) ($row['id'] ?? ''),
            'type' => $type,
            'tag' => 'div',
            'text' => '',
            'image' => null,
            'button' => null,
            'buttons' => null,
        ];

        switch ($type) {
            case 'text':
                $layer['text'] = $this->words($row['text'] ?? null, $locale, $fields);
                $layer['tag'] = is_string($row['tag'] ?? null) && preg_match('/^(p|span|h[1-6])$/', $row['tag']) === 1 ? $row['tag'] : 'p';

                if (preg_replace('/[\s\p{P}]+/u', '', $layer['text']) === '') {
                    return null;
                }

                // Resolved once, after `{title}` and `{text}` are put in as typed: the banner's
                // text resolved first and then the layer's would print `[[phone]]` as a number.
                $layer['text'] = $this->text->text($layer['text']);

                break;
            case 'image':
                $image = $this->media->resolve($row['image'] ?? null, $locale);

                if (! is_array($image) || ! is_string($image['url'] ?? null)) {
                    return null;
                }

                $layer['image'] = $image;
                break;
            case 'button':
                $button = $this->button($row, $locale, $candidates, $fields);

                if ($button === null) {
                    return null;
                }

                $layer['button'] = $button;
                break;
            case 'buttons':
                if ($buttons === []) {
                    return null;
                }

                $layer['buttons'] = $buttons;
                break;
            default:
                return null;
        }

        $style = is_array($row['style'] ?? null) ? $row['style'] : [];
        $motion = is_array($row['motion'] ?? null) ? $row['motion'] : [];

        $layer['style'] = $this->vars($desktop, $mobile, $style, $motion);
        $layer['class'] = $this->classes($desktop, $mobile, $style, $motion);
        $layer['reading'] = [self::num($desktop['x'] ?? 0), self::num($desktop['y'] ?? 0)];

        return $layer;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<int, LinkCandidate>>  $candidates
     * @param  array<string, string>  $fields
     * @return array<string, mixed>|null
     */
    private function button(array $row, string $locale, array $candidates, array $fields): ?array
    {
        $label = $this->words($row['text'] ?? null, $locale, $fields);

        if ($label === '' || ! is_array($row['link'] ?? null)) {
            return null;
        }

        $link = Link::fromArray($row['link']);
        $candidate = $link->entityType !== null && $link->entityId !== null
            ? ($candidates[$link->entityType][$link->entityId] ?? null)
            : null;

        if ($link->target === LinkTarget::Entity && ($candidate === null || ! $candidate->available)) {
            return null;
        }

        $url = $this->urls->hrefWith($link, $candidate, $locale);

        if ($url === null) {
            return null;
        }

        $variant = $row['variant'] ?? null;

        return [
            'label' => $label,
            'url' => $url,
            'new_tab' => $link->newTab,
            'rel' => $link->relAttribute(),
            'variant' => is_string($variant) && $this->variants->has($variant) ? $variant : $this->variants->first(),
        ];
    }

    /**
     * The custom properties of a layer: the computer's geometry as `--x`…, the phone's as `--m-x`…,
     * the look, the motion. Numbers and checked colours only.
     *
     * @param  array<string, mixed>  $desktop
     * @param  array<string, mixed>  $mobile
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>  $motion
     */
    private function vars(array $desktop, array $mobile, array $style, array $motion): string
    {
        $vars = [];

        foreach (['' => $desktop, 'm-' => $mobile] as $prefix => $frame) {
            foreach (self::FRAME_VARS as $key => $name) {
                $default = LayerRules::FRAME[$key][2];
                $vars["--{$prefix}{$name}"] = self::num($frame[$key] ?? $default);
            }

            [$ox, $oy] = self::ORIGINS[$frame['origin'] ?? 'tl'] ?? [0, 0];
            $vars["--{$prefix}ox"] = $ox.'%';
            $vars["--{$prefix}oy"] = $oy.'%';
            $vars["--{$prefix}display"] = ($frame['hidden'] ?? false) === true ? 'none' : 'block';
        }

        $vars['--weight'] = (int) self::num($style['weight'] ?? 400);
        $vars['--leading'] = self::num($style['line_height'] ?? 1.2);
        $vars['--opacity'] = self::num($style['opacity'] ?? 1);
        $vars['--align'] = in_array($style['align'] ?? null, LayerRules::ALIGNS, true) ? $style['align'] : 'start';

        foreach (['color' => '--color', 'background' => '--background'] as $key => $name) {
            $color = self::color($style[$key] ?? null);

            if ($color !== null) {
                $vars[$name] = $color;
            }
        }

        $font = $style['font'] ?? null;

        if (is_string($font) && in_array($font, LayerRules::FONTS, true)) {
            $vars['--font'] = "var(--site-font-{$font})";
        }

        $vars['--delay'] = (int) self::num($motion['delay'] ?? 0).'ms';
        $vars['--duration'] = (int) self::num($motion['duration'] ?? 600).'ms';

        $css = [];

        foreach ($vars as $name => $value) {
            $css[] = "{$name}:{$value}";
        }

        return implode(';', $css);
    }

    /**
     * @param  array<string, mixed>  $desktop
     * @param  array<string, mixed>  $mobile
     * @param  array<string, mixed>  $style
     * @param  array<string, mixed>  $motion
     */
    private function classes(array $desktop, array $mobile, array $style, array $motion): string
    {
        $classes = [];

        foreach (['italic', 'uppercase', 'shadow'] as $flag) {
            if (($style[$flag] ?? false) === true) {
                $classes[] = "is-{$flag}";
            }
        }

        if (is_string($style['background'] ?? null)) {
            $classes[] = 'has-background';
        }

        if (in_array($motion['in'] ?? null, LayerRules::ENTRANCES, true)) {
            $classes[] = 'is-in-'.$motion['in'];
        }

        if (in_array($motion['loop'] ?? null, LayerRules::LOOPS, true)) {
            $classes[] = 'is-loop-'.$motion['loop'];
        }

        return implode(' ', $classes);
    }

    /** A colour of the theme's palette, or one of its own — anything else is no colour at all. */
    public static function color(mixed $color): ?string
    {
        if (! is_string($color)) {
            return null;
        }

        if (preg_match('/^#[0-9a-f]{6}([0-9a-f]{2})?$/', $color) === 1) {
            return $color;
        }

        if (preg_match('/^token:([a-z][a-z0-9-]{0,40})$/', $color, $token) === 1) {
            return "var(--site-color-{$token[1]})";
        }

        return null;
    }

    private static function num(mixed $value): float|int
    {
        $number = is_numeric($value) ? round((float) $value, 2) : 0.0;

        return $number == (int) $number ? (int) $number : $number;
    }
}
