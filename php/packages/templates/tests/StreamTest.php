<?php

declare(strict_types=1);

namespace WebxUi\Templates\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Templates\Cdata;
use WebxUi\Templates\Escapes;
use WebxUi\Templates\Template;
use WebxUi\Templates\TemplateError;
use WebxUi\Templates\Variable;
use WebxUi\Templates\Variables;

/**
 * What a feed asks of the engine and a message never did: a template read once and rendered for
 * every product, values that go in whole as CDATA, the position of an element in a list.
 */
final class StreamTest extends TestCase
{
    #[Test]
    public function a_compiled_template_renders_again_and_again_with_other_values(): void
    {
        $compiled = Template::compile('<i>{{name}}</i>{{#tags}}{{^first}},{{/first}}{{t}}{{/tags}}', Escapes::for('xml'));

        $this->assertSame('<i>a &amp; b</i>x,y', $compiled->render(['name' => 'a & b', 'tags' => [['t' => 'x'], ['t' => 'y']]]));
        $this->assertSame('<i></i>', $compiled->render([]));
        $this->assertSame('<i>c</i>z', $compiled->render(['name' => 'c', 'tags' => [['t' => 'z']]]));
    }

    #[Test]
    public function two_compiled_templates_keep_their_own_escaping(): void
    {
        $xml = Template::compile('{{v}}', Escapes::for('xml'));
        $text = Template::compile('{{v}}', Escapes::for('text'));

        $this->assertSame('&lt;', $xml->render(['v' => '<']));
        $this->assertSame('<', $text->render(['v' => '<']));
        $this->assertSame('&lt;', $xml->render(['v' => '<']));
    }

    #[Test]
    public function a_template_is_checked_when_it_is_compiled(): void
    {
        $this->expectException(TemplateError::class);

        Template::compile('{{>footer}}', Escapes::for('text'));
    }

    #[Test]
    public function an_empty_cdata_is_null_and_it_reaches_the_browser_as_a_map(): void
    {
        $this->assertNull(Cdata::of(''));
        $this->assertNull(Cdata::of(null));
        $this->assertSame('{"$cdata":"<b>"}', json_encode(Cdata::of('<b>')));
    }

    #[Test]
    public function a_closure_that_knows_nothing_of_cdata_gets_its_text(): void
    {
        $rendered = Template::render('{{html}}', ['html' => Cdata::of('<b>')], static fn (string $value): string => strtoupper($value));

        $this->assertSame('<B>', $rendered['text']);
    }

    #[Test]
    public function a_name_inside_a_cdata_section_reaches_nothing(): void
    {
        // Mustache calls a method of that name on the object it stepped into.
        $rendered = Template::render('{{#html}}[{{of}}{{__construct}}{{jsonserialize}}{{text}}]{{/html}}', ['html' => Cdata::of('<b>')], Escapes::for('xml'));

        $this->assertSame('[]', $rendered['text']);
    }

    #[Test]
    public function invalid_utf8_does_not_lose_the_value(): void
    {
        $this->assertSame('a?b', Escapes::for('xml')("a\xFFb"));
        $this->assertSame("a\u{FFFD}b", Escapes::for('json')("a\xFFb"));
    }

    #[Test]
    public function a_format_nobody_knows_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Escapes::for('yaml');
    }

    #[Test]
    public function a_literal_cdata_is_checked_only_when_asked_for(): void
    {
        $this->assertSame('<![CDATA[x]]>', Template::render('<![CDATA[{{v}}]]>', ['v' => 'x'], Escapes::for('text'))['text']);

        try {
            Template::refuseInCdata('<![CDATA[{{v}}]]>');
            $this->fail('accepted');
        } catch (TemplateError $error) {
            $this->assertStringContainsString('{{v}}', $error->words());
            $this->assertStringContainsString('_cdata', $error->words());
        }
    }

    #[Test]
    public function first_and_last_are_known_inside_a_list_and_nowhere_else(): void
    {
        $variables = new Variables([
            new Variable('name', 'Name', example: 'Tea'),
            new Variable('images', 'Images', example: [['url' => 'a']], fields: ['url' => 'Address']),
            new Variable('field.*', 'Field'),
        ]);

        $this->assertTrue($variables->knows('first', ['images']));
        $this->assertTrue($variables->knows('last', ['images']));
        $this->assertTrue($variables->knows('url', ['images']));
        $this->assertTrue($variables->knows('name', ['images']));
        $this->assertTrue($variables->knows('field.phone'));
        $this->assertFalse($variables->knows('first'));
        $this->assertFalse($variables->knows('first', ['name']));
        $this->assertFalse($variables->knows('url'));
        $this->assertSame(['name' => 'Tea', 'images' => [['url' => 'a']]], $variables->examples());
    }
}
