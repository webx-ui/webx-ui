<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;

/**
 * The forms as the panel reads and writes them (§12).
 */
final class PanelFormsTest extends TestCase
{
    #[Test]
    public function it_lists_forms_with_what_is_waiting_in_them(): void
    {
        $contact = $this->form('contact');
        $this->form('callback');

        $this->submission($contact);
        $this->submission($contact, ['read_at' => now()]);
        // Spam is not what anybody means by "three new": it is hidden by default, and a badge
        // counting what the list will not show is a badge nobody can ever clear.
        $this->submission($contact, ['status_id' => Status::spam()?->getKey()]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api('forms'))->assertOk();

        $forms = $response->json('data');

        $this->assertCount(2, $forms);
        $this->assertSame('contact', $forms[0]['slug']);
        $this->assertSame(3, $forms[0]['submissions_count']);
        $this->assertSame(1, $forms[0]['unread_count']);
        $this->assertSame(0, $forms[1]['submissions_count']);
    }

    #[Test]
    public function it_creates_a_form_at_the_end_of_the_column(): void
    {
        $first = $this->form('contact');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('forms'), [
                'slug' => 'callback',
                'title' => ['en' => 'Call me back'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'callback')
            ->assertJsonPath('data.title.en', 'Call me back')
            ->assertJsonPath('data.is_enabled', true);

        $made = Form::query()->where('slug', 'callback')->sole();

        $this->assertGreaterThan($first->position, $made->position);
    }

    #[Test]
    public function it_refuses_an_address_that_is_taken_or_misshapen(): void
    {
        $this->form('contact');

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('forms'), ['slug' => 'contact', 'title' => 'Contact'])
            ->assertJsonValidationErrors('slug');

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('forms'), ['slug' => 'Contact Us', 'title' => 'Contact'])
            ->assertJsonValidationErrors('slug');
    }

    #[Test]
    public function it_keeps_only_the_settings_it_knows_about(): void
    {
        $form = $this->form('contact');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api("forms/{$form->getKey()}"), [
                'slug' => 'contact',
                'title' => ['en' => 'Contact'],
                'options' => [
                    // A key with a dot in it is one key, not a path (§5).
                    'thank-you.heading' => ['en' => 'Thank you'],
                    'thank-you.text' => ['en' => ''],
                    'antispam.captcha' => 'turnstile',
                    'antispam.min_seconds' => '4',
                    'recipients' => [['email' => 'sales@example.test']],
                    'whatever' => 'nobody asked for this',
                ],
            ])
            ->assertOk();

        $options = $form->refresh()->options;

        $this->assertSame(['en' => 'Thank you'], $options['thank-you.heading']);
        $this->assertSame('turnstile', $options['antispam.captcha']);
        $this->assertSame(4, $options['antispam.min_seconds']);
        $this->assertSame([['email' => 'sales@example.test']], $options['recipients']);
        // An empty line is absent rather than blank: `option()` answers with a default for a
        // key that is not there, and a blank one reads as a decision.
        $this->assertArrayNotHasKey('thank-you.text', $options);
        $this->assertArrayNotHasKey('whatever', $options);
    }

    #[Test]
    public function it_refuses_a_recipient_that_is_neither_an_administrator_nor_an_address(): void
    {
        $form = $this->form('contact');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api("forms/{$form->getKey()}"), [
                'slug' => 'contact',
                'title' => 'Contact',
                'options' => ['recipients' => [['email' => 'not-an-address']]],
            ])
            ->assertJsonValidationErrors('options.recipients');
    }

    #[Test]
    public function it_will_not_delete_a_form_that_has_taken_submissions(): void
    {
        $form = $this->form('contact');
        $this->submission($form);

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api("forms/{$form->getKey()}"))
            ->assertStatus(422);

        $this->assertModelExists($form);
    }

    #[Test]
    public function it_deletes_a_form_nobody_has_ever_used(): void
    {
        $form = $this->form('contact');

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api("forms/{$form->getKey()}"))
            ->assertNoContent();

        $this->assertModelMissing($form);
    }

    #[Test]
    public function it_copies_a_form_switched_off_and_with_its_questions(): void
    {
        $form = $this->form('contact');

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("forms/{$form->getKey()}/duplicate"))
            ->assertCreated();

        $this->assertSame('contact-2', $response->json('data.slug'));
        $this->assertFalse($response->json('data.is_enabled'));
        $this->assertCount(3, $response->json('data.fields'));
        // The same number as the address, so the two copies are told apart in the column.
        $this->assertSame('Contact 2', $response->json('data.title.en'));
    }

    #[Test]
    public function it_saves_the_order_of_the_column(): void
    {
        $first = $this->form('contact');
        $second = $this->form('callback');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('forms/sorting'), ['ids' => [$second->getKey(), $first->getKey()]])
            ->assertNoContent();

        $this->assertSame(0, $second->refresh()->position);
        $this->assertSame(1, $first->refresh()->position);
    }

    #[Test]
    public function reading_the_section_is_one_permission_and_changing_a_form_is_another(): void
    {
        $form = $this->form('contact');

        // The column is the navigation of the section: somebody who may read submissions has
        // to be able to reach one.
        $this->actingAs($this->editor(['inbox.view']), 'cms')
            ->getJson($this->api('forms'))
            ->assertOk();

        $this->actingAs($this->editor(['inbox.view', 'inbox.update']), 'cms')
            ->putJson($this->api("forms/{$form->getKey()}"), ['slug' => 'contact', 'title' => 'No'])
            ->assertForbidden();

        $this->actingAs($this->editor([]), 'cms')
            ->getJson($this->api('forms'))
            ->assertForbidden();
    }

    #[Test]
    public function it_names_the_administrators_a_form_can_be_told_to_write_to(): void
    {
        $reader = $this->editor(['inbox.view']);
        $stranger = $this->editor(['pages.view']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('recipients'))
            ->assertOk();

        $emails = array_column($response->json('data'), 'email');

        $this->assertContains($reader->email, $emails);
        // Writing to somebody who cannot open the submission is a letter and then a 403.
        $this->assertNotContains($stranger->email, $emails);
    }
}
