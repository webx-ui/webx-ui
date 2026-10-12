<?php

declare(strict_types=1);

namespace WebxUi\Themes\Testing;

use PHPUnit\Framework\Attributes\Test;

/**
 * The contract of a theme (spec §18), for its PHPUnit test — packaged or local:
 *
 *     final class ThemeTest extends TestCase
 *     {
 *         use ThemeContract;
 *
 *         protected function themePath(): string { return dirname(__DIR__); }
 *     }
 *
 * The manifest reads and the chain builds with this theme on top; every token of the vocabulary
 * has a value; `webx:theme:check` finds nothing in the chain — tokens, the preset, stylesheets,
 * block types and their schemas, icons, assets, words in every declared language, fonts, letters;
 * text reads in every preset; the layout keeps what every page needs (rule 21); the theme's views
 * compile; and a packaged theme's committed `dist/` is what its `src/` builds into.
 *
 * Green on a theme `webx:theme:make` just wrote: an agent starts from a working theme, and the
 * first red line is the first rule it broke. The test case boots the engine — a site's
 * `Tests\TestCase` does, and a package's Testbench case lists `ThemeServiceProvider`.
 */
trait ThemeContract
{
    private ?ThemeProbe $themeProbe = null;

    /** The theme's directory: the one with `theme.json`, or the package with its `composer.json`. */
    abstract protected function themePath(): string;

    protected function themeProbe(): ThemeProbe
    {
        return $this->themeProbe ??= new ThemeProbe($this->themePath(), app());
    }

    #[Test]
    public function the_theme_reads_and_stands_on_top_of_its_chain(): void
    {
        $top = $this->themeProbe()->theme();

        $this->assertSame(realpath($this->themePath()), realpath($top->path), "The chain's top is {$top->name}, not the theme under test.");
    }

    #[Test]
    public function every_token_of_the_vocabulary_has_a_value_in_the_chain(): void
    {
        $this->assertSame([], $this->themeProbe()->unvalued(), 'No layer gives these tokens a value; a bottom theme owes every one.');
    }

    #[Test]
    public function the_check_of_the_engine_finds_nothing_in_the_chain(): void
    {
        $this->assertSame([], $this->themeProbe()->findings(), 'php artisan webx:theme:check says the same, with the rule each line breaks.');
    }

    #[Test]
    public function text_reads_in_every_preset_of_the_chain(): void
    {
        $this->assertSame([], $this->themeProbe()->unreadable(), 'WCAG AA, rule 29: change the colour in tokens.json, not the rule.');
    }

    #[Test]
    public function the_layout_of_the_chain_prints_what_every_page_needs(): void
    {
        $this->assertSame([], $this->themeProbe()->layoutProblems());
    }

    #[Test]
    public function the_views_of_the_theme_compile(): void
    {
        $this->assertSame([], $this->themeProbe()->uncompiled());
    }

    #[Test]
    public function the_committed_dist_is_built_from_the_current_sources(): void
    {
        $this->assertNull($this->themeProbe()->staleDist());
    }
}
