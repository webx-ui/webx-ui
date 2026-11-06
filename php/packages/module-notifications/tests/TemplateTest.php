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
use WebxUi\Templates\Template;

/**
 * Mustache (§7): values escaped by the provider and never twice, lists and sections, nothing that
 * reaches past the template — partials, delimiters, unescaped output — and the language of the
 * event.
 */
final class TemplateTest extends TestCase
{
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
