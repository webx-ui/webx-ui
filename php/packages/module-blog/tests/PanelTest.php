<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Article;

/**
 * The section's API: the list the panel draws, its filters, and what an editor may do to an
 * article (§10, §11).
 *
 * The date is what most of this is about. A scheduled article is published as far as
 * `module-admin` is concerned — `published_at` is not null — so a test that only ever uses
 * `now()` is green against code that never looks at the date at all (§7). Every test here that
 * touches a state uses a date in the future on purpose.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_three_sections_are_in_the_manifest_under_one_group(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        /** @var array<int, array<string, mixed>> $modules */
        $modules = $response->json('data.modules');
        $blog = [];

        foreach ($modules as $module) {
            if (($module['group'] ?? null) === 'blog') {
                $blog[(string) $module['id']] = $module;
            }
        }

        $this->assertSame(['articles', 'rubrics', 'tags'], array_keys($blog));
        $this->assertSame(['blog.articles.view', 'blog.articles.manage'], $blog['articles']['permissions']);
        $this->assertSame(['blog.taxonomy.manage'], $blog['tags']['permissions']);

        // The group has to be declared as well, or the entries fall out of it and stand at the
        // top level — which is what happens to a group nobody registered.
        $ids = array_column((array) $response->json('data.groups'), 'id');
        $this->assertContains('blog', $ids);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $this->postJson($this->api(), ['title' => 'Belts'])->assertUnauthorized();

        $this->actingAs($this->editor(['blog.articles.view']), 'cms')
            ->postJson($this->api(), ['title' => 'Belts'])
            ->assertForbidden();

        $this->actingAs($this->editor(['blog.articles.view']), 'cms')
            ->getJson($this->api())
            ->assertOk();
    }

    #[Test]
    public function the_list_is_a_page_of_articles_pinned_first_and_newest_first(): void
    {
        $this->article('old', at: Carbon::now()->subDays(3));
        $this->article('new', at: Carbon::now()->subDay());
        $pinned = $this->article('pinned', at: Carbon::now()->subDays(9));
        $pinned->forceFill(['pinned' => true])->save();

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame(['pinned', 'new', 'old'], array_column($response->json('data'), 'slug'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame('blog/pinned', $response->json('data.0.path'));
        $this->assertTrue($response->json('data.0.pinned'));
    }

    #[Test]
    public function a_draft_sorts_by_when_it_was_touched_rather_than_behind_everything(): void
    {
        $this->article('published', at: Carbon::now()->subDays(3));

        // No date at all, and written this minute: it is the article somebody is working on,
        // and sorting it behind every published article would put it out of sight.
        $this->article('started', published: false);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame(['started', 'published'], array_column($response->json('data'), 'slug'));
    }

    #[Test]
    public function the_status_of_each_article_is_one_of_the_five(): void
    {
        $draft = $this->article('draft', published: false);
        $scheduled = $this->article('scheduled', at: Carbon::now()->addWeek());
        $live = $this->article('live', at: Carbon::now()->subDay());

        $modified = $this->article('modified', at: Carbon::now()->subDay());
        $modified->saveDraft(['title' => ['en' => 'Modified again']]);

        $pulled = $this->article('pulled', at: Carbon::now()->subDay());
        $pulled->unpublish();

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();
        $statuses = array_column($response->json('data'), 'status', 'id');

        $this->assertSame(Article::STATUS_DRAFT, $statuses[$draft->getKey()]);
        $this->assertSame(Article::STATUS_SCHEDULED, $statuses[$scheduled->getKey()]);
        $this->assertSame(Article::STATUS_PUBLISHED, $statuses[$live->getKey()]);
        $this->assertSame(Article::STATUS_MODIFIED, $statuses[$modified->getKey()]);
        // Taken off the site is not a draft, and only the history tells the two apart.
        $this->assertSame(Article::STATUS_UNPUBLISHED, $statuses[$pulled->getKey()]);
    }

    #[Test]
    public function the_filters_narrow_the_list_without_multiplying_it(): void
    {
        $repairs = $this->rubric('repairs');
        $news = $this->rubric('news');
        $belts = $this->tag('belts');

        $both = $this->article('both', at: Carbon::now()->subDay());
        // Two rubrics and a tag: a join would show this article twice and call it two.
        $both->rubrics()->sync([$repairs->getKey() => ['position' => 0], $news->getKey() => ['position' => 1]]);
        $both->tags()->sync([$belts->getKey()]);

        $other = $this->article('other', at: Carbon::now()->subDays(2));
        $other->rubrics()->sync([$news->getKey() => ['position' => 0]]);

        $editor = $this->editor();

        $response = $this->actingAs($editor, 'cms')
            ->getJson($this->api().'?rubric='.$repairs->getKey())
            ->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('both', $response->json('data.0.slug'));
        $this->assertSame(['repairs', 'news'], array_column($response->json('data.0.rubrics'), 'slug'));

        $this->assertSame(
            ['both'],
            array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?tag='.$belts->getKey())->json('data'), 'slug'),
        );

        $this->assertSame(
            ['both', 'other'],
            array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?q=oth')->json('data'), 'slug'),
        );
    }

    #[Test]
    public function the_list_carries_what_its_filters_can_be_set_to(): void
    {
        $repairs = $this->rubric('repairs');
        $used = $this->tag('belts');
        $this->tag('never-used');

        $article = $this->article('both', at: Carbon::now()->subDay());
        $article->rubrics()->sync([$repairs->getKey() => ['position' => 0]]);
        $article->tags()->sync([$used->getKey()]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertSame(['Repairs'], array_column($response->json('filters.rubrics'), 'title'));
        // A tag nobody has used is not a filter: it can only ever answer "none".
        $this->assertSame(['Belts'], array_column($response->json('filters.tags'), 'title'));
        $this->assertSame([], $response->json('filters.authors'));
    }

    #[Test]
    public function a_scheduled_article_is_not_in_the_published_filter(): void
    {
        $this->article('waiting', at: Carbon::now()->addWeek());
        $this->article('live', at: Carbon::now()->subDay());

        $editor = $this->editor();

        $this->assertSame(
            ['live'],
            array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?status=published')->json('data'), 'slug'),
        );

        $this->assertSame(
            ['waiting'],
            array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?status=scheduled')->json('data'), 'slug'),
        );
    }

    #[Test]
    public function a_new_article_is_a_draft_with_the_address_it_will_answer_at(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'How to choose a belt'])
            ->assertCreated();

        $this->assertSame('how-to-choose-a-belt', $response->json('data.slug'));
        $this->assertSame('blog/how-to-choose-a-belt', $response->json('data.path'));
        $this->assertSame(Article::STATUS_DRAFT, $response->json('data.status'));
        $this->assertNull($response->json('data.published_at'));

        // Whoever started it is its author until somebody says otherwise.
        $this->assertSame('Editor', $response->json('data.author.name'));
    }

    #[Test]
    public function saving_writes_the_text_into_the_draft_and_the_rubrics_straight_through(): void
    {
        $repairs = $this->rubric('repairs');
        $article = $this->article('belts', at: Carbon::now()->subDay());

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($article->getKey()), [
                'values' => [
                    'title' => ['en' => 'Seven signs of wear'],
                    'rubrics' => [$repairs->getKey()],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.article.title', 'Seven signs of wear')
            ->assertJsonPath('data.article.status', Article::STATUS_MODIFIED);

        $article->refresh();

        // The site still shows what was published; the new title is waiting in the draft.
        $this->assertSame('Belts', $article->title);
        $this->assertSame('Seven signs of wear', $article->withDraft()->title);

        // The rubric is not in the draft and could not be: a pivot row is not a column (§11).
        $this->assertSame([$repairs->getKey()], $article->rubrics()->pluck('rubrics.id')->all());
    }

    #[Test]
    public function a_save_against_a_revision_somebody_else_has_moved_on_from_is_refused(): void
    {
        $article = $this->article('belts', at: Carbon::now()->subDay());
        $editor = $this->editor();

        $read = $this->actingAs($editor, 'cms')->getJson($this->api($article->getKey()))->json('data.revision');

        $this->assertIsString($read);

        // Somebody else saves in between.
        $article->saveDraft(['title' => ['en' => 'Theirs']]);

        $this->actingAs($editor, 'cms')
            ->putJson($this->api($article->getKey()), [
                'values' => ['title' => ['en' => 'Mine']],
                'revision' => $read,
            ])
            ->assertStatus(409)
            ->assertJsonPath('data.article.title', 'Theirs');

        // A request that names no revision did not read the article first and is let through.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($article->getKey()), ['values' => ['title' => ['en' => 'A script']]])
            ->assertOk();
    }

    #[Test]
    public function publishing_can_name_the_day_the_article_goes_on_the_site(): void
    {
        $article = $this->article('belts', published: false);
        $tuesday = Carbon::now()->addWeek()->startOfHour();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($article->getKey().'/publish'), ['at' => $tuesday->toAtomString()])
            ->assertOk()
            ->assertJsonPath('data.status', Article::STATUS_SCHEDULED);

        $this->assertTrue($article->refresh()->isScheduled());
        $this->assertFalse($article->isPublished());
    }

    #[Test]
    public function taking_an_article_off_the_site_keeps_its_address(): void
    {
        $article = $this->article('belts', at: Carbon::now()->subDay());

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($article->getKey().'/unpublish'))
            ->assertOk()
            ->assertJsonPath('data.status', Article::STATUS_UNPUBLISHED)
            // The registry keeps it: an address released here would be taken by the next
            // article called the same thing, and putting this one back would be a move.
            ->assertJsonPath('data.path', 'blog/belts');
    }

    #[Test]
    public function the_bin_holds_what_was_deleted_and_gives_it_back(): void
    {
        $article = $this->article('belts', at: Carbon::now()->subDay());
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api($article->getKey()))->assertNoContent();

        $this->assertSame([], $this->actingAs($editor, 'cms')->getJson($this->api())->json('data'));
        $this->assertSame(
            ['belts'],
            array_column($this->actingAs($editor, 'cms')->getJson($this->api().'?trashed=1')->json('data'), 'slug'),
        );

        $this->actingAs($editor, 'cms')
            ->postJson($this->api($article->getKey().'/restore'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'belts');

        $this->assertSame(1, $this->actingAs($editor, 'cms')->getJson($this->api())->json('meta.total'));
    }

    #[Test]
    public function a_title_saved_in_one_language_does_not_overwrite_another(): void
    {
        $this->useLocales('en', 'ru');

        $article = $this->article('belts', at: Carbon::now()->subDay());
        $article->setTranslation('title', 'ru', 'Ремни')->save();

        // The form sends the languages it edited and no others. A language nobody mentioned is
        // a language nobody meant to delete, so the map is laid over what is there rather than
        // put in its place.
        $this->actingAs($this->editor(), 'cms')
            ->withHeader('X-Webx-Locale', 'ru')
            ->putJson($this->api($article->getKey()), ['values' => ['title' => ['ru' => 'Приводные ремни']]])
            ->assertOk();

        $shown = $article->refresh()->withDraft();

        $this->assertSame('Приводные ремни', $shown->getTranslation('title', 'ru'));
        $this->assertSame('Belts', $shown->getTranslation('title', 'en'));
    }
}
