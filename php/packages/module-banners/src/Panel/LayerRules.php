<?php

declare(strict_types=1);

namespace WebxUi\Banners\Panel;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use WebxUi\Admin\Links\Link;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Places;
use WebxUi\Banners\Variants;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaValues;

/**
 * The layers of a banner as the editor or an agent sent them, checked and tidied before anything
 * is written (§3.2, §3.4 of the layers spec).
 *
 * Every layer comes out whole: the keys of its kind and nothing else, every number inside its
 * range and rounded to hundredths of a percent, every missing value at its default — so what the
 * site prints from is never a guess. A refusal is a 422 under `layers.<n>.<field>`, numbered as
 * the editor lists the layers.
 *
 * What is dropped rather than refused, as an empty button row is: a text layer with no words in
 * any language, a button with neither words nor a link.
 */
final class LayerRules
{
    public const TYPES = ['text', 'image', 'button', 'buttons'];

    public const ORIGINS = ['tl', 't', 'tr', 'l', 'c', 'r', 'bl', 'b', 'br'];

    public const ALIGNS = ['start', 'center', 'end'];

    /** The families of a theme a layer may be set in; null is the site's own. */
    public const FONTS = ['heading', 'body'];

    public const ENTRANCES = ['fade', 'rise', 'slide-start', 'slide-end', 'zoom'];

    public const LOOPS = ['spin', 'float'];

    /**
     * The geometry of one layout, in percent of the picture's width (decision 1): range and
     * default. `y` reaches past the picture for the layer that steps out of it (decision 13).
     */
    public const FRAME = [
        'x' => [-50, 150, 5],
        'y' => [-50, 150, 5],
        'w' => [1, 150, 40],
        'size' => [0.3, 20, 3],
        'pad' => [0, 10, 0],
        'radius' => [0, 50, 0],
        // In em, not in percent: letter spacing follows the type, not the picture.
        'tracking' => [-0.2, 1, 0],
        'rotate' => [-180, 180, 0],
        'gap' => [0, 10, 1],
    ];

    /** The numbers of a layer's look: range and default. */
    public const STYLE = [
        'weight' => [100, 900, 400],
        'line_height' => [0.8, 3, 1.2],
        'opacity' => [0, 1, 1],
    ];

    public const MOTION = [
        'delay' => [0, 10000, 0],
        'duration' => [100, 5000, 600],
    ];

    /** The most characters a layer's words have in one language. */
    public const TEXT_MAX = 500;

    private const ID = '/^[a-z0-9]{4,12}$/';

    private const COLOR = '/^(#[0-9a-f]{6}([0-9a-f]{2})?|token:[a-z][a-z0-9-]{0,40})$/';

