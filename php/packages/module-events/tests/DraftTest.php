<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\Models\Event;

/**
 * The draft, the publication and the history of an event (§4.10) — the endpoints around the form.
 */
final class DraftTest extends TestCase
{
    #[Test]
    public function what_is_saved_waits_until_it_is_published(): void
    {
        $category = $this->category('cooking-classes');
        $event = $this->event('class', '2030-10-12 10:00:00');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->putJson($this->api($event->id), ['values' => [
            'title' => ['en' => 'Better class'],
            'categories' => [$category->id],
        ]])->assertOk();

        // The site still has the old title and no category.
        $this->get('/events/class')->assertOk()->assertSee('Class')->assertDontSee('Better class');
        $this->assertSame([], $event->refresh()->categoryIds());

        $this->actingAs($editor, 'cms')->postJson($this->api($event->id.'/publish'))
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.categories.0.id', $category->id);

        $this->get('/events/class')->assertOk()->assertSee('Better class');

        $versions = $this->actingAs($editor, 'cms')->getJson($this->api($event->id.'/versions'))->assertOk()->json('data');
        $this->assertSame([2, 1], array_column((array) $versions, 'number'));

        $this->actingAs($editor, 'cms')->postJson($this->api($event->id.'/versions/1/restore'))
            ->assertOk()
            ->assertJsonPath('data.values.title', ['en' => 'Class']);

        $this->actingAs($editor, 'cms')->postJson($this->api($event->id.'/discard'))
            ->assertOk()
            ->assertJsonPath('data.values.title', ['en' => 'Better class']);
    }

    #[Test]
    public function off_the_site_into_the_bin_and_back(): void
    {
        $event = $this->event('class', '2030-10-12 10:00:00');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->postJson($this->api($event->id.'/unpublish'))->assertOk()->assertJsonPath('data.status', 'unpublished');
        $this->get('/events/class')->assertNotFound();

        $this->actingAs($editor, 'cms')->deleteJson($this->api($event->id))->assertNoContent();
        $this->assertTrue(Event::withTrashed()->findOrFail($event->id)->trashed());

        $this->actingAs($editor, 'cms')->postJson($this->api($event->id.'/restore'))->assertOk()->assertJsonPath('data.id', $event->id);
        $this->assertFalse(Event::query()->findOrFail($event->id)->trashed());
    }

    #[Test]
    public function the_draft_is_shown_under_a_preview_token(): void
    {
        $event = $this->event('class', '2030-10-12 10:00:00', published: false);
        $editor = $this->editor();

        $this->get('/events/class')->assertNotFound();

        $url = (string) $this->actingAs($editor, 'cms')->getJson($this->api($event->id))->json('data.preview_url');

        $this->get($url)->assertOk()->assertSee('Class');
    }

    #[Test]
    public function reading_takes_view_and_writing_takes_manage(): void
    {
        $event = $this->event('class', '2030-10-12 10:00:00');
        $reader = $this->editor(['events.view']);

        $this->actingAs($reader, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($reader, 'cms')->putJson($this->api($event->id), ['values' => []])->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson($this->api($event->id.'/duplicate'))->assertForbidden();
    }
}
