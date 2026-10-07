<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Exceptions\PagesException;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;

/**
 * What the panel's doors refuse, and what they copy — the holes a full pass over the section
 * found between what the form, the list and the model each believed.
 */
final class PanelRefusalsTest extends TestCase
{
    #[Test]
    public function a_save_that_empties_the_title_is_refused(): void
    {
        $page = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($page->getKey()), ['values' => ['title' => ['en' => '']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);

        $this->assertFalse($page->refresh()->hasDraft());
    }

    #[Test]
    public function a_page_does_not_come_back_under_a_parent_still_in_the_bin(): void
    {
        $parent = $this->page('parent');
        $child = $this->page('child', $parent);
        $grandchild = $this->page('grandchild', $child);

        $grandchild->delete();
        $parent->refresh()->delete();

        $refused = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($grandchild->getKey()).'/restore')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, '#'.$parent->getKey()));
        // A refusal with no field still answers `errors` as a map, like every other 422 —
        // a client reading it as `Record<string, string[]>` must not get a list.
        $this->assertStringContainsString('"errors":{}', $refused->getContent());

        $this->assertTrue(Page::withTrashed()->findOrFail($grandchild->getKey())->trashed());
        // It took no top-level address while it was refused.
        $this->assertFalse(Route::query()->where('path', 'grandchild')->exists());

        // The way it is meant to go: the branch first, then the page deleted on its own.
        $this->assertTrue(Page::withTrashed()->findOrFail($parent->getKey())->restoreBranch());
        $this->assertTrue(Page::withTrashed()->findOrFail($grandchild->getKey())->restoreBranch());
        $this->assertSame('parent/child/grandchild', $grandchild->refresh()->routeCanonical('en')?->path);
    }

    #[Test]
    public function the_model_names_the_page_to_restore_first(): void
    {
        $parent = $this->page('parent');
        $child = $this->page('child', $parent);

        $child->delete();
        $parent->refresh()->delete();

        $this->expectException(PagesException::class);
        $this->expectExceptionMessage('Parent');

        Page::withTrashed()->findOrFail($child->getKey())->restoreBranchWithTrail();
    }

    #[Test]
    public function a_duplicate_copies_what_the_editor_sees_and_who_made_it(): void
    {
        // Never published: its content lives only in the draft.
        $page = $this->page('landing', published: false);
        $page->saveDraft([
            'title' => ['en' => 'Landing, reworded'],
            'slug' => ['en' => 'landing'],
            'blocks' => [['type' => 'text', 'key' => 'a1', 'values' => ['text' => 'Hello']]],
        ]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($page->getKey()).'/duplicate')
            ->assertCreated();

        $this->assertSame('Landing, reworded (copy)', $response->json('data.title'));
        $this->assertSame('Editor', $response->json('data.edited_by'));

        $copy = Page::query()->findOrFail($response->json('data.id'));
        $this->assertCount(1, $copy->withDraft()->blocksTree());
        $this->assertNull($copy->published_at);
    }

    #[Test]
    public function a_parent_that_does_not_exist_is_a_refused_field_not_a_404(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'News', 'parent_id' => 9999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        // The field by the name the dialog gives it, not «title».
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.title.0', fn (string $message): bool => str_contains($message, (string) __('webx-pages::page.field-title')) && ! str_contains($message, ' title '));
    }

    #[Test]
    public function a_reorder_among_siblings_changes_no_address(): void
    {
        $first = $this->page('first');
        $second = $this->page('second');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($second->getKey()).'/move', ['target' => $first->getKey(), 'zone' => 'before'])
            ->assertOk()
            ->assertJsonPath('data.addresses_changed', 0);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($second->getKey()).'/move', ['target' => $second->getKey(), 'zone' => 'after'])
            ->assertStatus(422)
            ->assertJsonPath('message', __('webx-pages::errors.move-beside-self'));
    }
}
