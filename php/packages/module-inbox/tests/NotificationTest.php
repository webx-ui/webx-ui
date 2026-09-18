<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Inbox\Mail\SubmissionReceived;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * Telling the people named on the form (§9), and what happens when that fails (§2.10).
 */
final class NotificationTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'ru'],
        ]);
    }

    #[Test]
    public function it_writes_to_the_addresses_the_form_names(): void
    {
        Mail::fake();

        $admin = $this->editor();
        $admin->update(['locale' => 'ru']);

        $this->form('contact', [], [
            'recipients' => [
                ['admin_id' => $admin->getKey()],
                ['email' => 'sales@example.test'],
                ['email' => 'not an address'],
            ],
        ]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        // Queued and not sent: the mailable is `ShouldQueue`, so a site with a queue gets one
        // and a site on `sync` sends it inline a moment later (§9).
        Mail::assertQueuedCount(2);

        // An administrator reads in the language they keep the panel in; a typed-in address
        // reads in the language the submission arrived in.
        Mail::assertQueued(SubmissionReceived::class, fn (SubmissionReceived $mail): bool => $mail->hasTo($admin->email) && $mail->locale === 'ru');
        Mail::assertQueued(SubmissionReceived::class, fn (SubmissionReceived $mail): bool => $mail->hasTo('sales@example.test') && $mail->locale === 'en');

        $submission = Submission::query()->sole();

        $this->assertNotNull($submission->notified_at);
        $this->assertNull($submission->notify_error);
        $this->assertTrue($submission->events->contains('type', SubmissionEvent::NOTIFIED));
    }

    #[Test]
    public function it_answers_to_the_person_who_wrote(): void
    {
        $this->form('contact', [], [
            'recipients' => [['email' => 'sales@example.test']],
            'email_field' => 'email',
        ]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        $mail = new SubmissionReceived(Submission::query()->sole());

        // Reply-To and never From: sending as somebody else's address is how a domain loses
        // its reputation.
        $this->assertTrue($mail->hasReplyTo('ada@example.test'));
    }

    #[Test]
    public function it_does_not_answer_to_something_that_is_not_an_address(): void
    {
        $this->form('contact', [], ['email_field' => 'name']);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        $this->assertFalse((new SubmissionReceived(Submission::query()->sole()))->hasReplyTo('Ada'));
    }

    #[Test]
    public function it_leaves_out_somebody_whose_account_is_gone_or_switched_off(): void
    {
        Mail::fake();

        $retired = $this->editor();
        $retired->update(['is_active' => false]);

        $this->form('contact', [], [
            'recipients' => [['admin_id' => $retired->getKey()], ['admin_id' => 999]],
        ]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        Mail::assertNothingOutgoing();
        $this->assertNull(Submission::query()->sole()->notified_at);
    }

    #[Test]
    public function a_mail_server_that_is_down_costs_a_notification_and_not_the_submission(): void
    {
        // The whole reason §2.10 puts the writing first: an enquiry that reached the database
        // and not the inbox is a nuisance, and a 500 to the visitor is a lost customer.
        Mail::shouldReceive('mailer')->andThrow(new RuntimeException('Connection refused'));

        $this->form('contact', [], ['recipients' => [['email' => 'sales@example.test']]]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk()->assertJson(['ok' => true]);

        $submission = Submission::query()->sole();

        $this->assertSame('ada@example.test', $submission->value('email')?->value);
        $this->assertSame('Connection refused', $submission->notify_error);
        $this->assertFalse($submission->events->contains('type', SubmissionEvent::NOTIFIED));
    }

    #[Test]
    public function it_writes_to_nobody_when_the_form_names_nobody(): void
    {
        Mail::fake();

        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        Mail::assertNothingOutgoing();

        // Not an error either: a form whose submissions are only read in the panel is a form
        // that works.
        $this->assertNull(Submission::query()->sole()->notify_error);
    }

    #[Test]
    public function the_letter_carries_the_values_the_files_and_a_way_back_to_the_panel(): void
    {
        $this->form('contact', [], ['recipients' => [['email' => 'sales@example.test']]]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello'],
            'webx_page' => 'https://example.test/contacts',
        ])->assertOk();

        $submission = Submission::query()->sole();

        (new SubmissionReceived($submission))
            ->assertSeeInHtml('Ada')
            ->assertSeeInHtml('ada@example.test')
            ->assertSeeInHtml('https://example.test/contacts')
            ->assertSeeInHtml('inbox/submissions/'.$submission->getKey());
    }
}