    /**
     * What a banner of a place with layers starts with (decision 5): its title, its text and its
     * buttons, in a column on the left — the panel's `defaultLayers()` in the same numbers. The
     * words are written once, in the default language; the others take them from there.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaults(string $locale): array
    {
        $frame = static fn (array $over): array => [
            'x' => 6, 'y' => 6, 'w' => 40, 'size' => 3, 'pad' => 0, 'radius' => 0, 'tracking' => 0,
            'rotate' => 0, 'gap' => 1, 'origin' => 'tl', 'hidden' => false, ...$over,
        ];
        $style = static fn (array $over): array => [
            'font' => null, 'weight' => 400, 'italic' => false, 'uppercase' => false, 'align' => 'start',
            'line_height' => 1.2, 'color' => null, 'background' => null, 'opacity' => 1, 'shadow' => false, ...$over,
        ];
        $motion = static fn (int $delay): array => ['in' => 'rise', 'loop' => null, 'delay' => $delay, 'duration' => 600];

        return [
            [
                'id' => 'title', 'type' => 'text', 'text' => [$locale => '{title}'], 'tag' => 'h2',
                'style' => $style(['weight' => 700, 'line_height' => 1.05, 'color' => '#ffffff', 'shadow' => true]),
                'frames' => ['desktop' => $frame(['w' => 45, 'size' => 4.2]), 'mobile' => null],
                'motion' => $motion(0),
            ],
            [
                'id' => 'text', 'type' => 'text', 'text' => [$locale => '{text}'], 'tag' => 'p',
                'style' => $style(['line_height' => 1.4, 'color' => '#ffffff', 'shadow' => true]),
                'frames' => ['desktop' => $frame(['y' => 18, 'size' => 1.4]), 'mobile' => null],
                'motion' => $motion(150),
            ],
            [
                'id' => 'buttons', 'type' => 'buttons',
                'style' => $style([]),
                'frames' => ['desktop' => $frame(['y' => 28, 'w' => 50, 'size' => 1.2]), 'mobile' => null],
                'motion' => $motion(300),
            ],
        ];
    }

    public function __construct(
        private readonly Places $places,
        private readonly Variants $variants,
        private readonly MediaValues $media,
        private readonly Locales $locales,
        private readonly ValidationFactory $validator,
    ) {}

    /**
     * The layers to store and the refusals, keyed as the form looks for them.
     *
     * @param  callable(array<string, mixed>): ?string  $linkProblem  what the link field says of a link
     * @return array{0: list<array<string, mixed>>|null, 1: array<string, list<string>>}
     */
    public function check(mixed $value, Banner $banner, ?string $place, callable $linkProblem): array
    {
        // An empty list is kept as one: somebody took every layer away, and the editor must not
        // lay the default three back the next time the banner opens (decision 5).
        if ($value === null || $value === []) {
            return [$value === [] ? [] : null, []];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return [null, ['layers' => [(string) __('webx-banners::errors.layers-shape')]]];
        }

        $max = $this->places->maxLayers();

        if (count($value) > $max) {
            return [null, ['layers' => [(string) __('webx-banners::errors.layers-max', ['max' => $max])]]];
        }

        $stored = $this->storedVariants($banner);
        $tags = array_keys($this->places->tags($place));
        $layers = [];
        $errors = [];
        $ids = [];
        $groups = 0;

        foreach ($value as $index => $raw) {
            $at = "layers.{$index}";

            if (! is_array($raw) || ! in_array($raw['type'] ?? null, self::TYPES, true)) {
                $errors["{$at}.type"] = [(string) __('webx-banners::errors.layer-type', ['types' => implode(', ', self::TYPES)])];

                continue;
            }

            $type = (string) $raw['type'];
            $id = $raw['id'] ?? null;

            if (! is_string($id) || preg_match(self::ID, $id) !== 1 || in_array($id, $ids, true)) {
                $id = $this->freshId($ids);
            }

            $ids[] = $id;

            $layer = ['id' => $id, 'type' => $type];
            $name = is_string($raw['name'] ?? null) ? mb_substr(trim($raw['name']), 0, 120) : '';

            if ($name !== '') {
                $layer['name'] = $name;
            }

            if ($type === 'text' || $type === 'button') {
                [$text, $problem] = $this->text($raw['text'] ?? null);

                if ($problem !== null) {
                    $errors["{$at}.text"] = [$problem];
                }

                $layer['text'] = $text;
            }

            if ($type === 'text') {
                if ($layer['text'] === [] && ! isset($errors["{$at}.text"])) {
                    continue;
                }

                $tag = $raw['tag'] ?? 'p';

                if (! is_string($tag) || ! in_array($tag, $tags, true)) {
                    $errors["{$at}.tag"] = [(string) __('webx-banners::errors.layer-tag', ['tags' => implode(', ', $tags)])];
                }

                $layer['tag'] = is_string($tag) ? $tag : 'p';
            }

            if ($type === 'image') {
                $image = $raw['image'] ?? null;
                $validator = $this->validator->make(['value' => $image], ['value' => ['required', ...$this->media->rules([], 'image')]], [], ['value' => (string) __('webx-banners::screen.image')]);

                if ($validator->fails()) {
                    $errors["{$at}.image"] = [(string) $validator->errors()->first('value')];
                }

                $layer['image'] = $this->media->store($image);
            }

            if ($type === 'button') {
                $link = is_array($raw['link'] ?? null) ? $raw['link'] : null;
                $empty = $link === null || Link::fromArray($link)->isEmpty();

                if ($empty && $layer['text'] === []) {
                    continue;
                }

                if ($empty) {
                    $errors["{$at}.link"] = [(string) __('webx-banners::errors.button-link')];
                } else {
                    $problem = $linkProblem($link);

                    if ($problem !== null) {
                        $errors["{$at}.link"] = [$problem];
                    }
                }

                $variant = $raw['variant'] ?? null;

                if (is_string($variant) && $variant !== '' && ! $this->variants->has($variant) && ! in_array($variant, $stored, true)) {
                    $errors["{$at}.variant"] = [(string) __('webx-banners::errors.variant', ['variants' => implode(', ', $this->variants->keys())])];
                }

                $layer['link'] = $link;
                $layer['variant'] = is_string($variant) && $variant !== '' ? $variant : null;
            }

            if ($type === 'buttons' && ++$groups > 1) {
                $errors[$at] = [(string) __('webx-banners::errors.layer-buttons-once')];
            }

            [$layer['style'], $styleErrors] = $this->style($raw['style'] ?? null, "{$at}.style");
            [$layer['frames'], $frameErrors] = $this->frames($raw['frames'] ?? null, "{$at}.frames");
            [$layer['motion'], $motionErrors] = $this->motion($raw['motion'] ?? null, "{$at}.motion");

            $errors = [...$errors, ...$styleErrors, ...$frameErrors, ...$motionErrors];
            $layers[] = $layer;
        }

        return [$layers, $errors];
    }

