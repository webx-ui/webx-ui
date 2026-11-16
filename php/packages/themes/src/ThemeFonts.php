<?php

declare(strict_types=1);

namespace WebxUi\Themes;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Throwable;
use WebxUi\Localization\Locales;

/**
 * The fonts of the chain (spec §15.3): what `fonts` in each layer's manifest says, as
 * `@font-face` rules for `@webxTheme`, and which scripts the languages of a site need.
 *
 *     "fonts": [{
 *       "family": "Inter", "weight": "100 900", "style": "normal",
 *       "files": { "latin": "fonts/inter-latin.woff2", "cyrillic": "fonts/inter-cyrillic.woff2" }
 *     }]
 *
 * A face has one file per script, under the layer's `assets/`, and each file is printed with the
 * `unicode-range` of its script — so a browser downloads the Cyrillic file only for a page that
 * has a Cyrillic letter to draw, and an English page never does. The files are the theme's own:
 * a font from somebody's CDN hands every visitor's address to a third party (§2, decision 11).
 *
 * A family a higher layer declares replaces the same family below whole: a local theme that ships
 * Inter with Greek added is the Inter of the site, not a second one beside it.
 *
 * Nothing here throws. A malformed entry is skipped on the page and named by
 * `webx:theme:check` ({@see Checks\FontCheck}).
 */
final class ThemeFonts
{
    /**
     * The scripts a file can cover, with the ranges Google Fonts and Fontsource cut their subsets
     * by — a font downloaded from either fits as it is. In the order the faces are printed: where
     * two ranges overlap (a combining accent is in several), the face declared last wins, and
     * that should be the one every page loads anyway, `latin`.
     */
    public const array SCRIPTS = [
        'cyrillic-ext' => 'U+0460-052F, U+1C80-1C8A, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F',
        'cyrillic' => 'U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116',
        'greek-ext' => 'U+1F00-1FFF',
        'greek' => 'U+0370-0377, U+037A-037F, U+0384-038A, U+038C, U+038E-03A1, U+03A3-03FF',
        'vietnamese' => 'U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB',
        'latin-ext' => 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF',
        'latin' => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
    ];

    /**
     * What a language needs besides `latin`, which every page needs for its digits, spaces and
     * punctuation. Polish `ą ł`, Turkish `ğ ş`, Czech `ř` are past U+00FF, in `latin-ext`;
     * German, French and Spanish are not. A language missing here is one the check cannot judge,
     * and it says nothing about it rather than something wrong.
     */
    public const array LANGUAGES = [
        'af' => [], 'ca' => ['latin-ext'], 'cs' => ['latin-ext'], 'cy' => ['latin-ext'], 'da' => [],
        'de' => [], 'en' => [], 'eo' => ['latin-ext'], 'es' => [], 'et' => ['latin-ext'], 'eu' => [],
        'fi' => [], 'fr' => [], 'ga' => [], 'gl' => [], 'hr' => ['latin-ext'], 'hu' => ['latin-ext'],
        'id' => [], 'is' => [], 'it' => [], 'lt' => ['latin-ext'], 'lv' => ['latin-ext'], 'ms' => [],
        'mt' => ['latin-ext'], 'nb' => [], 'nl' => [], 'nn' => [], 'no' => [], 'pl' => ['latin-ext'],
        'pt' => [], 'ro' => ['latin-ext'], 'sk' => ['latin-ext'], 'sl' => ['latin-ext'], 'sq' => [],
        'sv' => [], 'sw' => [], 'tr' => ['latin-ext'], 'az' => ['latin-ext'], 'uz' => ['latin-ext'],
        'bs' => ['latin-ext'],
        'be' => ['cyrillic'], 'bg' => ['cyrillic'], 'mk' => ['cyrillic'], 'ru' => ['cyrillic'],
        'sr' => ['cyrillic'], 'uk' => ['cyrillic'],
        'kk' => ['cyrillic', 'cyrillic-ext'], 'ky' => ['cyrillic', 'cyrillic-ext'],
        'mn' => ['cyrillic', 'cyrillic-ext'], 'tg' => ['cyrillic', 'cyrillic-ext'],
        'tt' => ['cyrillic', 'cyrillic-ext'], 'ba' => ['cyrillic', 'cyrillic-ext'],
        'el' => ['greek'],
        'vi' => ['vietnamese'],
    ];

