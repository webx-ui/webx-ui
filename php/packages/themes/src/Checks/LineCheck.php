<?php

declare(strict_types=1);

namespace WebxUi\Themes\Checks;

use Illuminate\Support\Arr;
use Throwable;
use WebxUi\Themes\ThemeManifest;

/**
 * Rule 26 of spec §16.1 for one layer: every language the layer declares in `locales` (§5) has the
 * words of the layout that `en` has — the same keys in `lang/<locale>/<group>.php`, no more, no
 * fewer. A key added in English and forgotten elsewhere does not break the page: `__()` prints the
 * key itself, `theme.blocks.contact.phone`, and only a visitor in that language ever sees it.
 *
 * Only the declared languages are compared. A layer may override a word or two in a language it
 * does not claim to cover — a local theme that renames "Read more" in Polish alone — and the rest
 * comes from the layers below; claiming the language in `locales` is what makes it a promise.
 */
final class LineCheck
{
    /**
     * @return array{findings: list<array{file: string, line: int|null, message: string}>, locales: int}
     */
    public static function check(ThemeManifest $layer): array
    {
        $lang = $layer->path.'/lang';
        $groups = glob($lang.'/en/*.php') ?: [];
        $findings = [];
        $compared = 0;

        if ($groups === []) {
            // Nothing to compare with: fine for a layer with no words, a gap for one with words elsewhere.
            foreach ($layer->locales as $locale) {
                if ($locale !== 'en' && (glob("{$lang}/{$locale}/*.php") ?: []) !== []) {
                    $findings[] = ['file' => "{$lang}/{$locale}", 'line' => null, 'message' => "words of the layout in \"{$locale}\", but no lang/en/ to compare them with — en holds every key"];
                }
            }

            return ['findings' => $findings, 'locales' => 0];
        }

        $english = [];

        foreach ($groups as $source) {
            $english[$source] = self::keys($source, $findings);
        }

        foreach ($layer->locales as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $compared++;

            foreach ($groups as $source) {
                $group = basename($source, '.php');
                $file = "{$lang}/{$locale}/{$group}.php";

                if (! is_file($file)) {
                    $findings[] = ['file' => $file, 'line' => null, 'message' => "the theme declares \"{$locale}\" in locales, but has no lang/{$locale}/{$group}.php"];

                    continue;
                }

                $words = self::keys($file, $findings);

                if ($english[$source] === null || $words === null) {
                    continue;
                }

                if (($missing = array_values(array_diff($english[$source], $words))) !== []) {
                    $findings[] = ['file' => $file, 'line' => null, 'message' => self::list('missing', $missing, $locale)];
                }

                if (($extra = array_values(array_diff($words, $english[$source]))) !== []) {
                    $findings[] = ['file' => $file, 'line' => null, 'message' => self::list('not in en', $extra, $locale)];
                }
            }
        }

        return ['findings' => $findings, 'locales' => $compared + (in_array('en', $layer->locales, true) ? 1 : 0)];
    }

    /**
     * The dotted keys of a words file, or null when it is not one — said once, as a finding.
     *
     * @param  list<array{file: string, line: int|null, message: string}>  $findings
     * @return list<string>|null
     */
    private static function keys(string $file, array &$findings): ?array
    {
        try {
            $words = require $file;
        } catch (Throwable $failure) {
            $words = null;
        }

        if (! is_array($words)) {
            $findings[] = ['file' => $file, 'line' => null, 'message' => 'not a words file: it returns an array of strings'];

            return null;
        }

        return array_map('strval', array_keys(Arr::dot($words)));
    }

    /** @param list<string> $keys */
    private static function list(string $what, array $keys, string $locale): string
    {
        $shown = array_slice($keys, 0, 5);
        $more = count($keys) > 5 ? ' and '.(count($keys) - 5).' more' : '';

        return sprintf('%s: %s%s — "%s" in locales has every key of en, and only those', $what, implode(', ', $shown), $more, $locale);
    }
}
