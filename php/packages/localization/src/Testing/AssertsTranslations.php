<?php

declare(strict_types=1);

namespace WebxUi\Localization\Testing;

/**
 * The keys of a module's `lang/<language>/*.php` are the same in every language it has, and
 * English is there: it is the reference of what exists and what the panel falls back to. A missing
 * line shows English; a missing English line shows the key.
 *
 * For a module outside the monorepo — a module of the site (WEBX_UI_SITE_MODULES.md §10). It
 * speaks English and the languages of the site's panel, not the ten every package of the monorepo
 * ships: those are checked there by `TranslationsCoverageTest`. Works in a plain PHPUnit case.
 *
 *     $this->assertTranslationsMatch(dirname(__DIR__).'/lang', config('webx-localization.panel'));
 */
trait AssertsTranslations
{
    /**
     * @param  string  $lang  The `lang` folder of the module.
     * @param  list<string>  $languages  Languages that must be there besides English; any other
     *                                   folder present is compared too, and not required.
     */
    protected function assertTranslationsMatch(string $lang, array $languages = []): void
    {
        $lang = rtrim($lang, '/');
        $present = array_map('basename', glob($lang.'/*', GLOB_ONLYDIR) ?: []);
        $problems = [];

        foreach (array_unique(['en', ...$languages]) as $language) {
            if (! in_array($language, $present, true)) {
                $problems[] = "{$lang}/{$language}: there is no such language";
            }
        }

        $english = in_array('en', $present, true) ? self::translationKeys($lang.'/en') : [];

        if ($english === [] && in_array('en', $present, true)) {
            $problems[] = "{$lang}/en: no lines, and en decides what exists";
        }

        foreach ($present as $language) {
            if ($language === 'en' || $english === []) {
                continue;
            }

            $keys = self::translationKeys($lang.'/'.$language);

            foreach (array_diff($english, $keys) as $key) {
                $problems[] = "{$language}: [{$key}] is in en and not here";
            }

            foreach (array_diff($keys, $english) as $key) {
                $problems[] = "{$language}: [{$key}] is here and not in en";
            }
        }

        $this->assertSame([], $problems, "The languages of the module do not say the same:\n".implode("\n", $problems));
    }

    /**
     * Every line of a language folder as `group.key.path`, sorted.
     *
     * @return list<string>
     */
    private static function translationKeys(string $directory): array
    {
        $keys = [];

        foreach (glob($directory.'/*.php') ?: [] as $file) {
            $lines = require $file;

            foreach (self::flattenTranslations(is_array($lines) ? $lines : [], basename($file, '.php')) as $key) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param  array<mixed>  $lines
     * @return list<string>
     */
    private static function flattenTranslations(array $lines, string $prefix): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            $path = $prefix.'.'.$key;

            if (is_array($value)) {
                array_push($keys, ...self::flattenTranslations($value, $path));

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }
}
