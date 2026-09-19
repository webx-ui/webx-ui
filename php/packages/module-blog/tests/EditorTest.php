<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Tag;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * The editor of one article: the described screen, the values it opens with, what a save writes
 * where, and the day an article is meant to go out (§§7, 10, 12).
 *
 * Values are read out of the array rather than by path: a field name is literal, and
 * `assertJsonPath('data.values.published_at')` would look for a nested key that is not there.
 */
final class EditorTest extends TestCase
{
    #[Test]
    public function the_form_is_described_and_the_seo_card_arrives_as_a_patch(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/screens/blog.article-form')
            ->assertOk();

        /** @var array<int, array<string, mixed>> $root */
        $root = $response->json('data.root');
        /** @var array<int, array<string, mixed>> $tabs */
        $tabs = $root[0]['children'];

        $this->assertSame('wx-tabs', $root[0]['type']);
        $this->assertSame(
            ['content', 'settings', 'seo', 'history'],
            array_map(static fn (array $tab): string => (string) $tab['id'], $tabs),
        );

        // The card is not in this module's description at all: `module-seo` replaces the
        // placeholder from its own provider, the way it does on the page editor (§12).
        $this->assertSame('seo-card', $tabs[2]['children'][0]['id']);
        $this->assertSame('wx-seo', $tabs[2]['children'][0]['children'][0]['type']);
    }

    #[Test]
    public function the_editor_gets_the_values_the_revision_and_a_preview_link(): void
    {
        $repairs = $this->rubric('repairs');
        $belts = $this->tag('belts');
        $article = $this->article('signs-of-wear');
        $article->rubrics()->sync([$repairs->getKey() => ['position' => 0]]);
        $article->tags()->sync([$belts->getKey()]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api($article->getKey()))
            ->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame(['en' => 'Signs of wear'], $values['title']);
        $this->assertSame(['en' => 'signs-of-wear'], $values['slug']);
        $this->assertSame([], $values['blocks']);
        $this->assertNull($values['cover']);
        $this->assertFalse($values['pinned']);
        $this->assertSame([$repairs->getKey()], $values['rubrics']);
        $this->assertSame([$belts->getKey()], $values['tags']);

        // With the offset on it. A wall clock with no zone would be read by the picker in the
        // reader's timezone and by the server in the application's, and one article would be
        // listed at one time and edited at another.
        $this->assertSame($article->published_at?->toAtomString(), $values['published_at']);

        $this->assertIsString($response->json('data.revision'));
        $this->assertSame('blog', $response->json('data.prefix'));
        $this->assertStringContainsString('_preview/article/'.$article->getKey(), (string) $response->json('data.preview_url'));

        // What the dropdowns on the screen can be set to travels with the article: the form
        // cannot draw a rubric list without it, and a second round trip is a second chance to
        // show half a screen.
        $this->assertSame($repairs->getKey(), $response->json('data.options.rubrics.0.id'));
        $this->assertSame('Editor', $response->json('data.options.authors.0.title'));
    }

    #[Test]
    public function the_cover_travels_as_a_library_key_and_is_kept_as_a_row(): void
    {
        $article = $this->article('signs-of-wear');
        // Every file belongs to a folder, and the library's migration makes the root one.
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        $file = MediaFile::query()->forceCreate([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'covers/belts.jpg',
            'hash' => str_repeat('a', 32),
            'name' => 'belts.jpg',
            'name_lower' => 'belts.jpg',
            'file_name' => 'belts.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
        ]);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($article->getKey()), [
                'values' => ['cover' => ['path' => 'covers/belts.jpg']],
            ])
            ->assertOk();

        // Into the draft, because a cover is part of what the article says rather than of where
        // it sits — and as an id, because that is what the column holds.
        $this->assertSame((int) $file->getKey(), $article->refresh()->withDraft()->cover_id);

