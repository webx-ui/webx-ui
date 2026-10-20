<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use WebxUi\Inbox\Notifications\SubmissionEvent;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\NotificationsServiceProvider;
use WebxUi\Notifications\Providers;
use WebxUi\Notifications\Sending\Message;
use WebxUi\Notifications\Sending\Renderer;

/**
 * The default templates of `inbox.submission` (NOTIFICATIONS §15.1): a letter and a Telegram
 * message in every language the package speaks, that a channel saves, previews and sends as they
 * are — a person far from code never writes one from nothing.
 *
 * What they render to on the examples is kept in `Fixtures/notifications`: a change of the letter
 * shows in the diff. `UPDATE_SNAPSHOTS=1` writes them anew.
 */
final class NotificationTemplatesTest extends TestCase
{
    /** Made up, the shape Telegram gives — never a real bot's. */
    private const TOKEN = '000000001:TEST-token-not-real-0000000000';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), NotificationsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Every language of the package, and one it has no words in.
        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ...array_map(static fn (string $code): array => ['code' => $code], [...array_values(array_diff(self::languages(), ['en'])), 'ja']),
        ]);
        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from', ['address' => 'site@site.test', 'name' => 'Site']);
        $app['config']->set('webx-notifications.telegram.api', 'https://telegram.test');
    }

    #[Test]
    public function the_event_has_a_letter_and_a_telegram_message_in_every_language_of_the_package(): void
    {
        $event = $this->event();

        foreach (self::languages() as $locale) {
            $email = $event->sampleFor('email', $locale);
            $telegram = $event->sampleFor('telegram', $locale);

            $this->assertNotNull($email, "No letter in [{$locale}].");
            $this->assertNotNull($telegram, "No Telegram message in [{$locale}].");
            $this->assertStringContainsString('{{form}}', (string) $email['subject']);
            $this->assertStringStartsWith('<table', ltrim($email['template']));
            $this->assertStringContainsString('{{#answers}}', $email['template']);
            $this->assertStringContainsString('href="{{submission_url}}"', $email['template']);
            $this->assertStringContainsString('{{#reply_to}}', $email['template']);
            $this->assertNull($telegram['subject']);
            $this->assertStringContainsString('href="{{submission_url}}"', $telegram['template']);
            $this->assertSame(trans('webx-inbox::mail.open', [], $locale), html_entity_decode((string) preg_replace('~.*<a href="\{\{submission_url\}\}">(.*?)</a>.*~s', '$1', $telegram['template']), ENT_QUOTES | ENT_HTML5));
        }

        $this->assertNull($event->sampleFor('email', 'ja'));
        // The words are the language's, not the English ones.
        $this->assertStringContainsString('Открыть в панели', $event->sampleFor('email', 'ru')['template'] ?? '');
    }

    #[Test]
    public function a_new_channel_starts_with_the_template_in_every_language_and_it_passes_the_check(): void
    {
        foreach (['email', 'telegram'] as $provider) {
            $saved = $this->app->make(ChannelForm::class)->save(new Channel, [
                'name' => 'Submissions',
                'provider' => $provider,
                'event' => SubmissionEvent::KEY,
            ]);
            $channel = $saved['channel'];

            // The check of the variables and of the markup ran, and found nothing.
            $this->assertSame([], $saved['warnings']);
            $template = $channel->getTranslations('template');
            $this->assertEqualsCanonicalizing(self::languages(), array_keys($template), $provider);
            $this->assertArrayNotHasKey('ja', $template);

            if ($provider === 'email') {
                $this->assertSame('New submission: {{form}}', $channel->getTranslation('subject', 'en'));
                $this->assertSame('Nowe zgłoszenie: {{form}}', $channel->getTranslation('subject', 'pl'));
            }
        }
    }

    #[Test]
    public function the_letter_renders_on_the_examples_as_kept(): void
    {
        $rendered = $this->render('email');
        $message = $rendered['message'];

        $this->assertSame([], $rendered['unknown']);
        $this->assertTrue($message->html);
        $this->assertSame('New submission: Contact us', $message->subject);
        $this->assertSame('visitor@site.test', $message->replyTo);
        $this->snapshot('email.html', $message->body);
        $this->snapshot('email.txt', $message->plain);

        // The answers, the button, the page and the note about who a reply reaches.
        $this->assertStringContainsString('Call me after six, please.', $message->body);
        $this->assertStringContainsString('<a href="https://site.test/cms/inbox/submissions/128"', $message->body);
        $this->assertStringContainsString("Name: Alex Doe\nPhone: +0 000 000-00-00", $message->plain);
        $this->assertStringContainsString('Open in the panel (https://site.test/cms/inbox/submissions/128)', $message->plain);
        $this->assertStringContainsString('Reply to this letter to answer the visitor: visitor@site.test.', $message->plain);
    }

    #[Test]
    public function the_telegram_message_renders_on_the_examples_as_kept(): void
    {
        $rendered = $this->render('telegram');
        $message = $rendered['message'];

        $this->assertSame([], $rendered['unknown']);
        $this->assertFalse($message->truncated);
        $this->snapshot('telegram.html', $message->body);
        $this->assertStringStartsWith("<b>Contact us</b>\n<b>Name:</b> Alex Doe\n", $message->body);
    }

    #[Test]
    public function a_visitors_markup_is_escaped_and_their_line_breaks_kept(): void
    {
        $values = ['answers' => [['name' => 'message', 'label' => 'Message', 'value' => "<b>Hi</b> & bye\nsecond line"]]] + $this->event()->examples();

        $letter = $this->render('email', $values)['message'];
        $this->assertStringContainsString('&lt;b&gt;Hi&lt;/b&gt; &amp; bye<br>second line', $letter->body);
        $this->assertStringContainsString("Message: <b>Hi</b> & bye\nsecond line", $letter->plain);

        $telegram = $this->render('telegram', $values)['message'];
        $this->assertStringContainsString("<b>Message:</b> &lt;b&gt;Hi&lt;/b&gt; &amp; bye\nsecond line", $telegram->body);
    }

    #[Test]
    public function a_test_of_a_channel_with_the_default_template_goes_out_unchanged(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $form = $this->app->make(ChannelForm::class);

        $email = $form->save(new Channel, [
            'name' => 'Letters',
            'provider' => 'email',
            'event' => SubmissionEvent::KEY,
            'recipients' => [['email' => 'managers@site.test']],
        ])['channel'];
        $telegram = $form->save(new Channel, [
            'name' => 'Chat',
            'provider' => 'telegram',
            'event' => SubmissionEvent::KEY,
            'credentials' => ['token' => self::TOKEN],
            'recipients' => [['chat_id' => '100200300']],
        ])['channel'];

        $this->assertSame(['sent'], array_column($this->app->make(Notifications::class)->test($email), 'status'));
        $this->assertSame(['sent'], array_column($this->app->make(Notifications::class)->test($telegram), 'status'));

        $letter = $this->sent()[0];
        $this->assertSame('New submission: Contact us', $letter->getSubject());
        $this->assertStringContainsString('<a href="https://site.test/cms/inbox/submissions/128"', (string) $letter->getHtmlBody());
        $this->assertStringContainsString('Name: Alex Doe', (string) $letter->getTextBody());
        $this->assertStringNotContainsString('<td', (string) $letter->getTextBody());

        Http::assertSent(static fn (HttpRequest $request): bool => str_ends_with($request->url(), '/sendMessage')
            && $request['parse_mode'] === 'HTML'
            && str_starts_with((string) $request['text'], '<b>Contact us</b>'));
    }

    /**
     * @return list<string>
     */
    private static function languages(): array
    {
        $languages = array_map('basename', glob(dirname(__DIR__).'/lang/*', GLOB_ONLYDIR) ?: []);
        sort($languages);

        return $languages;
    }

    private function event(): EventDefinition
    {
        $event = $this->app->make(NotificationEvents::class)->find(SubmissionEvent::KEY);
        $this->assertNotNull($event);

        return $event;
    }

    /**
     * @param  array<string, string|list<array<string, string>>>|null  $values
     * @return array{message: Message, unknown: list<string>}
     */
    private function render(string $provider, ?array $values = null): array
    {
        $event = $this->event();
        $channel = $this->app->make(ChannelForm::class)->unsaved(['provider' => $provider, 'event' => $event->key]);
        $found = $this->app->make(Providers::class)->find($provider);
        $this->assertNotNull($found);

        return $this->app->make(Renderer::class)->render($channel, $found, $values ?? $event->examples(), 'en', $event);
    }

    private function snapshot(string $name, string $actual): void
    {
        $file = __DIR__.'/Fixtures/notifications/'.$name;

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            @mkdir(dirname($file), 0777, true);
            file_put_contents($file, $actual);
        }

        $this->assertSame((string) file_get_contents($file), $actual, "{$name} differs from what is kept; UPDATE_SNAPSHOTS=1 writes it anew.");
    }

    /**
     * @return list<Email>
     */
    private function sent(): array
    {
        $transport = $this->app->make('mail.manager')->mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport->messages()->map(static function (SentMessage $message): Email {
            $original = $message->getOriginalMessage();
            assert($original instanceof Email);

            return $original;
        })->values()->all();
    }
}
