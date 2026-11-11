<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Themes\ThemeAccent;

/**
 * The accented words of a heading (spec §9.4): `==words==` in the heading, a span on the page,
 * and a heading without marks exactly as `{{ }}` printed it.
 */
final class ThemeAccentTest extends TestCase
{
    #[Test]
    public function a_heading_without_marks_prints_what_the_echo_printed(): void
    {
        foreach (['Plain words', 'Tom & Jerry <b>', 'Price: 5 = 5', '', 'a == b == c', 'An ==open mark', '== spaced =='] as $heading) {
            $this->assertSame(e($heading), ThemeAccent::heading($heading)->toHtml(), $heading);
        }

        $this->assertSame('', ThemeAccent::heading(null)->toHtml());
        $this->assertSame('', ThemeAccent::heading(['not', 'text'])->toHtml());
    }

    #[Test]
    public function marked_words_become_the_span_anywhere_in_the_heading(): void
    {
        $this->assertSame('Homes <span class="site-accent">by the river</span>', ThemeAccent::heading('Homes ==by the river==')->toHtml());
        $this->assertSame('<span class="site-accent">Квартиры</span> у реки', ThemeAccent::heading('==Квартиры== у реки')->toHtml());
        $this->assertSame('<span class="site-accent">a</span> and <span class="site-accent">b</span>', ThemeAccent::heading('==a== and ==b==')->toHtml());
    }

    #[Test]
    public function the_editor_s_text_is_escaped_and_html_is_not_escaped_twice(): void
    {
        $this->assertSame('<span class="site-accent">&lt;b&gt; &amp; co</span>', ThemeAccent::heading('==<b> & co==')->toHtml());

        // A heading with a shortcode arrives resolved and escaped already.
        $resolved = new HtmlString('Call <a href="tel:+100">+100</a> ==today==');
        $this->assertSame('Call <a href="tel:+100">+100</a> <span class="site-accent">today</span>', ThemeAccent::heading($resolved)->toHtml());
    }

    #[Test]
    public function the_plain_text_loses_its_marks(): void
    {
        $this->assertSame('Homes by the river', ThemeAccent::plain('Homes ==by the river=='));
        $this->assertSame('a == b == c', ThemeAccent::plain('a == b == c'));
        $this->assertSame('', ThemeAccent::plain(null));
    }

    #[Test]
    public function the_directive_prints_the_heading(): void
    {
        $this->assertSame(
            '<h2>Homes <span class="site-accent">by the river</span></h2><h2>A &amp; B</h2><h2></h2>',
            Blade::render('<h2>@webxAccent($a)</h2><h2>@webxAccent($b)</h2><h2>@webxAccent($c)</h2>', ['a' => 'Homes ==by the river==', 'b' => 'A & B', 'c' => null], deleteCachedView: true),
        );
    }
}
