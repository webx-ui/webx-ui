<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Email\EmailMarkup;
use WebxUi\Notifications\Email\EmailProvider;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Sending\Credentials;
use WebxUi\Notifications\Sending\Recipient;
use WebxUi\Notifications\Sending\Renderer;
use WebxUi\Notifications\Templates\Html;

/**
 * A letter whose template is HTML (§6.2): a template that starts with a tag is laid out by its
 * own tags, the values escaped into it, the plain part read out of it; one that does not is the
 * text it always was.
 */
final class HtmlLetterTest extends TestCase
{
    private const LETTER = <<<'HTML'
        <table role="presentation" width="100%">
          {{#items}}
          <tr>
            <td style="color:#6b7280;">{{name}}</td>
            <td>{{qty}}</td>
          </tr>
          {{/items}}
        </table>
        <p>Note: {{note}}</p>
        <a href="https://example.test/cms/orders/{{number}}" style="display:inline-block;">Open</a>
        HTML;

    #[Test]
    public function a_template_is_html_when_it_starts_with_a_tag(): void
    {
        $this->assertTrue(EmailMarkup::isHtml("  \n<table>"));
        $this->assertTrue(EmailMarkup::isHtml('<p>Hi</p>'));
        $this->assertFalse(EmailMarkup::isHtml('Order {{number}} <b>now</b>'));
        $this->assertFalse(EmailMarkup::isHtml('{{number}}'));
        $this->assertFalse(EmailMarkup::isHtml('<= 5 items'));
    }

    #[Test]
    public function the_values_are_escaped_into_the_html_and_the_plain_part_is_read_out_of_it(): void
    {
        $channel = $this->email(['template' => ['en' => self::LETTER]]);
        $values = [
            'number' => '12',
            'note' => "Ring <twice> & wait\nthen leave it",
            'items' => [['name' => 'Tea', 'qty' => '2'], ['name' => 'Cup', 'qty' => '1']],
        ];

        $message = $this->app->make(Renderer::class)->render($channel, $this->provider(), $values, 'en', null)['message'];

        $this->assertTrue($message->html);
        $this->assertStringContainsString('<p>Note: Ring &lt;twice&gt; &amp; wait<br>then leave it</p>', $message->body);
        $this->assertSame("Tea: 2\nCup: 1\n\nNote: Ring <twice> & wait\nthen leave it\n\nOpen (https://example.test/cms/orders/12)", $message->plain);

        $this->provider()->send($message, new Credentials, ['mode' => 'site'], new Recipient('manager@example.com'));
        $letter = $this->sent()[0];

        // The template's own tags in the HTML part, inside the frame; the plain part is text.
        $this->assertStringContainsString('<td style="color:#6b7280;">Tea</td>', (string) $letter->getHtmlBody());
        $this->assertStringContainsString('<!DOCTYPE html>', (string) $letter->getHtmlBody());
        $this->assertSame($message->plain, $letter->getTextBody());
    }

    #[Test]
    public function a_text_template_is_sent_as_it_always_was(): void
    {
        $channel = $this->email(['template' => ['en' => "Order {{number}}\n<b>{{note}}</b>"]]);

        $message = $this->app->make(Renderer::class)->render($channel, $this->provider(), ['number' => '12', 'note' => 'A & B'], 'en', null)['message'];

        $this->assertFalse($message->html);
        $this->assertSame("Order 12\n<b>A & B</b>", $message->body);

        $this->provider()->send($message, new Credentials, ['mode' => 'site'], new Recipient('manager@example.com'));
        $this->assertStringContainsString('&lt;b&gt;A &amp; B&lt;/b&gt;', (string) $this->sent()[0]->getHtmlBody());
    }

    #[Test]
    public function tags_and_attributes_a_letter_does_not_take_are_refused_on_save(): void
    {
        $this->assertSame([], EmailMarkup::problems(self::LETTER));
        $this->assertSame([], EmailMarkup::problems('<p>One<br>two<br/>three</p><hr>'));
        $this->assertSame([['tag', 'script']], EmailMarkup::problems('<p>Hi</p><script>alert(1)</script>'));
        $this->assertSame([['attribute', 'onclick']], EmailMarkup::problems('<a href="{{url}}" onclick="x()">Go</a>'));
        $this->assertSame([['unclosed', 'td']], EmailMarkup::problems('<table><tr><td>{{name}}</tr></table>'));
        $this->assertSame([['unclosed', 'tr']], EmailMarkup::problems('<table>{{#items}}<tr><td>{{name}}</td>{{/items}}</tr></table>'));
        $this->assertSame([['lt', '<']], EmailMarkup::problems('<p>1 < 2</p>'));
        // A variable inside a tag is not an attribute.
        $this->assertSame([], EmailMarkup::problems('<td {{#wide}}width="100%"{{/wide}} style="{{style}}">x</td>'));

        foreach ([
            '<p>Order</p><form></form>' => 'A letter does not allow <form>. Allowed: a, b, strong,',
            '<div onclick="x()">Order</div>' => 'A letter does not allow the attribute onclick. Allowed: style, href,',
        ] as $template => $words) {
            try {
                $this->app->make(ChannelForm::class)->save(new Channel, ['name' => 'Letters', 'provider' => 'email', 'subject' => 'Order', 'template' => $template]);
                $this->fail('The template should have been refused: '.$template);
            } catch (ValidationException $refused) {
                $this->assertStringContainsString($words, implode(' ', array_merge(...array_values($refused->errors()))));
            }
        }
    }

    #[Test]
    public function the_plain_text_of_html_keeps_paragraphs_rows_lists_and_links(): void
    {
        $this->assertSame(
            "Title\n\n- one\n- two\n\nSee site (https://example.test)\n\nhttps://example.test/x",
            Html::plain('<h1>Title</h1>  <ul><li>one</li><li>two</li></ul><p>See <a href="https://example.test">site</a></p><div><a href="https://example.test/x">https://example.test/x</a></div>'),
        );
        $this->assertSame('a &lt; b<br>c', Html::escape("a < b\nc"));
    }

    private function provider(): EmailProvider
    {
        return $this->app->make(EmailProvider::class);
    }

    /**
     * @return list<Email>
     */
    private function sent(): array
    {
        $transport = $this->app->make('mail.manager')->mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return array_values(array_map(static function (SentMessage $sent): Email {
            $original = $sent->getOriginalMessage();
            self::assertInstanceOf(Email::class, $original);

            return $original;
        }, $transport->messages()->all()));
    }
}
