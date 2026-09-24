<?php

declare(strict_types=1);

namespace WebxUi\Services\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Services\Models\Service;

/**
 * The editor of one service through the API (§4.6, §4.7, §4.13): the described screen, the values
 * it opens with, the draft, the revision, the project's fields and the history.
 *
 * Values are read out of the array rather than by path: a field name is literal, and a dotted
 * path would look for a nested key that is not there (CLAUDE.md §4).
 */
final class EditorTest extends TestCase
{
    #[Test]
    public function the_form_is_described_and_the_seo_card_arrives_as_a_patch(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/screens/services.form')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $root */
        $root = $response->json('data.root');
        /** @var array<int, array<string, mixed>> $tabs */
        $tabs = $root[0]['children'];

        $this->assertSame(['content', 'settings', 'seo', 'history'], array_column($tabs, 'id'));
        $this->assertSame('seo-card', $tabs[2]['children'][0]['id']);
    }

    #[Test]
    public function the_editor_gets_the_values_the_revision_the_prefix_and_a_preview(): void
    {
        $surgery = $this->category('surgery');
        $service = $this->service('implants');
        $service->syncCategories([$surgery->getKey()]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api($service->getKey()))->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame(['en' => 'Implants'], $values['title']);
        $this->assertSame(['en' => 'implants'], $values['slug']);
        $this->assertSame([$surgery->getKey()], $values['categories']);
        $this->assertNull($values['cover']);
        $this->assertSame('services', $response->json('data.prefix'));
        $this->assertIsString($response->json('data.revision'));
        $this->assertStringContainsString('_preview/service/'.$service->getKey(), (string) $response->json('data.preview_url'));
    }

    #[Test]
    public function a_save_goes_into_the_draft_and_the_site_keeps_what_is_published(): void
    {
        $service = $this->service('implants');
        $editor = $this->editor();
        $revision = $this->actingAs($editor, 'cms')->getJson($this->api($service->getKey()))->json('data.revision');

        $response = $this->actingAs($editor, 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['title' => ['en' => 'Dental implants'], 'lead' => ['en' => 'For life.']],
            'revision' => $revision,
        ])->assertOk();

        $this->assertSame('modified', $response->json('data.service.status'));
        $this->assertSame('Dental implants', $response->json('data.service.title'));
        $this->assertSame('Implants', $service->refresh()->getTranslation('title', 'en'));

        $this->actingAs($editor, 'cms')->postJson($this->api($service->getKey().'/publish'))->assertOk();

        $this->assertSame('Dental implants', $service->refresh()->getTranslation('title', 'en'));
        $this->assertSame('For life.', $service->getTranslation('lead', 'en'));
    }

    #[Test]
    public function a_save_over_somebody_elses_is_refused_with_the_service_as_it_now_is(): void
    {
        $service = $this->service('implants');
        $editor = $this->editor();

        $response = $this->actingAs($editor, 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['title' => ['en' => 'Mine']],
            'revision' => 'stale',
        ])->assertStatus(409);

        $this->assertSame('Implants', $response->json('data.service.title'));
        $this->assertFalse($service->refresh()->hasDraft());
    }

    #[Test]
    public function categories_take_effect_at_once_and_the_first_is_the_main_one(): void
    {
        $surgery = $this->category('surgery');
        $implantology = $this->category('implantology');
        $service = $this->service('implants');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['categories' => [$implantology->getKey(), $surgery->getKey()]],
        ])->assertOk();

        $this->assertSame($implantology->getKey(), $service->refresh()->mainServiceCategory()?->getKey());
        $this->assertFalse($service->hasDraft());
    }

    #[Test]
    public function a_category_that_is_not_one_is_refused_under_the_field(): void
    {
        $service = $this->service('implants');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['categories' => [999]],
        ])->assertUnprocessable()->assertJsonValidationErrors('categories');
    }

    #[Test]
    public function a_slug_taken_by_a_category_is_refused_under_the_slug(): void
    {
        $this->category('surgery');
        $service = $this->service('implants');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['slug' => ['en' => 'surgery']],
        ])->assertOk();

        // The address is the draft's until it is published, and publishing is where the registry
        // is asked — and says no, under the slug.
        $this->actingAs($editor, 'cms')->postJson($this->api($service->getKey().'/publish'))
            ->assertUnprocessable();

        $this->assertSame('services/implants', $service->refresh()->routeCanonical('en')?->path);
    }

    #[Test]
    public function a_field_of_the_project_is_saved_through_the_panel_and_kept_by_other_saves(): void
    {
        Screens::extend(Service::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'price-from', 'type' => 'wx-input-number', 'name' => 'price-from', 'label' => 'Price from'],
        ]]);

        $service = $this->service('crowns');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['price-from' => 450],
        ])->assertOk();

        // A save of another tab carries no `price-from`, and must not empty it.
        $response = $this->actingAs($editor, 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['title' => ['en' => 'Porcelain crowns']],
        ])->assertOk();

        $this->assertSame(450, $response->json('data.values')['price-from']);
        $this->assertNull($service->refresh()->extra('price-from'));

        $this->actingAs($editor, 'cms')->postJson($this->api($service->getKey().'/publish'))->assertOk();

        $this->assertSame(450, $service->refresh()->extra('price-from'));
    }

    #[Test]
    public function a_value_the_field_refuses_is_refused_the_same_way_as_everywhere(): void
    {
        Screens::extend(Service::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'price-from', 'type' => 'wx-input-number', 'name' => 'price-from', 'label' => 'Price from'],
        ]]);

        $service = $this->service('crowns');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($service->getKey()), [
            'values' => ['price-from' => 'a lot'],
        ])->assertUnprocessable();
    }

    #[Test]
    public function discarding_goes_back_to_what_is_published(): void
    {
        $service = $this->service('implants');
        $service->saveDraft(['title' => ['en' => 'Waiting']]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($service->getKey().'/discard'))
            ->assertOk();

        $this->assertSame('published', $response->json('data.service.status'));
        $this->assertSame(['en' => 'Implants'], $response->json('data.values')['title']);
    }

    #[Test]
    public function the_history_lists_publications_and_an_old_one_comes_back_as_the_draft(): void
    {
        $service = $this->service('implants');
        $service->saveDraft(['title' => ['en' => 'Dental implants']]);
        $service->publish();

        $editor = $this->editor();

        $versions = $this->actingAs($editor, 'cms')->getJson($this->api($service->getKey().'/versions'))->assertOk();
        $this->assertSame([2, 1], array_column((array) $versions->json('data'), 'number'));

        $response = $this->actingAs($editor, 'cms')
            ->postJson($this->api($service->getKey().'/versions/1/restore'))
            ->assertOk();

        $this->assertSame(['en' => 'Implants'], $response->json('data.values')['title']);
        $this->assertSame('Dental implants', $service->refresh()->getTranslation('title', 'en'));
    }
}
