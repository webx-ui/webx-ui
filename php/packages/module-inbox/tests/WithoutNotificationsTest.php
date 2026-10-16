<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Inbox\Events\SubmissionStored;
use WebxUi\Inbox\Mail\SubmissionReceived;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Notifications\Channels;

/**
 * A site without `webx-ui/module-notifications` (NOTIFICATIONS §15): the code may sit in `vendor`,
 * its provider does not run. The inbox works as it always has — the letter, the event for the
 * site's listeners — and nothing about channels shows.
 */
final class WithoutNotificationsTest extends TestCase
{
    #[Test]
    public function the_letter_goes_and_the_event_is_fired_as_before(): void
    {
        Mail::fake();
        Event::fake([SubmissionStored::class]);

        $this->form('contact', [], [
            'recipients' => [['email' => 'office@site.test']],
            'notify_channels' => [3],
        ]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test']])->assertOk();

        Mail::assertQueued(SubmissionReceived::class, static fn (SubmissionReceived $mail): bool => $mail->hasTo('office@site.test'));
        Event::assertDispatched(SubmissionStored::class, static fn (SubmissionStored $event): bool => ! $event->repeated
            && $event->submission->value('email')?->value === 'ada@site.test');

        $this->assertFalse($this->app->make(Channels::class)->available());
        $this->assertNull($this->app->make(RelationTargets::class)->find('notify-inbox-submission'));
    }

    #[Test]
    public function the_editor_and_the_card_have_no_channels_and_a_saved_choice_is_kept(): void
    {
        $form = $this->form('contact');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->putJson($this->api("forms/{$form->id}"), [
                'slug' => 'contact',
                'title' => ['en' => 'Contact'],
                'is_enabled' => true,
                'options' => ['notify_channels' => [5, '5', 7, 'x']],
            ])
            ->assertOk()
            ->assertJsonPath('data.notifications', false)
            ->assertJsonPath('data.channels', [])
            // Kept as the ids came, each once: removing the package must not wipe the choice.
            ->assertJsonPath('data.options.notify_channels', [5, 7])
            ->assertJsonPath('data.notifies', false);

        $submission = $this->submission($form);

        $this->actingAs($editor, 'cms')
            ->getJson($this->api("submissions/{$submission->id}"))
            ->assertOk()
            ->assertJsonPath('data.channels', null);
    }

    #[Test]
    public function a_resend_without_recipients_is_still_refused(): void
    {
        $form = $this->form('contact', [], ['notify_channels' => [5]]);
        $submission = $this->submission($form, ['source' => Submission::SOURCE_WEB]);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("submissions/{$submission->id}/notify"))
            ->assertStatus(422);
    }
}
