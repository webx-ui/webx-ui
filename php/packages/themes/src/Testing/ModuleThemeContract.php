<?php

declare(strict_types=1);

namespace WebxUi\Themes\Testing;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\Checks\CssCheck;
use WebxUi\Themes\Checks\CssFinding;
use WebxUi\Themes\Vocabulary;

/**
 * The theme contract of a module with public pages (spec §10.1, §10.2, §18), for its PHPUnit test:
 *
 *     final class ThemeContractTest extends TestCase
 *     {
 *         use ModuleThemeContract;
 *
 *         protected function modulePath(): string { return dirname(__DIR__); }
 *     }
 *
 * The base look (`resources/theme/*.css`) knows only the engine's vocabulary — a module cannot
 * know the theme above it, so a token a theme added is not one it may use — and passes §8 like a
 * theme. Its classes are a public contract: every class the stylesheet names is listed under
 * `### Classes` in `AGENTS.md`, and every listed class is printed by a view — a class in the
 * stylesheet that no view prints is a rule for markup that is gone, and one in `AGENTS.md` that
 * no view prints sends a theme to restyle nothing.
 *
 * A class a view builds (`'wx-form__field--'.$type`) is listed as `wx-form__field--<type>`: the
 * part before `<` has to be in a view, and any class it starts covers the stylesheet.
 */
trait ModuleThemeContract
{
    /** The module's directory: `resources/theme`, `resources/views` and `AGENTS.md` are under it. */
    abstract protected function modulePath(): string;

    #[Test]
    public function the_base_look_knows_only_the_engines_tokens(): void
    {
        $stylesheets = $this->moduleStylesheets();
        $this->assertNotSame([], $stylesheets, 'The module has no resources/theme/*.css.');

        foreach ($stylesheets as $name => $css) {
            $findings = array_map(
                static fn (CssFinding $finding): string => "{$name}:{$finding->line} {$finding->message()}",
                CssCheck::check($css, Vocabulary::base()),
            );
            $this->assertSame([], $findings);

            $bare = (string) preg_replace('~/\*.*?\*/~s', '', $css);

            $this->assertStringNotContainsString('!important', $bare, "{$name}: a theme above has to win by coming later, not by force (rule 14).");
            $this->assertDoesNotMatchRegularExpression('/@media\b/', $bare, "{$name}: the width of the container decides, not the window's (rule 12).");

            preg_match_all('/(--[a-z0-9-]+)\s*:/i', $bare, $declared);
            preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*([,)])/i', $bare, $references, PREG_SET_ORDER);

            foreach ($references as [, $variable, $after]) {
                if (str_starts_with($variable, '--webx-')) {
                    $this->assertContains($variable, $declared[1], "{$name}: {$variable} is declared nowhere in the stylesheet.");
                } else {
                    $this->assertStringStartsWith('--site-', $variable, "{$name}: {$variable} is neither a site token nor the module's own --webx-* (§7.2).");
                    $this->assertSame(')', $after, "{$name}: var({$variable}, …) has a fallback.");
                }
            }
        }
    }

    #[Test]
    public function the_base_look_styles_only_the_classes_in_agents_md(): void
    {
        $documented = $this->documentedClasses();

        foreach ($this->moduleStylesheets() as $name => $css) {
            foreach ($this->selectors($css) as $selector) {
                preg_match_all('/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', $selector, $classes);
                $this->assertNotSame([], $classes[1], "{$name}: \"{$selector}\" names no class — style the module by its classes, not by tags (rule 11).");

                foreach ($classes[1] as $class) {
                    $this->assertTrue(
                        self::covers($documented, $class),
                        "{$name}: .{$class} is not listed under ### Classes in AGENTS.md.",
                    );
                }
            }
        }
    }

    #[Test]
    public function the_classes_in_agents_md_are_printed_by_the_views(): void
    {
        $documented = $this->documentedClasses();
        $this->assertNotSame([], $documented, 'AGENTS.md lists no classes under ### Classes.');

        $views = '';

        foreach ($this->files($this->modulePath().'/resources/views', 'php') as $file) {
            $views .= file_get_contents($file)."\n";
        }

        foreach ($documented as $class) {
            $literal = strstr($class, '<', true);
            $literal = $literal === false ? $class : $literal;

            $this->assertMatchesRegularExpression(
                '/(?<![a-z0-9_-])'.preg_quote($literal, '/').($literal === $class ? '(?![a-z0-9_-])' : '').'/i',
                $views,
                "AGENTS.md lists {$class}, and no view prints it.",
            );
        }
    }

    /**
     * Every extension point a view offers (`@webxPart('blog.card', …)`) is in `AGENTS.md` by the
     * file a theme puts there, `parts/blog/card.blade.php` (THEMES §10.4, §16): a point nobody
     * can read about is one no theme fills.
     */
    #[Test]
    public function every_extension_point_of_the_views_is_in_agents_md(): void
    {
        $agents = (string) @file_get_contents($this->modulePath().'/AGENTS.md');

        foreach ($this->files($this->modulePath().'/resources/views', 'php') as $file) {
            preg_match_all("/@webxPart\(\s*'([a-z0-9._-]+)'/", (string) file_get_contents($file), $points);

            foreach ($points[1] as $point) {
                $part = 'parts/'.str_replace('.', '/', $point).'.blade.php';

                $this->assertStringContainsString($part, $agents, basename($file).": the point {$point} is not in AGENTS.md as {$part}.");
            }
        }

        // A module without points has nothing to name — that is a pass, not a risky test.
        $this->addToAssertionCount(1);
    }

    /**
     * Every stylesheet of the base look, by file name.
     *
     * @return array<string, string>
     */
    protected function moduleStylesheets(): array
    {
        $stylesheets = [];

        foreach ($this->files($this->modulePath().'/resources/theme', 'css') as $file) {
            $stylesheets[basename($file)] = (string) file_get_contents($file);
        }

        ksort($stylesheets);

        return $stylesheets;
    }

    /**
     * The classes in backticks under `### Classes` of `AGENTS.md`.
     *
     * @return list<string>
     */
    protected function documentedClasses(): array
    {
        $agents = (string) @file_get_contents($this->modulePath().'/AGENTS.md');

        if (preg_match('/^### Classes\s*$(.*?)(?=^##|\z)/ms', $agents, $section) !== 1) {
            return [];
        }

        preg_match_all('/`\.?((?:webx|wx|is)-[a-z0-9_-]*(?:<[a-z-]+>)?)`/', $section[1], $classes);

        return array_values(array_unique($classes[1]));
    }

    /**
     * The selectors of every rule, one per comma, outside keyframes.
     *
     * @return list<string>
     */
    private function selectors(string $css): array
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        $css = (string) preg_replace('/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', $css);

        preg_match_all('/(?:^|[;{}])\s*([^;{}@]+?)\s*\{/', $css, $rules);

        $selectors = [];

        foreach ($rules[1] as $rule) {
            foreach (explode(',', $rule) as $selector) {
                if (trim($selector) !== '') {
                    $selectors[] = trim($selector);
                }
            }
        }

        return $selectors;
    }

    /** @param list<string> $documented */
    private static function covers(array $documented, string $class): bool
    {
        foreach ($documented as $listed) {
            $prefix = strstr($listed, '<', true);

            if ($listed === $class || ($prefix !== false && str_starts_with($class, $prefix) && strlen($class) > strlen($prefix))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function files(string $directory, string $extension): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $found = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && strtolower($file->getExtension()) === $extension) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }
}