    /** A family name that needs no escaping inside quotes in a stylesheet. */
    public const string FAMILY_PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u';

    public const string WEIGHT_PATTERN = '/^[1-9]00(?: [1-9]00)?$/';

    public const array STYLES = ['normal', 'italic'];

    /** The formats a browser is told about; anything else is not a web font. */
    public const array FORMATS = ['woff2' => 'woff2', 'woff' => 'woff'];

    /** The metric overrides a `fallback` may set, in CSS's own names. */
    public const array OVERRIDES = ['size-adjust', 'ascent-override', 'descent-override', 'line-gap-override'];

    /** A percentage of an override: `101.45%`, `0%`. */
    public const string PERCENT_PATTERN = '/^\d{1,3}(?:\.\d{1,4})?%$/';

    /** The family a `fallback` draws with until the file arrives, when the theme names none. */
    public const array FALLBACK_LOCAL = ['Arial'];

    /** The scripts that are an alphabet of their own: a page in it preloads that file, not latin. */
    private const array ALPHABETS = ['cyrillic', 'greek'];

    public function __construct(
        private readonly ThemeChain $chain,
        private readonly ThemeAssets $assets,
    ) {}

    /**
     * The faces the site uses: every well-formed entry of every layer, a family of a higher layer
     * replacing the same family below.
     *
     * @return list<array{family: string, weight: string, style: string, files: array<string, string>, preload: bool, fallback: array<string, string|list<string>>|null, layer: ThemeManifest}>
     */
    public static function faces(ThemeChain $chain): array
    {
        $faces = [];
        $taken = [];

        foreach ($chain->layers as $layer) {
            $mine = [];

            foreach ($layer->fonts as $entry) {
                $face = self::face($entry);

                if ($face === null || isset($taken[mb_strtolower($face['family'])])) {
                    continue;
                }

                $mine[mb_strtolower($face['family'])] = true;
                $faces[] = [...$face, 'layer' => $layer];
            }

            $taken += $mine;
        }

        return $faces;
    }

    /**
     * One entry of `fonts`, or null when it is not a face the page can print: no family, a
     * family that would need escaping, no file of a known script. A `preload` that is not `true`
     * and a `fallback` that is not well formed are dropped rather than the face: the page keeps
     * its letters, `webx:theme:check` names the mistake.
     *
     * @param  array<string, mixed>  $entry
     * @return array{family: string, weight: string, style: string, files: array<string, string>, preload: bool, fallback: array<string, string|list<string>>|null}|null
     */
    public static function face(array $entry): ?array
    {
        $family = $entry['family'] ?? null;
        $weight = $entry['weight'] ?? '400';
        $style = $entry['style'] ?? 'normal';
        $files = $entry['files'] ?? null;

        if (! is_string($family) || preg_match(self::FAMILY_PATTERN, trim($family)) !== 1
            || ! is_string($weight) || preg_match(self::WEIGHT_PATTERN, $weight) !== 1
            || ! in_array($style, self::STYLES, true)
            || ! is_array($files)) {
            return null;
        }

        $known = [];

        foreach ($files as $script => $path) {
            if (is_string($script) && isset(self::SCRIPTS[$script]) && is_string($path)
                && ($path = ThemeAssets::normalise($path)) !== null && self::format($path) !== null) {
                $known[$script] = $path;
            }
        }

        if ($known === []) {
            return null;
        }

        // In the order of SCRIPTS, whatever order the manifest wrote them in.
        $ordered = array_intersect_key(array_replace(self::SCRIPTS, $known), $known);

        return [
            'family' => trim($family),
            'weight' => $weight,
            'style' => $style,
            'files' => $ordered,
            'preload' => ($entry['preload'] ?? false) === true,
            'fallback' => self::fallback($entry['fallback'] ?? null),
        ];
    }

