<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * The public door (§6, §7).
 */
final class IntakeTest extends TestCase
{
    #[Test]
    public function it_accepts_a_submission_and_answers_with_the_thank_you(): void
    {
        $this->form('contact', [], [
            'thank-you.heading' => ['en' => 'Thank you'],
            'thank-you.text' => ['en' => '<p>We will write back.</p>'],
        ]);

        $response = $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => "One\nTwo"],
        ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'heading' => 'Thank you',
            'message' => '<p>We will write back.</p>',
            'redirect' => null,
        ]);

        $submission = Submission::query()->sole();

        $this->assertSame(Submission::SOURCE_WEB, $submission->source);
        $this->assertSame('new', $submission->status->key);
        $this->assertNull($submission->read_at);
        $this->assertSame('ada@example.test', $submission->value('email')?->value);

        // The line break survives: the column is `text` and not `varchar(255)` (§2.5).
        $this->assertSame("One\nTwo", $submission->value('message')?->value);

        $this->assertSame(SubmissionEvent::CREATED, $submission->events->first()?->type);
    }

    #[Test]
    public function it_answers_in_the_language_the_page_was_printed_in(): void
    {
        // The intake's middleware is written out by hand, so nothing the site puts in its own
        // `web` group runs here — the language above all. Without the form saying which
        // language it came out in, a Russian page was answered in the application's default.
        config(['webx-localization.locales' => [
            ['code' => 'en', 'default' => true],
            ['code' => 'ru'],
        ]]);

        $this->form('contact', [], [
            'thank-you.heading' => ['en' => 'Thank you', 'ru' => 'Спасибо'],
        ]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ада', 'email' => 'ada@example.test'],
            'webx_locale' => 'ru',
        ])->assertOk()->assertJson(['heading' => 'Спасибо']);

        // And the submission remembers it, because the letter to whoever wrote in is sent in
        // the language they were reading.
        $this->assertSame('ru', Submission::query()->sole()->meta['locale']);
    }

    #[Test]
    public function it_ignores_a_language_this_site_does_not_have(): void
    {
        $this->form('contact', [], ['thank-you.heading' => ['en' => 'Thank you']]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_locale' => 'ru',
        ])->assertOk()->assertJson(['heading' => 'Thank you']);

        $this->assertSame('en', Submission::query()->sole()->meta['locale']);
    }

    #[Test]
    public function it_refuses_a_submission_the_fields_do_not_allow(): void
    {
        $this->form();

        $this->postJson($this->intake(), ['fields' => ['email' => 'not-an-address']])
            ->assertStatus(422)
            // Under the names the inputs have, so a form without JavaScript puts the message
            // beside the input that caused it (§6.3).
            ->assertJsonValidationErrors(['fields.name', 'fields.email']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function it_checks_the_rules_a_field_carries(): void
    {
        $this->form('survey', [
            ['name' => 'plan', 'type' => FieldType::Select, 'is_required' => true, 'options' => [
                'choices' => [
                    ['value' => 'basic', 'label' => ['en' => 'Basic']],
                    ['value' => 'pro', 'label' => ['en' => 'Pro']],
                ],
            ]],
            ['name' => 'extras', 'type' => FieldType::Checkbox, 'options' => [
                'choices' => [['value' => 'ssl', 'label' => 'SSL'], ['value' => 'cdn', 'label' => 'CDN']],
                'max' => 1,
            ]],
            ['name' => 'terms', 'type' => FieldType::Consent, 'is_required' => true],
            ['name' => 'note', 'type' => FieldType::Text, 'options' => ['maxlength' => 10]],
        ]);

        $this->postJson($this->intake('survey'), [
            'fields' => [
                'plan' => 'enterprise',
                'extras' => ['ssl', 'cdn'],
                'note' => 'far too long to fit',
            ],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'fields.plan',
            'fields.extras',
            'fields.terms',
            'fields.note',
        ]);
    }

    #[Test]
    public function it_writes_what_a_person_will_read_and_keeps_the_raw_values_beside_it(): void
    {
        $this->form('survey', [
            ['name' => 'plan', 'type' => FieldType::Select, 'options' => [
                'choices' => [['value' => 'pro', 'label' => ['en' => 'Pro plan']]],
            ]],
            ['name' => 'terms', 'type' => FieldType::Consent],
        ]);

        $this->postJson($this->intake('survey'), [
            'fields' => ['plan' => 'pro', 'terms' => '1'],
        ])->assertOk();

        $submission = Submission::query()->sole();

        // The label, because that is what goes into the letter and into the export.
        $this->assertSame('Pro plan', $submission->value('plan')?->value);
        $this->assertSame(['pro'], $submission->value('plan')->payload);
        $this->assertSame('Yes', $submission->value('terms')?->value);
    }

    #[Test]
    public function it_keeps_the_question_as_it_was_asked(): void
    {
        $form = $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ])->assertOk();

        // The field is renamed and then put aside, the way an editor tidying a form would do
        // it. The answer already given keeps the words it was given under (§2.2, §2.3).
        $field = $form->fields()->where('name', 'name')->sole();
        $field->update(['title' => ['en' => 'Full name'], 'type' => FieldType::Textarea]);
        $field->delete();

        $value = Submission::query()->sole()->value('name');

        $this->assertSame('Name', $value?->label);
        $this->assertSame('text', $value->type);
        $this->assertSame($field->getKey(), $value->field_id);
    }

    #[Test]
    public function it_treats_the_same_thing_sent_twice_as_one_submission(): void
    {
        $this->form();

        $values = ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello']];

        $this->postJson($this->intake(), $values)->assertOk();
        $this->postJson($this->intake(), $values)->assertOk();

        $this->assertSame(1, Submission::query()->count());
        // And not two copies of every answer either.
        $this->assertSame(3, Submission::query()->sole()->values()->count());
    }

    #[Test]
    public function it_treats_the_same_thing_sent_much_later_as_a_new_submission(): void
    {
        $this->form();

        $values = ['fields' => ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello']];

        $this->postJson($this->intake(), $values)->assertOk();

        // Outside the window the same enquiry is somebody writing again, and overwriting the
        // first one would take its date with it (§2.7).
        Carbon::setTestNow(Carbon::now()->addMinutes(20));

        $this->postJson($this->intake(), $values)->assertOk();

        $this->assertSame(2, Submission::query()->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function it_thanks_a_robot_that_filled_the_honeypot_and_writes_nothing(): void
    {
        $this->form();

        // Answered as a success on purpose: telling a robot which trick was seen is what
        // makes the next attempt harder to catch (§7).
        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_hp' => 'https://spam.test',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function it_refuses_a_form_filled_in_faster_than_a_person_could(): void
    {
        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_ts' => Crypt::encrypt((string) time()),
        ])->assertStatus(422)->assertJsonValidationErrors(['form']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function it_ignores_a_timestamp_old_enough_to_have_come_from_a_cached_page(): void
    {
        $this->form();

        // The trap of §7: on a page cached whole the mark belongs to the moment the cache was
        // written, so it reads as hours old for every visitor and must not be held against
        // anybody.
        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_ts' => Crypt::encrypt((string) (time() - 200000)),
        ])->assertOk();

        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function it_refuses_a_submission_posted_from_somebody_elses_page(): void
    {
        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ], ['Origin' => 'https://not-this-site.test'])->assertStatus(422);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
        ], ['Origin' => 'https://example.test'])->assertOk();

        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function it_stops_answering_after_the_forms_own_limit(): void
    {
        $this->form('contact', [], ['antispam.throttle' => 1]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])->assertOk();
        $this->postJson($this->intake(), ['fields' => ['name' => 'Bob', 'email' => 'b@example.test']])->assertStatus(429);

        $this->assertSame(1, Submission::query()->count());
    }

    #[Test]
    public function it_does_not_answer_for_a_form_that_is_switched_off(): void
    {
        $form = $this->form();
        $form->update(['is_enabled' => false]);

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])
            ->assertNotFound();
    }

    #[Test]
    public function it_does_not_ask_for_a_field_nobody_was_shown(): void
    {
        $form = $this->form();
        $form->fields()->where('name', 'email')->update(['is_enabled' => false]);

        // A disabled field that still validated would refuse a submission over something the
        // visitor never saw.
        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada']])->assertOk();

        $this->assertNull(Submission::query()->sole()->value('email'));
    }

    #[Test]
    public function it_remembers_where_the_submission_came_from(): void
    {
        $this->form();

        $this->postJson($this->intake().'?utm_source=newsletter', [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_page' => 'https://example.test/contacts',
        ])->assertOk();

        $meta = Submission::query()->sole()->meta;

        $this->assertSame('https://example.test/contacts', $meta['page']);
        $this->assertSame(['utm_source' => 'newsletter'], $meta['utm']);
        $this->assertSame('en', $meta['locale']);
    }

    #[Test]
    public function it_drops_the_last_octet_of_the_address_when_told_to(): void
    {
        config(['webx-inbox.anonymise_ip' => true]);

        $this->form();

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])
            ->assertOk();

        $this->assertSame('127.0.0.0', Submission::query()->sole()->meta['ip']);
    }

    #[Test]
    public function it_gives_an_arriving_submission_the_default_status(): void
    {
        Status::query()->where('key', 'new')->update(['is_default' => false]);
        Status::query()->where('key', 'in-progress')->update(['is_default' => true]);

        $this->form();

        $this->postJson($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])
            ->assertOk();

        $this->assertSame('in-progress', Submission::query()->sole()->status->key);
    }

    #[Test]
    public function it_sends_a_form_without_javascript_back_to_the_page_it_was_on(): void
    {
        $this->form('contact', [], ['thank-you.heading' => ['en' => 'Thank you']]);

        // No `Accept: application/json`, which is the shape a plain HTML form posts in: the
        // answer is a redirect and the message travels in the session (§2.8).
        $this->post($this->intake(), ['fields' => ['name' => 'Ada', 'email' => 'a@example.test']])
            ->assertRedirect()
            ->assertSessionHas('webx-inbox');

        $this->assertSame(1, Submission::query()->count());
    }
}
