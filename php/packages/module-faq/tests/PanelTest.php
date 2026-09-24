<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;

/**
 * The section as the panel reaches it (§4.5, §4.6): the manifest, the permissions, the list with
 * its two orders, the form that creates and saves, and the categories with no address.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_two_sections_are_in_the_manifest_under_one_group(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        /** @var array<int, array<string, mixed>> $modules */
        $modules = $response->json('data.modules');
        $ours = [];

        foreach ($modules as $module) {
            if (($module['group'] ?? null) === 'faq') {
                $ours[(string) $module['id']] = $module;
            }
        }

        $this->assertSame(['faq', 'faq-categories'], array_keys($ours));
        $this->assertSame(['faq.view', 'faq.manage'], $ours['faq']['permissions']);
        $this->assertSame(['faq.categories.manage'], $ours['faq-categories']['permissions']);

        $icons = array_column((array) $response->json('data.groups'), 'icon', 'id');
        $this->assertSame('question', $icons['faq'] ?? null);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $question = $this->question('Refunds');

        $this->getJson($this->api())->assertUnauthorized();

        $viewer = $this->editor(['faq.view']);

        $this->actingAs($viewer, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($viewer, 'cms')->getJson($this->api($question->id))->assertOk();
        $this->actingAs($viewer, 'cms')->postJson($this->api(), ['values' => ['question' => ['en' => 'Cards']]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->putJson($this->api($question->id), ['values' => ['published' => false]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('reorder'), ['ids' => [$question->id]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson('/api/cms/faq/categories', ['title' => 'Billing'])->assertForbidden();

        // The categories are read by anybody who may open a question: the form files into them.
        $this->actingAs($viewer, 'cms')->getJson('/api/cms/faq/categories')->assertOk();
    }

    #[Test]
    public function the_list_is_the_whole_faq_in_its_order_with_the_categories_beside_it(): void
    {
        $billing = $this->category('Billing');
        [$a, $b, $c] = [$this->question('A', 'А'), $this->question('B'), $this->question('C', published: false)];
        $a->syncCategories([$billing->id]);
        DB::table('faq_questions')->where('id', $a->id)->update(['position' => 30]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame([$b->id, $c->id, $a->id], array_column((array) $response->json('data'), 'id'));
        $this->assertSame(['en', 'ru'], $response->json('data.2.locales'));
        $this->assertSame(['en'], $response->json('data.0.locales'));
        $this->assertFalse($response->json('data.1.published'));
        $this->assertSame([['id' => $billing->id, 'title' => 'Billing']], $response->json('data.2.categories'));
        $this->assertSame([['id' => $billing->id, 'title' => 'Billing']], $response->json('filters.categories'));

        $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=B')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function a_drag_writes_the_order_it_was_made_in(): void
    {
        $billing = $this->category('Billing');
        $a = $this->question('A', categories: [$billing]);
        $b = $this->question('B', categories: [$billing]);
        $c = $this->question('C');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$b->id, $a->id], 'category' => $billing->id])
            ->assertNoContent();

        $inside = $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?category='.$billing->id)->assertOk();
        $this->assertSame([$b->id, $a->id], array_column((array) $inside->json('data'), 'id'));

        $whole = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();
        $this->assertSame([$a->id, $b->id, $c->id], array_column((array) $whole->json('data'), 'id'), 'the order inside a category is its own');
    }

    #[Test]
    public function a_new_question_is_created_from_the_values_of_its_form(): void
    {
        $billing = $this->category('Billing');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => [
                'question' => ['en' => 'Do you take cards?', 'ru' => 'Принимаете карты?'],
                'answer' => ['en' => '<p>Yes.</p>'],
                'published' => true,
                'categories' => [$billing->id],
            ],
        ])->assertCreated();

        $question = Question::query()->findOrFail($response->json('data.question.id'));

        $this->assertSame('do-you-take-cards', $response->json('data.question.anchor'));
        $this->assertSame(['en' => 'Do you take cards?', 'ru' => 'Принимаете карты?'], $response->json('data.values.question'));
        $this->assertSame([$billing->id], $response->json('data.values.categories'));
        $this->assertTrue($question->visibleIn('en'));
        $this->assertFalse($question->visibleIn('ru'));
    }

    #[Test]
    public function a_refused_save_says_which_field_and_leaves_nothing_behind(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => ['question' => ['en' => 'Orphan'], 'categories' => [999]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['categories']);

        $this->assertSame(0, Question::query()->withTrashed()->count());
    }

    #[Test]
    public function a_save_lays_one_language_over_the_others_and_keeps_the_anchor(): void
    {
        $question = $this->question('Refunds', 'Возврат');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($question->id), [
            'values' => ['question' => ['en' => 'Getting your money back'], 'published' => false],
        ])->assertOk()
            ->assertJsonPath('data.values.question', ['en' => 'Getting your money back', 'ru' => 'Возврат'])
            ->assertJsonPath('data.question.anchor', 'refunds')
            ->assertJsonPath('data.values.published', false);
    }

    #[Test]
    public function a_field_of_the_project_goes_into_extra(): void
    {
        Screens::extend(Question::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'source', 'type' => 'wx-input', 'name' => 'source', 'label' => 'Where it came from'],
        ]]);

        $question = $this->question('Refunds');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($question->id), ['values' => ['source' => 'The call centre']])
            ->assertOk()
            ->assertJsonPath('data.values.source', 'The call centre');

        $this->assertSame('The call centre', $question->refresh()->extra('source'));
    }

    #[Test]
    public function the_bin_keeps_the_question_and_gives_it_back(): void
    {
        $question = $this->question('Refunds');

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($question->id))->assertNoContent();
        $this->assertSame([], $this->actingAs($this->editor(), 'cms')->getJson($this->api())->json('data'));
        $this->assertSame([$question->id], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?trashed=1')->json('data'), 'id'));

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($question->id.'/restore'))
            ->assertOk()
            ->assertJsonPath('data.anchor', 'refunds');
    }

    #[Test]
    public function a_category_has_no_address(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/faq/categories', ['title' => ['en' => 'Billing and payment']])
            ->assertCreated();

        $category = FaqCategory::query()->findOrFail($response->json('data.id'));

        $this->assertNull($category->getRawOriginal('slug'));
    }
}
