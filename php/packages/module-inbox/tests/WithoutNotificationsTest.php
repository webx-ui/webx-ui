<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\Relations\RelationTargets;
use WebxUi\Inbox\Doctor\StrandedForms;
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

    #[Test]
    public function a_form_set_to_channels_writes_letters_when_the_module_is_gone_and_the_doctor_says_so(): void
    {
        Mail::fake();

        $this->form('contact', [], [
            'notify_via' => 'channels',
            'recipients' => [['email' => 'office@site.test']],
            'notify_channels' => [3],
        ]);
        $this->form('callback', [], ['notify_via' => 'email', 'recipients' => [['email' => 'office@site.test']]]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'ada@site.test']])->assertOk();

        // Not lost without a word: the addresses kept on the form are written to.
        Mail::assertQueued(SubmissionReceived::class, static fn (SubmissionReceived $mail): bool => $mail->hasTo('office@site.test'));

        $this->assertContains(StrandedForms::class, $this->app->make(DoctorChecks::class)->all());

        $found = $this->app->make(StrandedForms::class)->run();
        $this->assertCount(1, $found);
        $this->assertTrue($found[0]->warned());
        $this->assertStringContainsString('form "contact"', $found[0]->detail);
    }
}
