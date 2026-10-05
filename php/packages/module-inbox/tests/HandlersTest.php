<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Inbox\Events\SubmissionStored;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Inbox\Tests\Fixtures\FailingHandler;
use WebxUi\Inbox\Tests\Fixtures\GiftHandler;
use WebxUi\Inbox\Tests\Fixtures\RecordingHandler;

/**
 * What a site does with a submission once it is stored (§2.19): the event, and the handlers the
 * config names. The test queue is `sync`, so a queued handler has run by the time the answer
 * comes back.
 */
final class HandlersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingHandler::$seen = [];
    }

    #[Test]
    public function the_event_comes_once_the_answers_are_written(): void
    {
        $this->form();

        $seen = [];

        Event::listen(SubmissionStored::class, function (SubmissionStored $event) use (&$seen): void {
            // Counted in the database and not on the loaded relation: an Eloquent `created`
            // would have been here before a single answer was.
            $seen[] = [
                $event->submission->values()->count(),
                $event->submission->relationLoaded('values'),
                $event->submission->form->slug,
                $event->repeated,
            ];
        });

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello'],
        ])->assertOk();

        $this->assertSame([[3, true, 'contact', false]], $seen);
    }

    #[Test]
    public function nothing_is_announced_for_what_the_antispam_stopped(): void
    {
        $this->form();

        Event::fake([SubmissionStored::class]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_hp' => 'https://spam.test',
        ])->assertOk();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ], ['Origin' => 'https://not-this-site.test'])->assertStatus(422);

        Event::assertNotDispatched(SubmissionStored::class);
    }

    #[Test]
    public function a_submission_typed_in_by_hand_is_announced_too(): void
    {
        $form = $this->form();

        Event::fake([SubmissionStored::class]);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('forms/'.$form->getKey().'/submissions'), [
                'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            ])
            ->assertCreated();

        Event::assertDispatched(
            SubmissionStored::class,
            fn (SubmissionStored $event): bool => $event->submission->source === Submission::SOURCE_PANEL,
        );
    }

    #[Test]
    public function the_handlers_of_every_form_run_before_the_ones_of_this_form(): void
    {
        config()->set('webx-inbox.handlers', [
            '*' => [RecordingHandler::class],
            'subscribe' => [GiftHandler::class, RecordingHandler::class],
        ]);

        $this->form('contact');
        $this->form('subscribe');

        $this->postJson($this->intake('contact'), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        $this->postJson($this->intake('subscribe'), [
            'fields' => ['name' => 'Bob', 'email' => 'bob@example.test'],
        ])->assertOk();

        // Each class once per submission, even when it is named in both lists; and each with the
        // answers already in hand.
        $this->assertSame([
            [RecordingHandler::class, 'ada@example.test'],
            [RecordingHandler::class, 'bob@example.test'],
            [GiftHandler::class, 'bob@example.test'],
        ], array_map(fn (array $one): array => [$one['handler'], $one['email']], RecordingHandler::$seen));

        $subscribed = Submission::query()->latest('id')->firstOrFail();

        $this->assertSame(
            [RecordingHandler::class, GiftHandler::class],
            $subscribed->events->where('type', SubmissionEvent::HANDLED)->pluck('from')->values()->all(),
        );
    }

    #[Test]
    public function a_handler_that_fails_stops_nobody_and_is_written_down(): void
    {
        config()->set('webx-inbox.handlers', [
            '*' => [FailingHandler::class, RecordingHandler::class],
        ]);

        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertCount(1, RecordingHandler::$seen, 'the next handler ran all the same');

        $events = Submission::query()->sole()->events;
        $error = $events->firstWhere('type', SubmissionEvent::HANDLER_ERROR);

        $this->assertNotNull($error);
        $this->assertSame(FailingHandler::class, $error->from);
        $this->assertSame('The CRM answered 503.', $error->to);
        $this->assertSame(RecordingHandler::class, $events->firstWhere('type', SubmissionEvent::HANDLED)?->from);
    }

    #[Test]
    public function a_class_that_is_not_a_handler_is_refused_and_written_down(): void
    {
        config()->set('webx-inbox.handlers', ['*' => [\stdClass::class, 'App\\Nowhere']]);

        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        $this->assertSame(
            [\stdClass::class, 'App\\Nowhere'],
            Submission::query()->sole()->events->where('type', SubmissionEvent::HANDLER_ERROR)->pluck('from')->values()->all(),
        );
    }

    #[Test]
    public function the_same_thing_sent_twice_runs_the_handlers_once(): void
    {
        config()->set('webx-inbox.handlers', ['*' => [RecordingHandler::class]]);

        $this->form();

        $repeated = [];

        Event::listen(SubmissionStored::class, function (SubmissionStored $event) use (&$repeated): void {
            $repeated[] = $event->repeated;
        });

        foreach ([1, 2] as $attempt) {
            $this->postJson($this->intake(), [
                'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            ])->assertOk();
        }

        // Announced both times, so a listener of its own may care; a double click is still not
        // a second subscriber to the CRM.
        $this->assertSame([false, true], $repeated);
        $this->assertCount(1, RecordingHandler::$seen);
    }

    #[Test]
    public function a_listener_of_the_site_that_throws_does_not_reach_the_visitor(): void
    {
        $this->form();

        Event::listen(SubmissionStored::class, function (): void {
            throw new RuntimeException('A bug in the site.');
        });

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, Submission::query()->count());
    }
}