    /**
     * The words of a layer: a map of languages, always (decision 6) — a plain string from an agent
     * speaking one language is that language's.
     *
     * @return array{0: array<string, string>, 1: string|null}
     */
    private function text(mixed $value): array
    {
        $map = is_array($value) ? $value : (is_string($value) ? [$this->locales->content() => $value] : []);
        $text = [];
        $problem = null;

        foreach ($map as $code => $words) {
            if (! is_string($code) || ! is_string($words) || trim($words) === '') {
                continue;
            }

            if (mb_strlen(trim($words)) > self::TEXT_MAX) {
                $problem = (string) __('webx-banners::errors.layer-text-long', ['max' => self::TEXT_MAX]);
            }

            $text[$code] = trim($words);
        }

        return [$text, $problem];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function style(mixed $value, string $at): array
    {
        $value = is_array($value) ? $value : [];
        $errors = [];
        $style = [];

        $font = $value['font'] ?? null;
        $style['font'] = is_string($font) && in_array($font, self::FONTS, true) ? $font : null;

        foreach (self::STYLE as $key => [$min, $max, $default]) {
            [$style[$key], $problem] = self::number($value[$key] ?? null, $min, $max, $default);

            if ($problem) {
                $errors["{$at}.{$key}"] = [self::range($min, $max)];
            }
        }

        $style['weight'] = (int) (round($style['weight'] / 100) * 100);
        $style['italic'] = ($value['italic'] ?? false) === true;
        $style['uppercase'] = ($value['uppercase'] ?? false) === true;
        $style['shadow'] = ($value['shadow'] ?? false) === true;

        $align = $value['align'] ?? 'start';
        $style['align'] = is_string($align) && in_array($align, self::ALIGNS, true) ? $align : 'start';

        foreach (['color', 'background'] as $key) {
            $color = $value[$key] ?? null;
            $color = is_string($color) ? strtolower(trim($color)) : null;

            if ($color !== null && $color !== '' && preg_match(self::COLOR, $color) !== 1) {
                $errors["{$at}.{$key}"] = [(string) __('webx-banners::errors.layer-color')];
            }

            $style[$key] = $color === '' ? null : $color;
        }

        return [$style, $errors];
    }

    /**
     * Both layouts: the computer's always, the phone's or null — then the phone takes the
     * computer's (decision 3).
     *
     * @return array{0: array{desktop: array<string, mixed>, mobile: array<string, mixed>|null}, 1: array<string, list<string>>}
     */
    private function frames(mixed $value, string $at): array
    {
        $value = is_array($value) ? $value : [];
        [$desktop, $errors] = $this->frame($value['desktop'] ?? null, "{$at}.desktop");
        $mobile = null;

        if (is_array($value['mobile'] ?? null)) {
            [$mobile, $mobileErrors] = $this->frame($value['mobile'], "{$at}.mobile");
            $errors = [...$errors, ...$mobileErrors];
        }

        return [['desktop' => $desktop, 'mobile' => $mobile], $errors];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function frame(mixed $value, string $at): array
    {
        $value = is_array($value) ? $value : [];
        $frame = [];
        $errors = [];

        foreach (self::FRAME as $key => [$min, $max, $default]) {
            [$frame[$key], $problem] = self::number($value[$key] ?? null, $min, $max, $default);

            if ($problem) {
                $errors["{$at}.{$key}"] = [self::range($min, $max)];
            }
        }

        $origin = $value['origin'] ?? 'tl';
        $frame['origin'] = is_string($origin) && in_array($origin, self::ORIGINS, true) ? $origin : 'tl';
        $frame['hidden'] = ($value['hidden'] ?? false) === true;

        return [$frame, $errors];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, list<string>>}
     */
    private function motion(mixed $value, string $at): array
    {
        $value = is_array($value) ? $value : [];
        $errors = [];

        $in = $value['in'] ?? null;
        $loop = $value['loop'] ?? null;
        $motion = [
            'in' => is_string($in) && in_array($in, self::ENTRANCES, true) ? $in : null,
            'loop' => is_string($loop) && in_array($loop, self::LOOPS, true) ? $loop : null,
        ];

        foreach (self::MOTION as $key => [$min, $max, $default]) {
            [$number, $problem] = self::number($value[$key] ?? null, $min, $max, $default);
            $motion[$key] = (int) round($number);

            if ($problem) {
                $errors["{$at}.{$key}"] = [self::range($min, $max)];
            }
        }

        return [$motion, $errors];
    }

    /**
     * A number inside its range, rounded to hundredths; nothing at all is the default.
     *
     * @return array{0: float|int, 1: bool} the number, and whether it was refused
     */
    private static function number(mixed $value, float|int $min, float|int $max, float|int $default): array
    {
        if ($value === null || $value === '') {
            return [$default, false];
        }

        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return [$default, true];
        }

        $number = round((float) $value, 2);

        if ($number < $min || $number > $max) {
            return [$default, true];
        }

        return [$number == (int) $number ? (int) $number : $number, false];
    }

    private static function range(float|int $min, float|int $max): string
    {
        return (string) __('webx-banners::errors.layer-range', ['min' => $min, 'max' => $max]);
    }

    /**
     * The variants this banner's button layers already have — kept when the config lost them
     * (decision 11 of the banners spec).
     *
     * @return list<string>
     */
    private function storedVariants(Banner $banner): array
    {
        $variants = [];

        foreach ($banner->layerRows() as $row) {
            if (is_string($row['variant'] ?? null)) {
                $variants[] = $row['variant'];
            }
        }

        return $variants;
    }

    /** @param  list<string>  $taken */
    private function freshId(array $taken): string
    {
        do {
            $id = substr(bin2hex(random_bytes(4)), 0, 6);
        } while (in_array($id, $taken, true));

        return $id;
    }
}
