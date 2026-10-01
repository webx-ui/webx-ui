<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore;

/**
 * The words a search would have been had it been typed with the other keyboard layout on
 * (decision 16 of the Manticore spec): «xt[jk» is «чехол» typed with English on. Only among the
 * languages of the site — a shop in English and German has nothing to gain from Cyrillic.
 *
 * A layout is the characters of the keys of a US keyboard, row by row, and the same with Shift:
 * 47 each. The config holds them (`webx-catalog-manticore.layouts`), so that a site adds its own
 * without waiting for the package; a language without one is not tried.
 */
final class KeyboardLayouts
{
    private const KEYS = 47;

    /**
     * @param  array<string, mixed>  $layouts  language → [the keys, the keys with Shift]
     */
    public function __construct(private readonly array $layouts) {}

    /**
     * Every other spelling of the search among the site's languages, those meant for the page's
     * own language first; never the search itself.
     *
     * @param  list<string>  $locales  the site's languages
     * @return list<string>
     */
    public function alternatives(string $search, array $locales, string $locale): array
    {
        $keys = [];

        foreach ($locales as $code) {
            $layout = $this->layout($code);

            if ($layout !== null) {
                $keys[$code] = $layout;
            }
        }

        // The page's own language first: a reader on the Russian page meant Russian.
        uksort($keys, static fn (string $a, string $b): int => ($b === $locale) <=> ($a === $locale));

        $found = [];

        foreach ($keys as $to => $target) {
            foreach ($keys as $from => $source) {
                if ($from === $to || $source === $target) {
                    continue;
                }

                $spelt = self::retype($search, $source, $target);

                if ($spelt !== null && $spelt !== $search && ! in_array($spelt, $found, true)) {
                    $found[] = $spelt;
                }
            }
        }

        return $found;
    }

    /**
     * The search typed on the keys of `$source` read as `$target`; null when some character of it
     * is on no key of `$source` — then it was not typed with that layout on.
     *
     * @param  list<string>  $source
     * @param  list<string>  $target
     */
    private static function retype(string $search, array $source, array $target): ?string
    {
        $position = [];

        // The first of two equal characters wins: the key a person would press for it.
        foreach ($source as $index => $character) {
            $position[$character] ??= $index;
        }

        $spelt = '';

        foreach (mb_str_split($search) as $character) {
            if (isset($position[$character])) {
                $spelt .= $target[$position[$character]];
            } elseif (trim($character) === '') {
                $spelt .= $character;
            } else {
                return null;
            }
        }

        return $spelt;
    }

    /**
     * The keys of a language, both rows of 47 joined; null when it has none or they are not 47.
     *
     * @return list<string>|null
     */
    private function layout(string $locale): ?array
    {
        $layout = $this->layouts[$locale] ?? $this->layouts[strtolower((string) preg_split('/[-_]/', $locale)[0])] ?? null;

        if (! is_array($layout) || count($layout) !== 2) {
            return null;
        }

        [$plain, $shifted] = array_values($layout);

        if (! is_string($plain) || ! is_string($shifted)) {
            return null;
        }

        $plain = mb_str_split($plain);
        $shifted = mb_str_split($shifted);

        return count($plain) === self::KEYS && count($shifted) === self::KEYS ? [...$plain, ...$shifted] : null;
    }
}
