<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Notifications\Contracts\Provider;
use WebxUi\Notifications\Email\EmailProvider;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Sending\Message;
use WebxUi\Notifications\Sending\Renderer;
use WebxUi\Notifications\Telegram\TelegramMarkup;
use WebxUi\Notifications\Telegram\TelegramProvider;
use WebxUi\Notifications\Templates\Template;
use WebxUi\Notifications\Templates\TemplateError;

/**
 * Mustache (§7): values escaped by the provider and never twice, lists and sections, nothing that
 * reaches past the template — partials, delimiters, unescaped output — and the language of the
 * event.
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
    public function the_providers_escaping_works_inside_a_section_and_replaces_mustaches_own(): void
    {
        $channel = new Channel(['provider' => 'telegram', 'template' => ['en' => '{{#items}}<b>{{name}}</b> {{/items}}& {{note}}']]);
        $values = ['note' => 'Tom & "Jerry"', 'items' => [['name' => '<i>Tea</i> & co']]];

        $this->assertSame('<b>&lt;i&gt;Tea&lt;/i&gt; &amp; co</b> & Tom &amp; &quot;Jerry&quot;', $this->render($channel, $values)['message']->body);

        $email = new Channel(['provider' => 'email', 'subject' => ['en' => 'Order {{number}}'], 'template' => ['en' => "{{#items}}{{name}} & {{price}}\n{{/items}}"]]);
        $message = $this->render($email, ['number' => '1', 'items' => [['name' => '<b>Tea</b>', 'price' => '"3"']]], EmailProvider::class)['message'];

        // The letter's HTML is built from the text later; escaping it here would show "&amp;".
        $this->assertSame("<b>Tea</b> & \"3\"\n", $message->body);
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

    #[Test]
    public function a_name_the_event_declares_is_empty_without_being_unknown_and_a_field_counts_inside_its_list(): void
    {
        $event = $this->app->make(NotificationEvents::class)->find('shop.order');
        $this->assertNotNull($event);
        $known = static fn (string $name, array $sections): bool => $event->knows($name, $sections);

        $rendered = Template::render('{{field.city}}{{nobody}}{{#items}}{{price}}{{colour}}{{/items}}{{price}}', ['items' => []], static fn (string $value): string => $value, $known);

        $this->assertSame(['nobody', 'colour', 'price'], $rendered['unknown']);
        $this->assertTrue($event->knows('price', ['items']));
        $this->assertTrue($event->knows('number', ['items']));
        $this->assertFalse($event->knows('price'));
    }

    #[Test]
    public function telegram_escapes_the_values_and_keeps_the_markup_of_the_template(): void
    {
        $channel = new Channel(['provider' => 'telegram', 'template' => ['en' => '<b>{{note}}</b> & co']]);

        $message = $this->render($channel, ['note' => '<script>"x" & \'y\'</script>'])['message'];

        $this->assertSame('<b>&lt;script&gt;&quot;x&quot; &amp; &#039;y&#039;&lt;/script&gt;</b> & co', $message->body);
        $this->assertSame('<script>"x" & \'y\'</script> & co', $message->plain);
        $this->assertFalse($message->truncated);
    }

    #[Test]
    public function a_message_longer_than_telegram_takes_goes_as_plain_text_cut_to_the_limit(): void
    {
        $channel = new Channel(['provider' => 'telegram', 'template' => ['en' => '<b>{{note}}</b>']]);

        $message = $this->render($channel, ['note' => str_repeat('<', 5000)])['message'];

        $this->assertTrue($message->truncated);
        $this->assertSame(4096, mb_strlen($message->plain));
        $this->assertStringEndsWith('…', $message->plain);
    }

    #[Test]
    public function an_email_subject_is_one_line_and_the_body_is_the_text_as_written(): void
    {
        $channel = new Channel(['provider' => 'email', 'subject' => ['en' => 'Order {{number}}'], 'template' => ['en' => "Note:\n{{note}}"]]);

        $message = $this->render($channel, ['number' => "12\r\nBcc: someone@example.com", 'note' => '<b>bold</b>', 'reply_to' => 'customer@example.com'], EmailProvider::class)['message'];

        $this->assertSame('Order 12 Bcc: someone@example.com', $message->subject);
        $this->assertSame("Note:\n<b>bold</b>", $message->body);
        $this->assertSame('customer@example.com', $message->replyTo);
    }

    #[Test]
    public function the_template_is_taken_in_the_events_language_then_the_sites_then_any(): void
    {
        $channel = new Channel(['provider' => 'telegram', 'template' => ['en' => 'Hello {{number}}', 'ru' => 'Привет {{number}}']]);
        $russianOnly = new Channel(['provider' => 'telegram', 'template' => ['ru' => 'Привет {{number}}']]);

        $this->assertSame('Привет 7', $this->render($channel, ['number' => '7'], locale: 'ru')['message']->body);
        $this->assertSame('Hello 7', $this->render($channel, ['number' => '7'], locale: 'de')['message']->body);
        $this->assertSame('Hello 7', $this->render($channel, ['number' => '7'], locale: null)['message']->body);
        $this->assertSame('Привет 7', $this->render($russianOnly, ['number' => '7'], locale: 'en')['message']->body);
    }

    #[Test]
    public function telegram_markup_is_checked_before_it_is_saved(): void
    {
        $this->assertSame([], TelegramMarkup::problems('<b>Bold</b> <a href="{{url}}">link</a> <span class="tg-spoiler">x</span> {{a}} &lt;'));
        $this->assertSame([['tag', 'div']], TelegramMarkup::problems('<div>x</div>'));
        $this->assertSame([['unclosed', 'b']], TelegramMarkup::problems('<b>open'));
        $this->assertSame([['unclosed', 'b'], ['unclosed', 'i']], TelegramMarkup::problems('<b><i>x</b>'));
        $this->assertSame([['lt', '<']], TelegramMarkup::problems('1 < 2'));
        $this->assertSame([], TelegramMarkup::problems('{{#items}}<b>{{name}}</b>{{/items}}{{^items}}<i>none</i>{{/items}}'));
        $this->assertSame([['unclosed', 'b']], TelegramMarkup::problems('{{#items}}<b>{{name}}{{/items}}</b>'));
        $this->assertSame([['unclosed', 'b']], TelegramMarkup::problems('<b>{{#items}}</b>{{/items}}'));
        $this->assertSame([['tag', 'span']], TelegramMarkup::problems('<span>x</span>'));
    }

    /**
     * @param  array<string, string|list<array<string, string>>>  $values
     * @param  class-string  $provider
     * @return array{message: Message, unknown: list<string>}
     */
    private function render(Channel $channel, array $values, string $provider = TelegramProvider::class, ?string $locale = 'en'): array
    {
        $instance = $this->app->make($provider);
        $this->assertInstanceOf(Provider::class, $instance);

        return $this->app->make(Renderer::class)->render($channel, $instance, $values, $locale, null);
    }
}
