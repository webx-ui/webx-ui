<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Compositions;

/**
 * The compositions in `webx:theme:check` (COMPOSITIONS §8): a module fixture holds the base of
 * `layout`, a local theme patches it — wrongly, on purpose.
 */
final class CompositionCheckTest extends TestCase
{
    #[Test]
    public function the_findings_of_a_themes_compositions_fail_the_check(): void
    {
        $this->module();

        $this->artisan('webx:theme:check', ['path' => self::fixture('composed-theme')])
            ->expectsOutputToContain('compositions/layout/default.json  remove sidebar — no node with this id')
            ->expectsOutputToContain('compositions/layout/default.json  attrs html.lang — lang is the language of the page')
            ->expectsOutputToContain('compositions/layout/blog.artcle.json  unknown handle "blog.artcle"')
            ->expectsOutputToContain('compositions/ghost/default.json  no layer has a base for the composition "ghost"')
            ->expectsOutputToContain('add fixture.banner — "fixture." is the prefix of module-fixture')
            ->expectsOutputToContain('meta.theme-color: no token "color-nope" with a value in the chain')
            ->expectsOutputToContain('views/components/layout.blade.php:5  place: no container "sidebar" in layout × default')
            ->expectsOutputToContain('views/components/layout.blade.php  note: overrides the composition layout')
            ->expectsOutputToContain('views/vendor/webx-blog/pages/article.blade.php  note: overrides the composition blog.article')
            ->expectsOutputToContain('7 findings')
            ->assertFailed();
    }

    /** §2.2: the base names the view that includes it, and the check looks for a copy of that one — not a guess from the name. */
    #[Test]
    public function the_copy_of_a_composition_is_looked_for_by_its_dispatcher(): void
    {
        $this->module();

        $this->artisan('webx:theme:check', ['path' => self::fixture('composed-theme')])
            ->expectsOutputToContain('views/vendor/webx-blog/pages/article.blade.php  note: overrides the composition blog.article')
            ->doesntExpectOutputToContain('views/vendor/webx-blog/article.blade.php')
            ->assertFailed();
    }

    /** §4: a page composition is included with `default`, a file for its own name never applies. */
    #[Test]
    public function a_page_compositions_file_for_its_own_name_is_a_note(): void
    {
        $this->module();

        $this->artisan('webx:theme:check', ['path' => self::fixture('composed-theme')])
            ->expectsOutputToContain('compositions/blog.article/blog.article.json  note: the composition blog.article is the page of blog.article already: its handle is default')
            ->doesntExpectOutputToContain('compositions/layout/blog.article.json  note')
            ->assertFailed();
    }

    /** §2.2: a view a theme adds reads only what the base gives its leaves — `vars` — and its own `with`. */
    #[Test]
    public function a_view_of_a_theme_reading_a_variable_the_leaves_do_not_get_is_a_note(): void
    {
        $this->module();

        $this->artisan('webx:theme:check', ['path' => self::fixture('composed-theme')])
            ->expectsOutputToContain('compositions/blog.article/default.json  note: byline: the view components.byline reads $author — the leaves of blog.article get $article')
            ->doesntExpectOutputToContain('stamp:')
            ->assertFailed();
    }

    #[Test]
    public function a_file_that_is_not_json_is_a_finding_of_its_own(): void
    {
        $this->module();
        $theme = sys_get_temp_dir().'/webx-composition-check-'.uniqid();
        (new Filesystem)->copyDirectory(self::fixture('composed-theme'), $theme);
        file_put_contents($theme.'/compositions/layout/default.json', '{ "remove": ["footer"], ');

        try {
            $this->artisan('webx:theme:check', ['path' => $theme])
                ->expectsOutputToContain('compositions/layout/default.json  invalid JSON')
                ->doesntExpectOutputToContain('unknown handle')
                ->assertFailed();
        } finally {
            (new Filesystem)->deleteDirectory($theme);
        }
    }

    #[Test]
    public function a_chain_without_compositions_says_nothing_about_them(): void
    {
        $this->artisan('webx:theme:check')
            ->doesntExpectOutputToContain('composition')
            ->assertSuccessful();
    }

    /** The base of `layout` from a module, and the one page type it names. */
    private function module(): void
    {
        Compositions::load(self::fixture('module-fixture/resources/compositions'));
        Compositions::handles('blog', ['blog.article']);
    }
}
