<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;

/**
 * The query budget of the inbox in the panel (WEBX_UI_PERFORMANCE_AUDIT.md §9.5): a page of
 * submissions, a tab of one status and one submission with its neighbours. The answers in the
 * columns are joined, not read per row, so the page costs the same for five rows and for thirty;
 * a change that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    private const SUBMISSIONS = 30;

    #[Test]
    public function the_list_of_submissions_fits_its_budget_and_does_not_grow_with_the_rows(): void
    {
        $form = $this->seedInbox();
        $editor = $this->editor();
        $list = $this->api("forms/{$form->getKey()}/submissions");
        $new = $list.'?view='.Status::query()->orderBy('position')->value('key');

        foreach ([[$list, 10], [$new, 10], [$list.'?q=Person', 10]] as [$url, $budget]) {
            $this->actingAs($editor, 'cms')->getJson($url)->assertOk();

            $this->assertQueriesWithin($budget, fn () => $this->actingAs($editor, 'cms')->getJson($url)->assertOk(), $url);
            $this->assertNoRepeatedQueries(fn () => $this->actingAs($editor, 'cms')->getJson($url)->assertOk(), $url);
        }

        $five = $this->recordQueries(fn () => $this->actingAs($editor, 'cms')->getJson($list.'?per_page=5')->assertOk())->count();
        $thirty = $this->recordQueries(fn () => $this->actingAs($editor, 'cms')->getJson($list.'?per_page=30')->assertOk())->count();
        $this->assertSame($five, $thirty);
    }

    #[Test]
    public function a_submission_fits_its_budget(): void
    {
        $form = $this->seedInbox();
        $editor = $this->editor();
        $one = $this->api('submissions/'.$form->submissions()->orderBy('id')->value('id'));

        $this->actingAs($editor, 'cms')->getJson($one)->assertOk();
        $this->assertQueriesWithin(12, fn () => $this->actingAs($editor, 'cms')->getJson($one)->assertOk());
    }

    private function seedInbox(): Form
    {
        $form = $this->form();

        for ($i = 1; $i <= self::SUBMISSIONS; $i++) {
            $submission = $this->submission($form);

            foreach ($form->fields as $field) {
                $submission->values()->create([
                    'form_id' => $form->getKey(),
                    'field_id' => $field->getKey(),
                    'name' => $field->key(),
                    'label' => (string) $field->title,
                    'type' => $field->type->value,
                    'value' => match ($field->key()) {
                        'email' => "person-{$i}@example.test",
                        'name' => "Person {$i}",
                        default => "Message {$i}",
                    },
                ]);
            }
        }

        return $form;
    }
}
