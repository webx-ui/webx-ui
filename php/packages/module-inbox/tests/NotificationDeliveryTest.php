<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Throwable;
use WebxUi\Inbox\Audit\NotificationTrouble;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * A letter through a real queue (§9): pushing the job is not sending the letter, so the
 * submission says *queued* until a worker has sent it or given up on it.
 *
 * The queue is faked and its jobs are run by hand, the way a worker runs them — `handle()`,
 * and `failed()` once the tries are spent — against a transport that refuses one address.
 */
final class NotificationDeliveryTest extends TestCase
{
    public const DOWN = 'down@example.test';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('mail.default', 'refusing');
        $app['config']->set('mail.mailers.refusing', ['transport' => 'refusing']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // What the site on a real server saw: the SMTP server is not there for one address.
        Mail::extend('refusing', static fn (): AbstractTransport => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                foreach ($message->getEnvelope()->getRecipients() as $recipient) {
                    if ($recipient->getAddress() === NotificationDeliveryTest::DOWN) {
                        throw new TransportException('Connection could not be established with host "127.0.0.1:1025"');
                    }
                }
            }

            public function __toString(): string
            {
                return 'refusing://';
            }
        });
    }

    #[Test]
    public function a_queued_letter_is_queued_and_not_yet_sent(): void
    {
        Queue::fake();

        $submission = $this->send(['sales@example.test', 'boss@example.test']);

        Queue::assertPushed(SendQueuedMailable::class, 2);

        // The old mistake was exactly here: `notified_at`, no error and a NOTIFIED line.
        $this->assertNull($submission->notified_at);
        $this->assertNull($submission->notify_error);
        $this->assertNotNull($submission->notify_queued_at);
        $this->assertSame(Submission::NOTIFY_QUEUED, $submission->notification()['state']);
        $this->assertSame(['queued', 'queued'], array_column($submission->notification()['recipients'], 'state'));
        $this->assertFalse($submission->events->contains('type', SubmissionEvent::NOTIFIED));
        $this->assertSame('2', $submission->events->firstWhere('type', SubmissionEvent::NOTIFY_QUEUED)?->to);
    }

    #[Test]
    public function the_worker_sending_it_marks_it_delivered(): void
    {
        $queue = Queue::fake();

        $submission = $this->send(['sales@example.test', 'boss@example.test']);

        $this->work($queue);

        $submission->refresh();

        $this->assertSame(Submission::NOTIFY_DELIVERED, $submission->notification()['state']);
        $this->assertNotNull($submission->notified_at);
        $this->assertNull($submission->notify_queued_at);
        $this->assertSame(['delivered', 'delivered'], array_column($submission->notification()['recipients'], 'state'));

        // One line for the attempt, with the count, once every letter of it has settled.
        $this->assertSame(['2'], $submission->events->where('type', SubmissionEvent::NOTIFIED)->pluck('to')->values()->all());
    }

    #[Test]
    public function a_letter_the_queue_gives_up_on_is_recorded_on_the_submission(): void
    {
        $queue = Queue::fake();

        $submission = $this->send(['sales@example.test', self::DOWN]);

        $this->work($queue);

        $submission->refresh();
        $notification = $submission->notification();

        $this->assertSame(Submission::NOTIFY_FAILED, $notification['state']);
        $this->assertStringContainsString('127.0.0.1:1025', (string) $submission->notify_error);
        $this->assertNull($submission->notify_queued_at);

        // Per recipient: the one that left and the one that did not, with its own reason.
        $this->assertSame('delivered', $notification['recipients'][0]['state']);
        $this->assertSame(self::DOWN, $notification['recipients'][1]['address']);
        $this->assertSame('failed', $notification['recipients'][1]['state']);
        $this->assertStringContainsString('127.0.0.1:1025', (string) $notification['recipients'][1]['error']);

        $this->assertSame(self::DOWN, $submission->events->firstWhere('type', SubmissionEvent::NOTIFY_FAILED)?->to);
        $this->assertSame('1', $submission->events->firstWhere('type', SubmissionEvent::NOTIFIED)?->to);
    }

    #[Test]
    public function a_letter_about_a_submission_deleted_in_the_meantime_is_dropped_quietly(): void
    {
        $queue = Queue::fake();

        $submission = $this->send(['sales@example.test']);
        $submission->delete();

        // Before, the worker threw ModelNotFoundException unpacking the job — the letter went
        // to failed_jobs and `failed()` tried to report on a row that is gone.
        $this->work($queue);

        $this->assertSame(0, Submission::query()->count());
        $this->assertSame(0, SubmissionEvent::query()->count());
    }

    #[Test]
    public function a_letter_that_never_reached_the_queue_is_not_said_to_be_queued(): void
    {
        // A queue whose table is not there: every push throws, as on a site whose queue
        // database or mailer is misconfigured.
        config([
            'queue.default' => 'broken',
            'queue.connections.broken' => ['driver' => 'database', 'table' => 'no_such_jobs', 'queue' => 'default'],
        ]);

        $submission = $this->send(['sales@example.test']);

        $this->assertSame(Submission::NOTIFY_FAILED, $submission->notification()['state']);
        $this->assertFalse($submission->events->contains('type', SubmissionEvent::NOTIFY_QUEUED));
        $this->assertSame('sales@example.test', $submission->events->firstWhere('type', SubmissionEvent::NOTIFY_FAILED)?->to);
    }

    #[Test]
    public function a_letter_reported_twice_is_written_down_once(): void
    {
        $queue = Queue::fake();

        $submission = $this->send([self::DOWN]);

        // A job run again after its first report — a worker killed before it acknowledged.
        $this->work($queue);
        $this->work($queue);

        $this->assertCount(1, $submission->refresh()->events->where('type', SubmissionEvent::NOTIFY_FAILED));
    }

    #[Test]
    public function on_sync_the_letter_is_written_down_as_before(): void
    {
        $submission = $this->send(['sales@example.test', self::DOWN]);

        // The inline path: `notified_at` for the attempt, the last error beside it, a NOTIFIED
        // line with the count of letters that left — and now who each letter was for.
        $this->assertNotNull($submission->notified_at);
        $this->assertStringContainsString('127.0.0.1:1025', (string) $submission->notify_error);
        $this->assertNull($submission->notify_queued_at);
        $this->assertSame('1', $submission->events->firstWhere('type', SubmissionEvent::NOTIFIED)?->to);
        $this->assertSame(['delivered', 'failed'], array_column($submission->notification()['recipients'], 'state'));
        $this->assertFalse($submission->events->contains('type', SubmissionEvent::NOTIFY_QUEUED));
    }

    #[Test]
    public function a_submission_from_before_reads_as_it_did(): void
    {
        $form = $this->form();

        $this->assertSame('delivered', $this->submission($form, ['notified_at' => now()])->notification()['state']);
        $this->assertSame('failed', $this->submission($form, ['notified_at' => now(), 'notify_error' => 'x'])->notification()['state']);
        $this->assertSame('none', $this->submission($form)->notification()['state']);
        $this->assertSame([], $this->submission($form)->notification()['recipients']);
    }

    #[Test]
    public function the_panel_sends_it_again_and_says_who_asked(): void
    {
        $editor = $this->editor();
        $submission = $this->send([self::DOWN]);

        $this->assertSame('failed', $submission->notification()['state']);

        $form = $submission->form;
        $form->update(['options' => ['recipients' => [['email' => 'sales@example.test']]]]);

        $this->actingAs($editor, 'cms')
            ->postJson($this->api("submissions/{$submission->getKey()}/notify"))
            ->assertOk()
            ->assertJsonPath('data.notification.state', 'delivered')
            ->assertJsonPath('data.notify_error', null);

        $this->assertSame((int) $editor->getKey(), $submission->refresh()->events->where('type', SubmissionEvent::NOTIFIED)->last()?->admin_id);
    }

    #[Test]
    public function the_panel_refuses_to_send_it_for_a_form_that_names_nobody(): void
    {
        $submission = $this->submission($this->form());

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("submissions/{$submission->getKey()}/notify"))
            ->assertStatus(422);

        // And somebody who may only read does not get to send letters.
        $this->actingAs($this->editor(['inbox.view']), 'cms')
            ->postJson($this->api("submissions/{$submission->getKey()}/notify"))
            ->assertForbidden();
    }

    #[Test]
    public function the_audit_finds_letters_that_failed_or_wait_for_no_worker(): void
    {
        $form = $this->form();
        $check = new NotificationTrouble;

        $this->assertSame([], $check->findings(30, 30));

        $this->submission($form, ['notify_error' => 'Connection refused']);
        $this->submission($form, ['notify_queued_at' => Carbon::now()->subHours(2)]);
        // Queued a minute ago: a worker may simply not have got to it yet.
        $this->submission($form, ['notify_queued_at' => Carbon::now()->subMinute()]);
        // Failed long ago: past the window, somebody has had their chance to see it.
        $this->submission($form, ['notify_error' => 'old', 'created_at' => Carbon::now()->subDays(90)]);

        $findings = (new NotificationTrouble)->findings(30, 30);

        $this->assertSame(['failed', 'queued'], array_map(static fn ($finding): string => $finding->key, $findings));
        $this->assertSame(1, $findings[0]->details['summary']['params']['count']);
        $this->assertSame(1, $findings[1]->details['summary']['params']['count']);
        $this->assertSame('webx-inbox::audit.notify-failed', $findings[0]->details['summary']['key']);
    }

    /**
     * A submission to a form that names these addresses, through the public door.
     *
     * @param  list<string>  $addresses
     */
    private function send(array $addresses): Submission
    {
        $this->form('contact', [], [
            'recipients' => array_map(static fn (string $address): array => ['email' => $address], $addresses),
        ]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        return Submission::query()->with('events')->sole();
    }

    /** What a worker does with each pushed letter, its only try being its last. */
    private function work(QueueFake $queue): void
    {
        foreach ($queue->pushed(SendQueuedMailable::class) as $job) {
            // Through serialisation, as a worker gets it: the flag must survive the trip.
            $job = unserialize(serialize($job));

            try {
                $job->handle($this->app->make('mail.manager'));
            } catch (Throwable $exception) {
                $job->failed($exception);
            }
        }
    }
}