        $values = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api($article->getKey()))
            ->json('data.values');

        $this->assertSame(['path' => 'covers/belts.jpg'], $values['cover']);
    }

    #[Test]
    public function a_rubric_that_is_not_a_rubric_is_refused_under_its_own_field(): void
    {
        $article = $this->article('signs-of-wear');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($article->getKey()), ['values' => ['rubrics' => [9001]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rubrics']);
    }

    #[Test]
    public function a_day_picked_for_an_article_that_was_never_published_waits_in_the_draft(): void
    {
        $article = $this->article('signs-of-wear', published: false);
        $tuesday = Carbon::now()->addWeek()->startOfHour();

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($article->getKey()), [
                'values' => ['published_at' => $tuesday->toAtomString()],
            ])
            ->assertOk();

        // Nothing is on the site: saving a draft never publishes, whatever date it carries.
        $this->assertNull($article->refresh()->published_at);
        $this->assertSame(Article::STATUS_DRAFT, $article->status());

        // And it comes back to the form, so the day survives closing the tab.
        $values = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api($article->getKey()))
            ->json('data.values');

        $this->assertSame($tuesday->toAtomString(), $values['published_at']);

        // Published under that day: scheduled in the panel, a 404 on the site (§7).
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($article->getKey()).'/publish', ['at' => $tuesday->toAtomString()])
            ->assertOk()
            ->assertJsonPath('data.status', Article::STATUS_SCHEDULED);

        $this->get('/blog/signs-of-wear')->assertNotFound();
    }

    #[Test]
    public function moving_the_day_of_an_article_that_is_already_dated_writes_the_column(): void
    {
        $article = $this->article('signs-of-wear', at: Carbon::now()->addWeek());
        $yesterday = Carbon::now()->subDay()->startOfHour();

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($article->getKey()), [
                'values' => ['published_at' => $yesterday->toAtomString()],
            ])
            ->assertOk();

        // The date of an article that has a date is the column: every listing orders by it, and
        // one that moved only in a draft would sit in the wrong place until an unrelated
        // publication. Moved back, the article is simply on the site.
        $this->assertTrue($yesterday->equalTo($article->refresh()->published_at));
        $this->get('/blog/signs-of-wear')->assertOk();
    }

    #[Test]
    public function the_offset_on_a_date_is_honoured_rather_than_dropped(): void
    {
        $article = $this->article('signs-of-wear', at: Carbon::now()->addWeek());

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($article->getKey()), [
                'values' => ['published_at' => '2026-12-25T11:06:00+03:00'],
            ])
            ->assertOk();

        // Eloquent writes a `Carbon` out with its own timezone still on it, so an instant parsed
        // as `+03:00` and never converted is stored as the literal 11:06 and read back as 11:06
        // here — the article moves by the offset every time somebody saves it, silently and in
        // a direction that depends on where they are.
        $this->assertSame(
            '2026-12-25 08:06:00',
            $article->refresh()->published_at?->utc()->format('Y-m-d H:i:s'),
        );
    }

    #[Test]
    public function discarding_the_draft_leaves_what_the_site_is_showing(): void
    {
        $article = $this->article('signs-of-wear', at: Carbon::now()->subDay());

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($article->getKey()), ['values' => ['title' => ['en' => 'Half a thought']]])
            ->assertOk();

        $this->assertSame(Article::STATUS_MODIFIED, $article->refresh()->status());

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($article->getKey()).'/discard')
            ->assertOk()
            ->assertJsonPath('data.article.title', 'Signs of wear');

        $this->assertSame(Article::STATUS_PUBLISHED, $article->refresh()->status());
    }

    #[Test]
    public function the_history_lists_publications_and_restoring_one_makes_it_the_draft(): void
    {
        $article = $this->article('signs-of-wear', at: Carbon::now()->subDay());

        $article->saveDraft(['title' => ['en' => 'Signs of wear, again']]);
        $article->publish();

        $versions = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api($article->getKey()).'/versions')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $versions);
        $this->assertSame(2, $versions[0]['number']);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($article->getKey()).'/versions/1/restore')
            ->assertOk();

        // The draft, not the site: putting an old version back on the site is the same separate
        // step publishing always is.
        $article->refresh();

        $this->assertSame('Signs of wear, again', $article->title);
        $this->assertSame('Signs of wear', $article->withDraft()->title);
    }

    #[Test]
    public function a_tag_is_found_by_typing_and_made_when_it_is_not_there(): void
    {
        $belts = $this->tag('drive-belts');
        $this->article('signs-of-wear')->tags()->sync([$belts->getKey()]);

        $found = $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/blog/tags?q=belt')
            ->assertOk()
            ->json('data');

        $this->assertSame([$belts->getKey()], array_column($found, 'id'));
        $this->assertSame(1, $found[0]['articles_count']);

        $made = $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/blog/tags', ['title' => 'Гидравлика'])
            ->assertCreated()
            ->json('data');

        // The address is transliterated the way an article's is, and the tag starts out of the
        // index — opening one is a decision made on the tags screen (§2.9).
        $tag = Tag::query()->findOrFail($made['id']);

        $this->assertSame('gidravlika', $tag->getTranslation('slug', 'en'));
        $this->assertTrue($tag->noindex);
    }
}
