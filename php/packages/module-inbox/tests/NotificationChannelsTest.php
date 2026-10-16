<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Inbox\Events\SubmissionStored;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Notifications\SubmissionEvent;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\NotificationsServiceProvider;

/**
 * A submission and the channels of `webx-ui/module-notifications` (NOTIFICATIONS §15): the event,
 * "Notify through" on a form, the sending, the card and the resend — with the letter of the form
 * going out beside them all along.
 */
final class NotificationChannelsTest extends TestCase
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

        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from', ['address' => 'site@site.test', 'name' => 'Site']);
        $app['config']->set('queue.default', 'sync');
        // Through the queue — `sync` here, so the job runs inside the request and the test sees it.
        $app['config']->set('webx-notifications.queue.mode', 'queue');
        $app['config']->set('webx-notifications.telegram.api', 'https://telegram.test');
    }

    #[Test]
    public function the_event_is_declared_with_its_variables_and_an_addressee(): void
    {
        $event = $this->app->make(NotificationEvents::class)->find('inbox.submission');

        $this->assertNotNull($event);
        $this->assertSame('inbox', $event->module);
        $this->assertSame(['reply_to'], array_map(static fn ($one): string => $one->name, $event->addressees('email')));
        $this->assertTrue($event->knows('field.phone'));
        $this->assertTrue($event->knows('label', ['answers']));
        $this->assertNotNull($event->sampleFor('telegram'));
        $this->assertSame('New submission: {{form}}', $event->sampleFor('email')['subject'] ?? null);

        // The picker of the form's editor asks this target.
        $this->assertNotNull($this->app->make(RelationTargets::class)->find('notify-inbox-submission'));
    }

    #[Test]
    public function a_submission_goes_to_every_chosen_channel_by_its_template_and_the_letter_still_goes(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $telegram = $this->telegram();
        $email = $this->email();

        $form = $this->form('contact', [
            ['name' => 'name', 'title' => 'Name', 'is_required' => true],
            ['name' => 'email', 'type' => 'email', 'title' => 'E-mail'],
            ['name' => 'phone', 'title' => 'Phone'],
        ], [
            'recipients' => [['email' => 'office@site.test']],
            'email_field' => 'email',
            'notify_channels' => [$telegram->id, $email->id],
        ]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Alex <Doe>', 'email' => 'visitor@site.test', 'phone' => '+0 000 000-00-00'],
            'webx_page' => 'https://site.test/contacts',
        ])->assertOk();

        $submission = Submission::query()->sole();

        // Telegram: the template, the value escaped for its HTML.
        Http::assertSent(static fn (HttpRequest $request): bool => str_ends_with($request->url(), '/sendMessage')
            && $request['chat_id'] === '100200300'
            && $request['text'] === "<b>Contact</b>: Alex &lt;Doe&gt;\nPhone: +0 000 000-00-00\nhttps://example.test/cms/inbox/submissions/{$submission->id}");

        // E-mail: to the channel's address and to the visitor, the addressee of the event.
        $messages = $this->sent();
        $channelLetters = array_values(array_filter($messages, static fn (Email $one): bool => $one->getSubject() === 'Contact from Alex <Doe>'));
        $this->assertCount(2, $channelLetters);
        $to = array_map(static fn (Email $one): string => $one->getTo()[0]->getAddress(), $channelLetters);
        sort($to);
        $this->assertSame(['managers@site.test', 'visitor@site.test'], $to);
        $this->assertStringContainsString("Name: Alex <Doe>\nE-mail: visitor@site.test\nPhone: +0 000 000-00-00", (string) $channelLetters[0]->getTextBody());
        $this->assertSame('visitor@site.test', $channelLetters[0]->getReplyTo()[0]->getAddress());

        // The form's own letter went beside them, as it always has.
        $this->assertCount(1, array_filter($messages, static fn (Email $one): bool => $one->getTo()[0]->getAddress() === 'office@site.test'));
        $this->assertNotNull($submission->refresh()->notified_at);

        // One row per recipient, about this submission.
        $rows = Delivery::query()->where('subject_id', $submission->id)->get();
        $this->assertSame(['sent', 'sent', 'sent'], $rows->pluck('status')->all());
        $this->assertSame([Submission::MORPH], $rows->pluck('subject_type')->unique()->values()->all());
        $this->assertSame('https://site.test/contacts', $rows->first()?->variables['page'] ?? null);
    }

    #[Test]
    public function a_submission_sent_again_in_the_window_or_typed_in_by_hand_is_not_sent_to_channels(): void
    {
        Http::fake(['telegram.test/*' => Http::response(['ok' => true])]);

        $form = $this->form('contact', [], ['notify_channels' => [$this->telegram()->id]]);
        $fields = ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test']];

        $this->postJson($this->intake(), $fields)->assertOk();
        $this->postJson($this->intake(), $fields)->assertOk();

        $this->assertSame(1, Delivery::query()->count());

        $this->actingAs($this->admin(), 'cms')
            ->postJson($this->api("forms/{$form->id}/submissions"), ['fields' => ['name' => 'Bob', 'email' => 'bob@site.test']])
            ->assertCreated();

        $this->assertSame(1, Delivery::query()->count());
    }

    #[Test]
    public function the_form_keeps_only_channels_that_exist_and_the_editor_is_told_about_them(): void
    {
        $telegram = $this->telegram();
        $binned = $this->email();
        $binned->delete();
        $off = $this->email(['enabled' => false]);
        $form = $this->form('contact');

        $this->actingAs($this->admin(), 'cms')
            ->putJson($this->api("forms/{$form->id}"), [
                'slug' => 'contact',
                'title' => ['en' => 'Contact'],
                'is_enabled' => true,
                'options' => ['notify_channels' => [$telegram->id, (string) $binned->id, $off->id, 999, 'x', $telegram->id]],
            ])
            ->assertOk()
            ->assertJsonPath('data.options.notify_channels', [$telegram->id, $binned->id, $off->id])
            ->assertJsonPath('data.notifications', true)
            ->assertJsonPath('data.notifies', true)
            ->assertJsonPath('data.channels.0.name', 'Submissions in Telegram')
            ->assertJsonPath('data.channels.0.sends', true)
            ->assertJsonPath('data.channels.1.deleted', true)
            ->assertJsonPath('data.channels.2.sends', false);
    }

    #[Test]
    public function the_card_shows_what_the_channels_made_of_it_and_a_resend_sends_the_failed_rows_again(): void
    {
        Http::fake(['telegram.test/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
            ->push(['ok' => true])]);

        $this->form('contact', [], ['notify_channels' => [$this->telegram()->id]]);
        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test']])->assertOk();

        $submission = Submission::query()->sole();
        $admin = $this->admin();

        $this->actingAs($admin, 'cms')
            ->getJson($this->api("submissions/{$submission->id}"))
            ->assertOk()
            ->assertJsonPath('data.channels.0.status', 'failed')
            ->assertJsonPath('data.channels.0.channel', 'Submissions in Telegram')
            ->assertJsonPath('data.channels.0.retryable', true);

        // The form names nobody for a letter: the resend is the channel's alone, not a refusal.
        $this->actingAs($admin, 'cms')
            ->postJson($this->api("submissions/{$submission->id}/notify"))
            ->assertOk()
            ->assertJsonPath('data.channels.0.status', 'sent');

        Http::assertSentCount(2);
    }

    #[Test]
    public function an_agent_chooses_channels_reads_them_and_sends_failed_rows_again(): void
    {
        Http::fake(['telegram.test/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
            ->push(['ok' => true])]);

        $telegram = $this->telegram();
        $admin = $this->admin();
        $this->form('contact');

        $saved = $this->agent('form_save', ['form' => 'contact', 'options' => ['notify_channels' => [$telegram->id, 404]]], $admin);
        $this->assertSame([$telegram->id], $saved['form']['options']['notify_channels']);
        $this->assertSame('Submissions in Telegram', $saved['form']['channels'][0]['name']);

        $read = $this->agent('form_get', ['form' => 'contact'], $admin);
        $this->assertTrue($read['form']['notifications']);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test']])->assertOk();
        $submission = Submission::query()->sole();

        $got = $this->agent('get', ['submission' => $submission->id], $admin);
        $this->assertSame('failed', $got['channels'][0]['status']);

        $dry = $this->agent('notify', ['submission' => $submission->id, 'dry_run' => true], $admin);
        $this->assertSame('retry_channels', $dry['would']);
        $this->assertCount(1, $dry['would_retry']);
        Http::assertSentCount(1);

        $sent = $this->agent('notify', ['submission' => $submission->id], $admin);
        $this->assertSame('sent', $sent['channels'][0]['status']);
    }

    #[Test]
    public function the_audit_counts_a_form_that_only_has_a_channel_as_one_that_notifies(): void
    {
        $form = $this->form('contact', [], ['notify_channels' => [$this->telegram()->id]]);

        $this->actingAs($this->admin(), 'cms')
            ->getJson($this->api("forms/{$form->id}"))
            ->assertJsonPath('data.notifies', true);

        Channel::query()->update(['enabled' => false]);

        $this->actingAs($this->admin(), 'cms')
            ->getJson($this->api("forms/{$form->id}"))
            ->assertJsonPath('data.notifies', false);
    }

    #[Test]
    public function the_values_of_a_submission_are_a_snapshot_for_the_templates(): void
    {
        $form = $this->form('contact', [
            ['name' => 'name', 'title' => 'Name'],
            ['name' => 'email', 'type' => 'email', 'title' => 'E-mail'],
            ['name' => 'agree', 'type' => 'consent', 'title' => 'I agree'],
        ], ['email_field' => 'email']);

        $this->postJson($this->intake().'?utm_source=newsletter&utm_medium=mail', [
            'fields' => ['name' => 'Ada', 'email' => 'ada@site.test', 'agree' => '1'],
        ])->assertOk();

        $values = SubmissionEvent::values(Submission::query()->sole());

        $this->assertSame('Contact', $values['form']);
        $this->assertSame('contact', $values['form_slug']);
        $this->assertSame('ada@site.test', $values['reply_to']);
        $this->assertSame('Ada', $values['field.name']);
        $this->assertSame('en', $values['locale']);
        $this->assertSame('source=newsletter, medium=mail', $values['utm']);
        $this->assertStringStartsWith("Name: Ada\nE-mail: ada@site.test\nI agree: ", $values['fields']);
        $this->assertSame(['name' => 'name', 'label' => 'Name', 'value' => 'Ada'], $values['answers'][0] ?? null);
        $this->assertStringEndsWith('/cms/inbox/submissions/'.Submission::query()->sole()->id, $values['submission_url']);
        $this->assertNotSame('', $values['created_at']);
        $this->assertInstanceOf(Form::class, Submission::query()->sole()->form);
    }

    #[Test]
    public function the_stored_event_is_fired_with_the_module_too(): void
    {
        Event::fake([SubmissionStored::class]);

        $this->form('contact');
        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test']])->assertOk();

        Event::assertDispatched(SubmissionStored::class, static fn (SubmissionStored $event): bool => ! $event->repeated
            && $event->submission->value('name')?->value === 'Ada');
    }

    private function telegram(): Channel
    {
        return Channel::query()->create([
            'name' => 'Submissions in Telegram',
            'provider' => 'telegram',
            'event' => 'inbox.submission',
            'credentials' => ['token' => self::TOKEN],
            'recipients' => [['chat_id' => '100200300', 'label' => 'Managers']],
            'template' => ['en' => "<b>{{form}}</b>: {{field.name}}\nPhone: {{field.phone}}\n{{submission_url}}"],
            'enabled' => true,
        ])->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function email(array $attributes = []): Channel
    {
        return Channel::query()->create([
            'name' => 'Submissions by mail',
            'provider' => 'email',
            'event' => null,
            'options' => ['mode' => 'site'],
            'recipients' => [['email' => 'managers@site.test'], ['variable' => 'reply_to']],
            'subject' => ['en' => '{{form}} from {{field.name}}'],
            'template' => ['en' => "{{fields}}\n\n{{submission_url}}"],
            'enabled' => true,
            ...$attributes,
        ])->refresh();
    }

    private function admin(): CmsUser
    {
        return CmsUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@site.test',
            'password' => 'correct-horse-battery',
            'is_super' => true,
            'is_active' => true,
        ]);
    }

    /**
     * What a tool answered, as the agent reads it.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function agent(string $tool, array $arguments, CmsUser $as): array
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('inbox_'.$tool));
        $decoded = null;

        WebxServer::actingAs($as, 'cms')->tool($bound, $arguments)
            ->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
                $decoded = $json->etc()->toArray();
            });

        return is_array($decoded) ? $decoded : [];
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
