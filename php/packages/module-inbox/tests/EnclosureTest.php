<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Contracts\Enclosure;
use WebxUi\Inbox\Mail\SubmissionReceived;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Submissions\Enclosed;
use WebxUi\Inbox\Submissions\Enclosures;

/**
 * What a module puts into a submission beside the answers (§2.20): asked for by name, refused for
 * a form it is not for, answered with the errors of the fields, written as a row with no field,
 * part of the hash, in the letter, in the export and in the panel's card.
 */
final class EnclosureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(Enclosures::class)->register(new class implements Enclosure
        {
            public function name(): string
            {
                return 'basket';
            }

            public function accepts(Form $form, Request $request): bool
            {
                return $form->slug === 'contact';
            }

            public function make(Form $form, Request $request): Enclosed
            {
                $count = $request->input('basket.count');

                if (! is_numeric($count) || (int) $count < 1) {
                    throw ValidationException::withMessages(['basket.count' => 'At least one, please.']);
                }

                // Counted here, never read from the request: the price a browser sends is anybody's.
                $total = (int) $count * 250;

                return new Enclosed('Basket', "Apples × {$count}\nTotal: {$total}", ['kind' => 'basket', 'count' => (int) $count, 'total' => $total]);
            }
        });
    }

    #[Test]
    public function a_named_enclosure_is_written_as_an_answer_without_a_field(): void
    {
        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_enclosure' => ['basket'],
            'basket' => ['count' => 3, 'total' => 1],
        ])->assertOk();

        $row = Submission::query()->sole()->value('basket');

        $this->assertNotNull($row);
        $this->assertNull($row->field_id);
        $this->assertSame(Enclosures::TYPE, $row->type);
        $this->assertSame('Basket', $row->label);
        $this->assertSame("Apples × 3\nTotal: 750", $row->value);
        $this->assertSame(['kind' => 'basket', 'count' => 3, 'total' => 750], $row->payload);
    }

    #[Test]
    public function a_request_that_names_none_is_a_submission_like_any_other(): void
    {
        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            // A name nobody registered: the module that printed it may be gone since the page was cached.
            'webx_enclosure' => ['nobody'],
        ])->assertOk();

        $this->assertNull(Submission::query()->sole()->value('basket'));
        $this->assertCount(2, Submission::query()->sole()->values);
    }

    #[Test]
    public function its_errors_come_back_with_the_errors_of_the_fields(): void
    {
        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['email' => 'ada@example.test'],
            'webx_enclosure' => ['basket'],
            'basket' => ['count' => 0],
        ])->assertStatus(422)->assertJsonValidationErrors(['fields.name', 'basket.count']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function a_form_it_is_not_for_refuses_it(): void
    {
        $this->form('callback');

        $this->postJson($this->intake('callback'), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_enclosure' => ['basket'],
            'basket' => ['count' => 2],
        ])->assertStatus(422)->assertJsonValidationErrors(['basket']);

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function two_different_enclosures_from_one_person_are_two_submissions(): void
    {
        $this->form();
        $answers = ['name' => 'Ada', 'email' => 'ada@example.test'];

        foreach ([2, 2, 5] as $count) {
            $this->postJson($this->intake(), ['fields' => $answers, 'webx_enclosure' => ['basket'], 'basket' => ['count' => $count]])->assertOk();
        }

        // The same basket twice is the double click; another basket is another enquiry.
        $this->assertSame(2, Submission::query()->count());
    }

    #[Test]
    public function the_letter_and_the_export_carry_its_text(): void
    {
        Mail::fake();

        $form = $this->form('contact', [], ['notify.emails' => ['office@example.test']]);

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_enclosure' => ['basket'],
            'basket' => ['count' => 3],
        ])->assertOk();

        $letter = new SubmissionReceived(Submission::query()->sole());
        $html = $letter->render();

        $this->assertStringContainsString('Basket', $html);
        $this->assertStringContainsString('Apples × 3<br />', $html);

        $csv = $this->actingAs($this->editor(), 'cms')
            ->get($this->api('forms/'.$form->getKey().'/submissions/export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Basket (basket)', $csv);
        $this->assertStringContainsString("\"Apples × 3\nTotal: 750\"", $csv);
    }

    #[Test]
    public function the_card_of_the_panel_has_its_payload(): void
    {
        $this->form();

        $this->postJson($this->intake(), [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_enclosure' => ['basket'],
            'basket' => ['count' => 1],
        ])->assertOk();

        $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('submissions/'.Submission::query()->sole()->getKey()))
            ->assertOk()
            ->assertJsonFragment([
                'field_id' => null,
                'name' => 'basket',
                'type' => 'enclosure',
                'payload' => ['kind' => 'basket', 'count' => 1, 'total' => 250],
            ]);
    }
}
