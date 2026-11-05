<?php

declare(strict_types=1);

namespace WebxUi\Templates\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Templates\Template;
use WebxUi\Templates\TemplateError;

/**
 * Mustache: values escaped by the caller and never twice, lists and sections, nothing that reaches
 * past the template — partials, delimiters, unescaped output.
 */
final class TemplateTest extends TestCase
{
    #[Test]
    public function variables_and_fields_by_a_dot_go_in_and_a_value_is_never_expanded_again(): void
    {
        $rendered = Template::render(
            'A {{ name }}, {{field.phone}}, {{missing}}, {not}',
            ['name' => 'Alex {{field.phone}}', 'field.phone' => '+0 000'],
            static fn (string $value): string => $value,
        );

        $this->assertSame('A Alex {{field.phone}}, +0 000, , {not}', $rendered['text']);
        $this->assertSame(['missing'], $rendered['unknown']);
        $this->assertSame(
            [
                ['name' => 'name', 'sections' => [], 'kind' => Template::VALUE],
                ['name' => 'items', 'sections' => [], 'kind' => Template::SECTION],
                ['name' => 'qty', 'sections' => ['items'], 'kind' => Template::VALUE],
                ['name' => 'note', 'sections' => [], 'kind' => Template::SECTION],
                ['name' => 'field.phone', 'sections' => [], 'kind' => Template::VALUE],
            ],
            Template::usages('{{name}} {{#items}}{{qty}}{{/items}}{{^note}}{{ field.phone }}{{/note}}'),
        );
    }

    #[Test]
    public function a_list_goes_over_its_rows_with_the_fields_of_each(): void
    {
        $template = "Order {{number}}\n{{#items}}\n- {{name}} × {{qty}}: {{price}}\n{{/items}}\n{{^items}}\nNo items\n{{/items}}\nTotal {{total}}";
        $values = ['number' => '7', 'total' => '8.00', 'items' => [
            ['name' => 'Tea', 'qty' => '2', 'price' => '3.00'],
            ['name' => 'Cup', 'qty' => '1', 'price' => '2.00'],
        ]];
        $plain = static fn (string $value): string => $value;

        $this->assertSame("Order 7\n- Tea × 2: 3.00\n- Cup × 1: 2.00\nTotal 8.00", Template::render($template, $values, $plain)['text']);
        $this->assertSame("Order 7\nNo items\nTotal 8.00", Template::render($template, [...$values, 'items' => []], $plain)['text']);
    }

    #[Test]
    public function a_section_shows_when_a_value_is_there_and_an_inverted_one_when_it_is_not(): void
    {
        $template = '{{#note}}Note: {{note}}{{/note}}{{^note}}No note{{/note}}';
        $plain = static fn (string $value): string => $value;

        $this->assertSame('Note: At the door', Template::render($template, ['note' => 'At the door'], $plain)['text']);
        $this->assertSame('No note', Template::render($template, ['note' => ''], $plain)['text']);
        $this->assertSame('No note', Template::render($template, [], $plain)['text']);
    }

    #[Test]
    public function triple_mustaches_and_ampersands_are_escaped_like_any_value(): void
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES);

        $rendered = Template::render('{{{note}}} {{& note}} {{note}}', ['note' => '<script>x</script>'], $escape);

        $this->assertSame(str_repeat('&lt;script&gt;x&lt;/script&gt; ', 2).'&lt;script&gt;x&lt;/script&gt;', $rendered['text']);
    }

    #[Test]
    public function partials_delimiters_pragmas_and_broken_syntax_are_refused(): void
    {
        $refusals = [
            '{{>footer}}' => 'template-partial',
            '{{<layout}}{{/layout}}' => 'template-partial',
            '{{=<% %>=}}<% note %>' => 'template-delimiters',
            '{{%FILTERS}}' => 'template-pragma',
            '{{$block}}x{{/block}}' => 'template-pragma',
            '{{#items}}{{name}}' => 'template-unclosed',
            '{{#items}}{{/note}}' => 'template-unclosed',
            '{{/items}}' => 'template-unexpected',
            'Hi {{note' => 'template-tag',
            '{{ Name }}' => 'template-name',
            '{{.}}' => 'template-name',
            '{{*dynamic}}' => 'template-name',
        ];

        foreach ($refusals as $template => $key) {
            try {
                Template::usages($template);
                $this->fail("{$template} was accepted");
            } catch (TemplateError $error) {
                $this->assertSame($key, $error->key, $template);
            }

            // Never sent either, whatever got past the check.
            $this->assertThrows(static fn () => Template::render($template, [], static fn (string $value): string => $value), TemplateError::class);
        }

        $this->assertSame('The section {{#items}} is not closed: end it with {{/items}}.', (new TemplateError('template-unclosed', ['section' => 'items']))->words());
    }
}
