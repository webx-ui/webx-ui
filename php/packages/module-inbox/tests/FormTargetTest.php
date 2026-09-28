<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Inbox\Relations\FormTarget;

/**
 * A form as the target of a relation (§4.3 of the vacancies spec): whoever chooses the form a
 * vacancy answers with need not read submissions, and a form deleted takes the rows with it.
 */
final class FormTargetTest extends TestCase
{
    #[Test]
    public function the_picker_lists_forms_to_somebody_without_any_inbox_permission(): void
    {
        $this->form('job-application');
        $this->form('contact')->update(['is_enabled' => false]);

        $rows = $this->actingAs($this->editor(['vacancies.manage']), 'cms')
            ->getJson('/api/cms/relations/'.FormTarget::KEY)
            ->assertOk()
            ->json('data');

        $this->assertIsArray($rows);
        $bySlug = [];

        foreach ($rows as $row) {
            $bySlug[$row['subtitle']] = $row;
        }

        $this->assertSame(['job-application', 'contact'], array_keys($bySlug));
        $this->assertSame('Job-application', $bySlug['job-application']['title']);
        $this->assertTrue($bySlug['job-application']['visible']);
        // Disabled: still offered and still chosen, marked, because the site shows no form there.
        $this->assertFalse($bySlug['contact']['visible']);
    }

    #[Test]
    public function a_deleted_form_takes_the_rows_pointing_at_it_along(): void
    {
        $form = $this->form('job-application');
        $other = $this->form('contact');

        foreach ([$form, $other] as $position => $target) {
            DB::table(Relations::TABLE)->insert([
                'owner_type' => 'vacancy',
                'owner_id' => 12,
                'role' => 'form',
                'target_type' => FormTarget::KEY,
                'target_id' => $target->id,
                'position' => $position,
            ]);
        }

        $form->delete();

        $this->assertSame(
            [$other->id],
            DB::table(Relations::TABLE)->where('target_type', FormTarget::KEY)->pluck('target_id')->map(intval(...))->all(),
        );
    }
}
