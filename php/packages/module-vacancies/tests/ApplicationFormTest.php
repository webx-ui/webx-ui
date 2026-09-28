<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * The application form (decision 10): a relation to `inbox-form`, chosen by somebody who cannot
 * read submissions, waiting in the draft until it is published, and gone with the form.
 */
final class ApplicationFormTest extends TestCase
{
    #[Test]
    public function an_editor_of_vacancies_picks_a_form_without_any_inbox_permission(): void
    {
        $this->form('job-application');

        $this->actingAs($this->editor(['vacancies.view', 'vacancies.manage']), 'cms')
            ->getJson('/api/cms/relations/inbox-form')
            ->assertOk()
            ->assertJsonPath('data.0.subtitle', 'job-application');
    }

    #[Test]
    public function the_choice_waits_in_the_draft_and_goes_live_with_the_publication(): void
    {
        $form = $this->form('job-application');
        $vacancy = $this->vacancy('designer');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($vacancy->id), ['values' => ['form' => [$form->id]]])
            ->assertOk()
            ->assertJsonPath('data.values.form', [$form->id]);

        $this->assertSame([], $vacancy->refresh()->relatedIds(Vacancy::FORM));
        $this->assertNull(vacancies()->first()['form'] ?? null);

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))->assertOk();

        $this->assertSame([$form->id], $vacancy->refresh()->relatedIds(Vacancy::FORM));
        $this->assertSame('job-application', vacancies()->first()['form'] ?? null);
    }

    #[Test]
    public function a_form_that_does_not_exist_and_a_second_form_are_refused(): void
    {
        $one = $this->form('one');
        $two = $this->form('two');
        $vacancy = $this->vacancy('designer');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($vacancy->id), ['values' => ['form' => [999]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['form']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($vacancy->id), ['values' => ['form' => [$one->id, $two->id]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['form']);
    }

    #[Test]
    public function a_disabled_form_stays_chosen_and_a_deleted_one_takes_the_choice_along(): void
    {
        $form = $this->form('job-application');
        $vacancy = $this->vacancy('designer');
        $vacancy->syncRelated(Vacancy::FORM, Vacancy::FORM_TARGET, [$form->id]);

        $form->update(['is_enabled' => false]);

        $card = vacancies()->first();
        $this->assertIsArray($card);
        $this->assertNull($card['form']);
        $this->assertSame([$form->id], $this->actingAs($this->editor(), 'cms')->getJson($this->api($vacancy->id))->json('data.values.form'));

        $form->delete();

        $this->assertSame([], $vacancy->refresh()->relatedIds(Vacancy::FORM));
        $this->assertNull($vacancy->formSlug());
    }
}