    /**
     * A `fallback` of a face: the local family the page draws with until the file arrives,
     * stretched to the font's metrics so the text does not move when it swaps (rule 39) —
     *
     *     "fallback": { "local": ["Arial", "Helvetica"], "size-adjust": "101.45%",
     *                   "ascent-override": "93.64%", "descent-override": "24.64%", "line-gap-override": "0%" }
     *
     * Null when there is none or it is not well formed: an unknown key, a value that is not a
     * percentage, a local name that would need escaping.
     *
     * @return array<string, string|list<string>>|null `local` → the families, each override → its value.
     */
    public static function fallback(mixed $value): ?array
    {
        if (! is_array($value) || array_is_list($value) && $value !== []) {
            return null;
        }

        $local = $value['local'] ?? self::FALLBACK_LOCAL;
        $local = is_string($local) ? [$local] : $local;

        if (! is_array($local) || $local === [] || ! array_is_list($local)) {
            return null;
        }

        foreach ($local as $name) {
            if (! is_string($name) || preg_match(self::FAMILY_PATTERN, trim($name)) !== 1) {
                return null;
            }
        }

        $fallback = ['local' => array_values(array_map(static fn (string $name): string => trim($name), $local))];

        foreach ($value as $key => $setting) {
            if ($key === 'local') {
                continue;
            }

            if (! in_array($key, self::OVERRIDES, true) || ! is_string($setting) || preg_match(self::PERCENT_PATTERN, $setting) !== 1) {
                return null;
            }

            $fallback[$key] = $setting;
        }

        return $fallback;
    }

    /** `"Inter Fallback"`: the family a `fallback` is printed under, after its font in the stack. */
    public static function fallbackFamily(string $family): string
    {
        return $family.' Fallback';
    }

    /**
     * The families that have a fallback, lower-cased → the fallback's family — what `Tokens`
     * puts after each in a font stack. The first face of a family with a fallback decides.
     *
     * @return array<string, string>
     */
    public static function fallbacks(ThemeChain $chain): array
    {
        $families = [];

        foreach (self::faces($chain) as $face) {
            $key = mb_strtolower($face['family']);

            if ($face['fallback'] !== null && ! isset($families[$key])) {
                $families[$key] = self::fallbackFamily($face['family']);
            }
        }

        return $families;
    }

    /**
     * A font stack with each family that has a fallback followed by it: `Inter, Arial` becomes
     * `Inter, "Inter Fallback", Arial`. A stack that already names the fallback, or names no such
     * family, comes back as it was.
     *
     * @param  array<string, string>  $fallbacks  {@see fallbacks()}
     */
    public static function withFallbacks(string $stack, array $fallbacks): string
    {
        if ($fallbacks === [] || str_contains($stack, 'var(')) {
            return $stack;
        }

        $items = array_map('trim', explode(',', $stack));
        $named = array_map(static fn (string $item): string => mb_strtolower(trim($item, "\"' ")), $items);
        $out = [];

        foreach ($items as $i => $item) {
            $out[] = $item;
            $fallback = $fallbacks[$named[$i]] ?? null;

            if ($fallback !== null && ! in_array(mb_strtolower($fallback), $named, true)) {
                $out[] = '"'.$fallback.'"';
            }
        }

        return count($out) === count($items) ? $stack : implode(', ', $out);
    }

    /**
     * The scripts whose files a page in this language preloads: the language's own alphabet —
     * `cyrillic` for `uk`, `greek` for `el` — and `latin` for every language written in it.
     * Not the `-ext` files: they draw a few letters of a page, which `unicode-range` fetches
     * when a letter asks. An unknown language is read as latin.
     *
     * @return list<string>
     */
    public static function preloaded(string $locale): array
    {
        $alphabets = array_values(array_intersect(self::needs($locale) ?? ['latin'], self::ALPHABETS));

        return $alphabets === [] ? ['latin'] : $alphabets;
    }

    /**
     * `<link rel="preload">` for the files of the faces that ask for it (`"preload": true`), only
     * of the scripts the page's language is written in ({@see preloaded()}): a preload is a
     * download whatever the page shows, so an English page never preloads Cyrillic. A file sync
     * has not published is left out, as in {@see css()}.
     *
     * @return list<string>
     */
    public function preloads(UrlGenerator $urls, string $locale): array
    {
        $scripts = self::preloaded($locale);
        $links = [];

        foreach (self::faces($this->chain) as $face) {
            if (! $face['preload']) {
                continue;
            }

            foreach (array_intersect_key($face['files'], array_flip($scripts)) as $path) {
                $url = $this->assets->url($face['layer'], 'assets/'.$path, $urls);

                if ($url !== null) {
                    $links[$url] = '<link rel="preload" href="'.e($url).'" as="font" type="font/'.self::format($path).'" crossorigin>';
                }
            }
        }

        return array_values($links);
    }

