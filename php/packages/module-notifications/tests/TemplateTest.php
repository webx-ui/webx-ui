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

/**
 * `{{name}}` and nothing else (§7): one pass of replacement, values escaped by the provider, the
 * template's own markup left alone, and the language of the event.
 */
final class TemplateTest extends TestCase
{
    #[Test]
    public function variables_are_replaced_once_with_spaces_and_families_and_a_value_is_never_expanded_again(): void
    {
        $rendered = Template::render(
            'A {{ name }}, {{field.phone}}, {{missing}}, {{ Name }}, {not}',
            ['name' => 'Alex {{field.phone}}', 'field.phone' => '+0 000'],
            static fn (string $value): string => $value,
        );

        $this->assertSame('A Alex {{field.phone}}, +0 000, , {{ Name }}, {not}', $rendered['text']);
        $this->assertSame(['missing'], $rendered['unknown']);
        $this->assertSame(['name', 'field.phone', 'missing'], Template::names('{{name}} {{ field.phone }} {{missing}} {{name}}'));
    }

    #[Test]
    public function a_name_the_event_declares_is_empty_without_being_unknown(): void
    {
        $event = $this->app->make(NotificationEvents::class)->find('shop.order');
        $this->assertNotNull($event);

        $rendered = Template::render('{{field.city}}{{nobody}}', [], static fn (string $value): string => $value, static fn (string $name): bool => $event->variable($name) !== null);

        $this->assertSame(['nobody'], $rendered['unknown']);
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
        $this->assertSame([['tag', 'span']], TelegramMarkup::problems('<span>x</span>'));
    }

    /**
     * @param  array<string, string>  $values
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
