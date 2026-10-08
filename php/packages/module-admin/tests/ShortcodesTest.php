<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\Shortcodes\Shortcode;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Shortcodes\ShortcodeText;
use WebxUi\Admin\Tests\Fixtures\Editor;

final class ShortcodesTest extends TestCase
{
    private Shortcodes $shortcodes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shortcodes = $this->app->make(Shortcodes::class);
        $this->shortcodes->register('dot', '<span class="accent-dot">.</span>', plain: '.');
        $this->shortcodes->register(
            'phone',
            static fn (array $args): string => ($args['format'] ?? null) === 'intl' ? '<a href="tel:+15550100">+15550100</a>' : '<a href="tel:+15550100">555 0100</a>',
            static fn (array $args): string => ($args['format'] ?? null) === 'intl' ? '+15550100' : '555 0100',
        );
    }

    /*
     * The editor's text is escaped and the shortcode's HTML is not: the order that keeps a
     * `<script>` typed into a heading a string, and the accent dot a span.
     */
    #[Test]
    public function html_escapes_the_text_and_keeps_the_shortcode_html_as_it_is(): void
    {
        $this->assertSame(
            'Deeply heard<span class="accent-dot">.</span> &lt;b&gt;Fish &amp; chips&lt;/b&gt;<span class="accent-dot">.</span>',
            $this->shortcodes->html('Deeply heard[dot] <b>Fish & chips</b>[dot]'),
        );
    }

    /* A shortcode does not make the text around it trusted, even when it is the whole of it. */
    #[Test]
    public function markup_typed_next_to_a_shortcode_is_still_escaped(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;<a href="tel:+15550100">555 0100</a>',
            $this->shortcodes->html('<script>alert(1)</script>[phone]'),
        );
    }

    #[Test]
    public function only_a_registered_name_is_replaced(): void
    {
        $this->assertSame('See note [1] and [sic], or [nope].', $this->shortcodes->plain('See note [1] and [sic], or [nope].'));
        $this->assertSame('[Dot] &amp; [phone-x]', $this->shortcodes->html('[Dot] & [phone-x]'));
        $this->assertSame('a [ dot ] b', $this->shortcodes->plain('a [ dot ] b'));
    }

    #[Test]
    public function a_doubled_bracket_prints_the_shortcode_literally(): void
    {
        $this->assertSame('Type [phone] to show 555 0100.', $this->shortcodes->plain('Type [[phone]] to show [phone].'));
        $this->assertSame('Type [phone format=intl]', $this->shortcodes->html('Type [[phone format=intl]]'));
        // A bracket that is not a shortcode is left doubled: nothing to escape there.
        $this->assertSame('[[nope]]', $this->shortcodes->plain('[[nope]]'));
        // One bracket more on one side only is the bracket and the shortcode.
        $this->assertSame('[555 0100', $this->shortcodes->plain('[[phone]'));
    }

    #[Test]
    public function arguments_reach_the_shortcode_however_they_are_quoted(): void
    {
        $this->assertSame('+15550100', $this->shortcodes->plain('[phone format=intl]'));
        $this->assertSame('+15550100', $this->shortcodes->plain('[phone format="intl"]'));
        $this->assertSame('+15550100', $this->shortcodes->plain("[phone  format='intl' ]"));
        $this->assertSame('<a href="tel:+15550100">+15550100</a>', $this->shortcodes->html('[phone format=intl]'));
    }

    /* A title, a description, a mail: the text, never markup — and not escaped here either. */
    #[Test]
    public function plain_text_takes_the_plain_rendering_and_escapes_nothing(): void
    {
        $this->assertSame('Deeply heard. Call 555 0100 & ask', $this->shortcodes->plain('Deeply heard[dot] Call [phone] & ask'));

        // Without a plain rendering of its own, a shortcode is its HTML without the tags.
        $this->shortcodes->register('brand', '<strong>Acme &amp; Co</strong>');
        $this->assertSame('Acme & Co', $this->shortcodes->plain('[brand]'));

        $this->assertSame('Heading. Text 555 0100', $this->shortcodes->text('<h2>Heading[dot]</h2> <p>Text [phone]</p>'));
    }

    /*
     * Rich text is trusted HTML already: the shortcodes between its tags are replaced, the tags
     * and the attributes stay as written, and so does what is inside <code>.
     */
    #[Test]
    public function inside_html_only_the_text_between_tags_is_read(): void
    {
        $this->assertSame(
            '<p title="[dot]">Heard<span class="accent-dot">.</span> <code>[dot]</code> <em>&lt;x&gt;</em></p>',
            $this->shortcodes->htmlIn('<p title="[dot]">Heard[dot] <code>[dot]</code> <em>&lt;x&gt;</em></p>'),
        );

        // An inline rich text field keeps its bare <span> accent; a shortcode beside it resolves.
        $this->assertSame(
            'Deeply heard<span>.</span> Call <a href="tel:+15550100">555 0100</a>',
            $this->shortcodes->htmlIn('Deeply heard<span>.</span> Call [phone]'),
        );

        $this->assertSame('<p>No brackets &amp; nothing to do</p>', $this->shortcodes->htmlIn('<p>No brackets &amp; nothing to do</p>'));
        // An argument quoted in the stored HTML is read as the text it shows.
        $this->assertSame('<a href="tel:+15550100">+15550100</a>', $this->shortcodes->htmlIn('[phone format=&quot;intl&quot;]'));
    }

    #[Test]
    public function a_text_with_nothing_to_replace_stays_the_same_string(): void
    {
        $this->assertSame('Plain [1] text', $this->shortcodes->resolve('Plain [1] text'));
        $this->assertNull($this->shortcodes->resolve(null));

        $resolved = $this->shortcodes->resolve('A & B[dot]');
        $this->assertInstanceOf(ShortcodeText::class, $resolved);
        $this->assertSame('A &amp; B<span class="accent-dot">.</span>', $resolved->toHtml());
        $this->assertSame('A & B.', $resolved->plain());
        $this->assertSame('A & B[dot]', $resolved->text());
        $this->assertSame('"A & B."', json_encode($resolved));
    }

    #[Test]
    public function a_resolved_text_is_printed_by_blade_without_escaping_it_twice(): void
    {
        $text = $this->shortcodes->resolve('Fish & chips[dot]');

        $this->assertSame('<h1>Fish &amp; chips<span class="accent-dot">.</span></h1>', Blade::render('<h1>{{ $text }}</h1>', ['text' => $text]));
        $this->assertSame('<img alt="Fish &amp; chips.">', Blade::render('<img alt="@shortcodesPlain($text)">', ['text' => $text]));
    }

    #[Test]
    public function the_blade_directives_resolve_a_template_s_own_fields(): void
    {
        $this->assertSame('<p>&lt;i&gt; <a href="tel:+15550100">555 0100</a></p>', Blade::render('<p>@shortcodes($text)</p>', ['text' => '<i> [phone]']));
        $this->assertSame('<p><b>x<span class="accent-dot">.</span></b></p>', Blade::render('<p>@shortcodesIn($html)</p>', ['html' => '<b>x[dot]</b>']));
        $this->assertSame('<title>Call 555 0100 &amp; more</title>', Blade::render('<title>@shortcodesPlain($text)</title>', ['text' => 'Call [phone] & more']));
    }

    #[Test]
    public function a_registered_shortcode_wins_over_one_from_a_source_and_a_failing_source_adds_nothing(): void
    {
        $this->shortcodes->source(static fn (): array => [
            new Shortcode('dot', '!', origin: 'settings'),
            new Shortcode('email', '<a href="mailto:hi@example.com">hi@example.com</a>', origin: 'settings'),
            new Shortcode('Bad Name', 'x', origin: 'settings'),
        ]);
        $this->shortcodes->source(static fn (): array => throw new RuntimeException('no table yet'));

        $this->assertSame(['dot', 'email', 'phone'], array_keys($this->shortcodes->all()));
        $this->assertSame('code', $this->shortcodes->get('dot')?->origin);
        $this->assertSame('hi@example.com.', $this->shortcodes->plain('[email][dot]'));
    }

    #[Test]
    public function a_name_is_held_to_what_fits_in_a_bracket(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->shortcodes->register('Phone Number', 'x');
    }

    #[Test]
    public function names_lists_every_bracket_that_reads_as_a_shortcode(): void
    {
        $this->assertSame([
            ['name' => 'phnoe', 'args' => [], 'known' => false, 'escaped' => false, 'raw' => 'phnoe'],
            ['name' => 'phone', 'args' => ['format' => 'intl'], 'known' => true, 'escaped' => false, 'raw' => 'phone format=intl'],
            ['name' => 'dot', 'args' => [], 'known' => true, 'escaped' => true, 'raw' => 'dot'],
        ], $this->shortcodes->names('[phnoe] [phone format=intl] [[dot]] [1]'));
    }

    #[Test]
    public function the_panel_reads_the_list_with_both_renderings(): void
    {
        $this->actingAs(new Editor([]))
            ->getJson('/api/cms/shortcodes')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'name' => 'dot',
                'description' => null,
                'html' => '<span class="accent-dot">.</span>',
                'plain' => '.',
                'origin' => 'code',
            ])
            ->assertJsonPath('data.1.name', 'phone');
    }
}