    /**
     * The `@font-face` of each fallback: a local family under the font's name plus ` Fallback`,
     * with the overrides that give it the font's size and line — so the swap moves nothing.
     */
    public function fallbackCss(): string
    {
        $rules = [];

        foreach (self::faces($this->chain) as $face) {
            $family = self::fallbackFamily($face['family']);

            if ($face['fallback'] === null || isset($rules[$family])) {
                continue;
            }

            $lines = ['@font-face {', '  font-family: "'.$family.'";'];
            $lines[] = '  src: '.implode(', ', array_map(static fn (string $name): string => 'local("'.$name.'")', (array) $face['fallback']['local'])).';';

            foreach (self::OVERRIDES as $override) {
                if (isset($face['fallback'][$override]) && is_string($face['fallback'][$override])) {
                    $lines[] = "  {$override}: {$face['fallback'][$override]};";
                }
            }

            $lines[] = '}';
            $rules[$family] = implode("\n", $lines);
        }

        return implode("\n", $rules);
    }

    /** `woff2` for `fonts/inter-latin.woff2`; null for a file a browser would not take as a web font. */
    public static function format(string $path): ?string
    {
        return self::FORMATS[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * The scripts a language needs, `latin` first; null for a language the check does not know.
     * `pt-BR` and `sr_Latn` are read by their language alone.
     *
     * @return list<string>|null
     */
    public static function needs(string $locale): ?array
    {
        $language = strtolower((string) preg_split('/[-_]/', trim($locale))[0]);
        $extra = self::LANGUAGES[$language] ?? null;

        return $extra === null ? null : ['latin', ...$extra];
    }

    /**
     * What each face lacks for the languages given: the scripts a language needs that the face
     * has no file for.
     *
     * @param  list<array{family: string, weight: string, style: string, files: array<string, string>, layer: ThemeManifest}>  $faces
     * @param  list<string>  $locales
     * @return list<array{face: array{family: string, weight: string, style: string, files: array<string, string>, layer: ThemeManifest}, missing: array<string, list<string>>}> Missing script → the languages that need it.
     */
    public static function gaps(array $faces, array $locales): array
    {
        $gaps = [];

        foreach ($faces as $face) {
            $missing = [];

            foreach (array_unique($locales) as $locale) {
                foreach (self::needs($locale) ?? [] as $script) {
                    if (! isset($face['files'][$script])) {
                        $missing[$script][] = $locale;
                    }
                }
            }

            if ($missing !== []) {
                $gaps[] = ['face' => $face, 'missing' => $missing];
            }
        }

        return $gaps;
    }

    /**
     * The languages the site publishes in: the `locales` table when the localization package is
     * there and answers, the application's locale otherwise.
     *
     * @return list<string>
     */
    public static function siteLocales(Application $app): array
    {
        if (class_exists(Locales::class) && $app->bound(Locales::class)) {
            try {
                $codes = $app->make(Locales::class)->codes();

                if ($codes !== []) {
                    return array_values(array_map('strval', $codes));
                }
            } catch (Throwable) {
                // No database yet: a check run before migrate still has the application's locale.
            }
        }

        $locale = $app->make('config')->get('app.locale');

        return is_string($locale) && $locale !== '' ? [$locale] : [];
    }

    /**
     * The `@font-face` rules for the page. A face of a layer that sync has not published is
     * left out — the page falls back to the next family of the stack, not to a 404 per letter
     * — and `$missing` hears about it.
     *
     * @param  (callable(string): void)|null  $missing
     */
    public function css(UrlGenerator $urls, ?callable $missing = null): string
    {
        $rules = [];

        foreach (self::faces($this->chain) as $face) {
            foreach ($face['files'] as $script => $path) {
                $url = $this->assets->url($face['layer'], 'assets/'.$path, $urls);

                if ($url === null) {
                    if ($missing !== null) {
                        $missing("assets/{$path} of {$face['layer']->name}");
                    }

                    continue;
                }

                $rules[] = implode("\n", [
                    '@font-face {',
                    '  font-family: "'.$face['family'].'";',
                    '  font-style: '.$face['style'].';',
                    '  font-weight: '.$face['weight'].';',
                    '  font-display: swap;',
                    '  src: url("'.str_replace(['"', '\\', "\n"], ['%22', '%5C', ''], $url).'") format("'.self::format($path).'");',
                    '  unicode-range: '.self::SCRIPTS[$script].';',
                    '}',
                ]);
            }
        }

        return implode("\n", $rules);
    }
}
