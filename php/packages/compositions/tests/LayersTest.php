<?php

declare(strict_types=1);

namespace WebxUi\Compositions\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Compositions\Compositions;

/**
 * Handles and layers (COMPOSITIONS §4, §5.2): `default` under every handle, a layer whole before
 * the next, the config of the site on top, `extends`, and the layout a page type stands in.
 */
final class LayersTest extends TestCase
{
    #[Test]
    public function a_handle_is_its_own_file_over_default_and_a_page_without_one_gets_default(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['body' => ['class' => 'themed']]]);
        $this->theme('layout', 'blog.article', ['attrs' => ['body' => ['class' => 'is-article']]]);

        $this->assertStringContainsString('<body class="site themed is-article">', $this->page('blog.article'));
        $this->assertStringContainsString('<p class="b-article">For articles</p>', $this->page('blog.article'));
        $this->assertStringContainsString('<body class="site themed">', $this->page());
        $this->assertStringNotContainsString('b-article', $this->page());

        // A handle no layer has a file for is the default variant.
        $this->assertStringContainsString('<body class="site themed">', $this->page('services.show'));
    }

    /**
     * A layer whole, then the next: the theme's default.json takes away what a module's
     * blog.article.json put there — under "all defaults, then all handles" the module would win.
     */
    #[Test]
    public function a_higher_layer_wins_over_the_handle_file_of_a_lower_one(): void
    {
        $this->theme('layout', 'default', ['remove' => ['b.article']]);

        $this->assertStringNotContainsString('b-article', $this->page('blog.article'));
        $this->assertSame([], $this->findings($this->tree('blog.article')));
    }

    #[Test]
    public function modules_go_by_id_and_the_site_config_goes_last(): void
    {
        // module-b is loaded first by the fixtures and still comes after module-a.
        $this->assertMatchesRegularExpression('~>a</aside>\s*<aside class="note">b</aside>~', $this->page());

        $this->theme('layout', 'default', ['attrs' => ['a.note' => ['from' => 'theme']]]);
        $this->config('layout', 'default', ['attrs' => ['a.note' => ['from' => 'config']]]);

        $this->assertStringContainsString('>config</aside>', $this->page());
    }

    /**
     * A provider passes `__DIR__.'/../resources/compositions'`: two modules that both do are two
     * layers, each under its package's name, not one `..` the second overwrites.
     */
    #[Test]
    public function a_module_loaded_from_its_src_directory_is_named_after_its_package(): void
    {
        Compositions::load(self::fixtures().'/module-c/src/../resources/compositions');
        Compositions::load(self::fixtures().'/module-a/src/../resources/compositions');

        $this->assertSame(['fixture/theme', 'module-a', 'module-b', 'module-c'], $this->tree()->layers);
        $this->assertStringContainsString('<aside class="note">c</aside>', $this->page());
    }

    #[Test]
    public function the_generated_view_says_what_it_is_built_from(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['body' => ['class' => 'x']]]);

        $this->assertSame(['fixture/theme', 'module-a', 'module-b', $this->local], $this->tree()->layers);
    }

    /** §3.7: a variant extends the final tree of its parent — the modules and the theme's patches with it. */
    #[Test]
    public function an_extending_composition_takes_the_parents_final_tree(): void
    {
        $this->theme('layout', 'default', ['attrs' => ['html' => ['data-webx-smooth-scroll' => true]]]);
        $this->theme('layout.minimal', 'default', ['attrs' => ['body' => ['class' => 'is-minimal']]]);

        $html = $this->page(null, 'layout.minimal');

        $this->assertStringContainsString('<html lang="en" data-webx-smooth-scroll>', $html);
        $this->assertStringContainsString('<body class="site is-minimal">', $html);
        $this->assertStringContainsString('>a</aside>', $html);
        $this->assertStringNotContainsString('<footer>', $html);

        // The parent's handle files reach the variant for the same handle.
        $this->assertStringContainsString('b-article', $this->page('blog.article', 'layout.minimal'));
    }

    /** §4: the site says which layout a page type stands in; the dispatcher of `layout` prints it. */
    #[Test]
    public function the_site_config_hands_a_page_type_to_another_layout(): void
    {
        config()->set('webx-compositions.layouts', ['catalog.checkout' => 'layout.minimal']);

        $this->assertStringNotContainsString('<footer>', $this->page('catalog.checkout'));
        $this->assertStringContainsString('<footer>', $this->page('catalog.product'));
    }

    /** The example of the spec (§10.1): the studio's site turns smooth scrolling on without a copy of the layout. */
    #[Test]
    public function the_smooth_scroll_of_a_site_is_a_patch_not_a_copy(): void
    {
        $this->theme('layout', 'default', (string) file_get_contents(self::fixtures().'/smooth-scroll.json'));

        $html = $this->page();

        $this->assertStringStartsWith("<!doctype html>\n<html lang=\"en\" data-webx-smooth-scroll>\n<head>", $html);
        $this->assertStringNotContainsString('<aside class="note">b</aside>', $html);
        $this->assertSame([], $this->findings($this->tree()));
    }
}
