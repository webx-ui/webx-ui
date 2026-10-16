<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Mail\Transport\ArrayTransport;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use WebxUi\Notifications\Email\EmailProvider;
use WebxUi\Notifications\Refusal;
use WebxUi\Notifications\Sending\Credentials;
use WebxUi\Notifications\Sending\DeliveryFailed;
use WebxUi\Notifications\Sending\Message;
use WebxUi\Notifications\Sending\Recipient;

/**
 * Letters (§6.2): the site's mail or the channel's SMTP, text and HTML in one letter, an
 * administrator's address from the account, and an address from the event.
 */
final class EmailTest extends TestCase
{
    #[Test]
    public function a_letter_is_text_and_escaped_html_with_links_from_the_channels_sender_with_reply_to(): void
    {
        $this->provider()->send(
            new Message('Order 12', "Note: <b>bold</b>\nhttps://example.test/cms/orders/12", '', false, 'customer@example.com'),
            new Credentials,
            ['mode' => 'site', 'from_address' => 'shop@example.com', 'from_name' => 'Shop'],
            new Recipient('manager@example.com', 'Manager'),
        );

        $email = $this->sent()[0];

        $this->assertSame('Order 12', $email->getSubject());
        $this->assertSame('manager@example.com', $email->getTo()[0]->getAddress());
        $this->assertSame('shop@example.com', $email->getFrom()[0]->getAddress());
        $this->assertSame('customer@example.com', $email->getReplyTo()[0]->getAddress());
        $this->assertSame("Note: <b>bold</b>\nhttps://example.test/cms/orders/12", $email->getTextBody());
        $this->assertStringContainsString('Note: &lt;b&gt;bold&lt;/b&gt;<br>', (string) $email->getHtmlBody());
        $this->assertStringContainsString('<a href="https://example.test/cms/orders/12">', (string) $email->getHtmlBody());
    }

    #[Test]
    public function without_a_sender_of_its_own_the_letter_is_from_the_site(): void
    {
        $this->provider()->send(new Message('S', 'B', 'B'), new Credentials, ['mode' => 'site'], new Recipient('manager@example.com'));

        $this->assertSame('site@example.com', $this->sent()[0]->getFrom()[0]->getAddress());
    }

    #[Test]
    public function an_administrator_is_written_to_at_the_address_of_the_account_and_one_switched_off_is_skipped(): void
    {
        $active = $this->admin();
        $inactive = $this->admin();
        $inactive->forceFill(['is_active' => false])->save();

        $resolved = $this->provider()->resolve([
            ['admin_id' => $active->id],
            ['admin_id' => $inactive->id],
            ['admin_id' => 999],
            ['email' => 'manager@example.com'],
            ['variable' => 'customer_email'],
            ['variable' => 'reply_to'],
        ], ['customer_email' => 'customer@example.com', 'reply_to' => 'not an address']);

        $this->assertSame([$active->email, $inactive->email, '#999', 'manager@example.com', 'customer@example.com', '{{reply_to}}'], array_map(static fn (Recipient $one): string => $one->key, $resolved));
        $this->assertSame([null, 'admin_inactive', 'admin_deleted', null, null, 'no_address'], array_map(static fn (Recipient $one): ?string => $one->skip, $resolved));
    }

    #[Test]
    public function recipients_are_checked_and_each_kept_once(): void
    {
        $this->assertSame(
            [['email' => 'manager@example.com'], ['admin_id' => 3], ['variable' => 'customer_email']],
            $this->provider()->recipients(['manager@example.com', ['email' => 'MANAGER@example.com'], ['admin_id' => '3'], ['variable' => 'customer_email']]),
        );

        $this->expectException(Refusal::class);
        $this->provider()->recipients(['not an address']);
    }

    #[Test]
    public function a_smtp_server_that_does_not_answer_is_said_without_the_password(): void
    {
        $password = 'smtp-password-not-real';
        $options = ['mode' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'encryption' => 'none', 'username' => 'robot'];

        $check = $this->provider()->check(new Credentials(['password' => $password]), $options);

        $this->assertFalse($check->ok);
        $this->assertStringNotContainsString($password, $check->message);

        try {
            $this->provider()->send(new Message('S', 'B', 'B'), new Credentials(['password' => $password]), $options, new Recipient('manager@example.com'));
            $this->fail('A letter through a dead server was taken as sent.');
        } catch (DeliveryFailed $failed) {
            $this->assertFalse($failed->permanent);
            $this->assertStringNotContainsString($password, $failed->getMessage());
        }
    }

    #[Test]
    public function the_sites_mail_that_writes_to_the_log_is_not_a_working_check(): void
    {
        config()->set('mail.default', 'log');

        $this->assertFalse($this->provider()->check(new Credentials, ['mode' => 'site'])->ok);

        config()->set('mail.default', 'smtp');

        $this->assertTrue($this->provider()->check(new Credentials, ['mode' => 'site'])->ok);
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
