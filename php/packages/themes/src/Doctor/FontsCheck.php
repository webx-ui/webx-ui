<?php

declare(strict_types=1);

namespace WebxUi\Themes\Doctor;

use Illuminate\Contracts\Foundation\Application;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Themes\Checks\FontCheck;
use WebxUi\Themes\ThemeChain;
use WebxUi\Themes\ThemeFonts;

/**
 * The fonts of the theme against the languages the site publishes in (spec §15.3), in the
 * command a deploy already runs. A language is added in the panel long after the theme was
 * checked; the page in it then draws its letters in the fallback font, and nothing fails.
 *
 * Registered only where the panel's frame is installed: the themes engine does not need it.
 * A warning, not a failure — the site works, it just does not look like its theme.
 */
final class FontsCheck implements Check
{
    private const string SUBJECT = 'Theme fonts';

    public function __construct(
        private readonly Application $app,
        private readonly ThemeChain $chain,
    ) {}

    public function run(): array
    {
        if ($this->chain->isEmpty()) {
            return [];
        }

        $locales = ThemeFonts::siteLocales($this->app);
        $found = [];

        foreach ($this->chain->layers as $layer) {
            foreach ($layer->fonts as $entry) {
                foreach (FontCheck::problems($layer, $entry) as $problem) {
                    $found[] = Diagnosis::warn(self::SUBJECT, "{$layer->name}: {$problem} — php artisan webx:theme:check says where.");
                }
            }
        }

        $faces = ThemeFonts::faces($this->chain);

        foreach (ThemeFonts::gaps($faces, $locales) as $gap) {
            foreach ($gap['missing'] as $script => $languages) {
                $found[] = Diagnosis::warn(self::SUBJECT, sprintf(
                    '%s (%s, of %s) has no %s file, and the site publishes in %s — those letters are drawn in the fallback font. Add the file to "fonts" of the theme.',
                    $gap['face']['family'],
                    $gap['face']['style'],
                    $gap['face']['layer']->name,
                    $script,
                    implode(', ', $languages),
                ));
            }
        }

        if ($found !== []) {
            return $found;
        }

        if ($faces === []) {
            return [Diagnosis::ok(self::SUBJECT, 'the theme has no fonts of its own; the reader\'s fonts draw '.implode(', ', $locales).'.')];
        }

        $families = array_values(array_unique(array_column($faces, 'family')));

        return [Diagnosis::ok(self::SUBJECT, implode(', ', $families).' — a file for every script of '.implode(', ', $locales).'.')];
    }
}
