<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use WebxUi\Themes\ThemeAssets;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeFonts;
use WebxUi\Themes\ThemeManifest;

/**
 * Rule 27 of spec §16.1 (§15.3): the fonts of the chain are the theme's own files, and they cover
 * the scripts of every language they will be asked to draw — the site's languages, and the ones
 * the layer that ships them declares in `locales`. Without a file for `latin-ext` a Polish page
 * draws `ą` and `ł` in the fallback font, the rest of the word in the theme's, and nothing but
 * a reader's eye ever notices.
 *
 * Each entry of `fonts` is read as the page would read it ({@see ThemeFonts::face()}); what the
 * page would skip in silence is said here, with the reason.
 */
final class FontCheck
{
    /**
     * @param  list<string>  $locales  The site's languages.
     * @return array{findings: list<array{file: string, line: int|null, message: string}>, notes: list<array{file: string, line: int|null, message: string}>, faces: list<array{family: string, weight: string, style: string, files: array<string, string>, layer: ThemeManifest}>}
     */
    public static function check(ThemeChain $chain, array $locales): array
    {
        $findings = [];
        $notes = [];

        foreach ($chain->layers as $layer) {
            foreach ($layer->fonts as $index => $entry) {
                foreach (self::problems($layer, $entry) as $problem) {
                    $findings[] = ['file' => self::manifest($layer), 'line' => null, 'message' => "fonts[{$index}]: {$problem}"];
                }

                foreach (self::advice($entry) as $advice) {
                    $notes[] = ['file' => self::manifest($layer), 'line' => null, 'message' => "fonts[{$index}]: {$advice}"];
                }
            }
        }

        $faces = ThemeFonts::faces($chain);

        foreach ($faces as $face) {
            // The site's languages, and the ones the layer promises words in: a theme that speaks
            // Ukrainian draws it in its own font, on whatever site it is installed.
            $wanted = array_values(array_unique([...$locales, ...$face['layer']->locales]));

            foreach (ThemeFonts::gaps([$face], $wanted) as $gap) {
                foreach ($gap['missing'] as $script => $languages) {
                    $findings[] = [
                        'file' => self::manifest($face['layer']),
                        'line' => null,
                        'message' => sprintf(
                            'font "%s" %s %s has no file for %s, which %s %s — add one under "files" (rule 27), or the letters of %s are drawn in the fallback font',
                            $face['family'],
                            $face['style'],
                            $face['weight'],
                            $script,
                            implode(', ', $languages),
                            count($languages) === 1 ? 'needs' : 'need',
                            $script,
                        ),
                    ];
                }
            }
        }

        return ['findings' => $findings, 'notes' => $notes, 'faces' => $faces];
    }

    /**
     * What is wrong with one entry of a layer's `fonts`.
     *
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    public static function problems(ThemeManifest $layer, array $entry): array
    {
        $problems = [];
        $family = $entry['family'] ?? null;

        if (! is_string($family) || preg_match(ThemeFonts::FAMILY_PATTERN, trim($family)) !== 1) {
            $problems[] = '"family" is the name of the font — letters, digits, spaces, dots, dashes';
        }

        if (array_key_exists('weight', $entry) && (! is_string($entry['weight']) || preg_match(ThemeFonts::WEIGHT_PATTERN, $entry['weight']) !== 1)) {
            $problems[] = '"weight" is "400", or a range of a variable font, "100 900"';
        }

        if (array_key_exists('style', $entry) && ! in_array($entry['style'], ThemeFonts::STYLES, true)) {
            $problems[] = '"style" is "normal" or "italic"';
        }

        if (array_key_exists('scripts', $entry)) {
            $problems[] = '"scripts" is not read — the scripts are the keys of "files": { "latin": "fonts/….woff2" }';
        }

        $files = $entry['files'] ?? null;

        if (! is_array($files) || $files === [] || array_is_list($files)) {
            $problems[] = '"files" maps each script to its file under assets/: { "latin": "fonts/….woff2", "cyrillic": … }';

            return $problems;
        }

        foreach ($files as $script => $path) {
            $script = (string) $script;

            if (! isset(ThemeFonts::SCRIPTS[$script])) {
                $problems[] = "\"{$script}\" is not a script the check knows: ".implode(', ', array_reverse(array_keys(ThemeFonts::SCRIPTS)));

                continue;
            }

            $relative = is_string($path) ? ThemeAssets::normalise($path) : null;

            if ($relative === null) {
                $problems[] = "{$script}: a path under assets/, not a URL — the font is the theme's own file, not a CDN's (rule 27)";

                continue;
            }

            if (ThemeFonts::format($relative) === null) {
                $problems[] = "{$script}: assets/{$relative} is not a web font — .woff2, or .woff";

                continue;
            }

            if (! is_file($layer->path.'/assets/'.$relative)) {
                $problems[] = "{$script}: assets/{$relative} does not exist";
            }
        }

        return $problems;
    }

    /**
     * What works but costs the visitor more than it needs to (rule 39): a `.woff` is a third
     * larger than the same font in `.woff2`, which every browser the site supports reads. Said as
     * a note, not a finding — the page draws the same letters either way.
     *
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    public static function advice(array $entry): array
    {
        $advice = [];

        foreach (is_array($entry['files'] ?? null) ? $entry['files'] : [] as $script => $path) {
            $relative = is_string($path) ? ThemeAssets::normalise($path) : null;

            if ($relative !== null && ThemeFonts::format($relative) === 'woff') {
                $advice[] = "{$script}: assets/{$relative} is .woff — the same font in .woff2 is a third smaller (rule 39)";
            }
        }

        return $advice;
    }

    private static function manifest(ThemeManifest $layer): string
    {
        return $layer->path.($layer->local ? '/theme.json' : '/composer.json');
    }
}
