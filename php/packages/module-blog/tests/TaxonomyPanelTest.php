<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Blog\Models\Rubric;
use WebxUi\Blog\Models\Tag;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * The two screens of the taxonomy, through the doors the panel uses (§10, §11).
 *
 * Three things here are worth the round trip rather than a call on the model. The refusal to
 * delete a full rubric has to arrive as a 422 with the number in it, because the number is what
 * the editor's next move depends on. The merge has to work end to end — pivot, redirects, the
 * tags that go — since that is the one irreversible operation in the module. And the three
 * states of indexing have to be the same answer as the rendered page gives, which is exactly
 * the thing a test of the column alone would not notice (§12).
 */
final class TaxonomyPanelTest extends TestCase
{
    #[Test]
    public function the_rubrics_arrive_in_the_order_of_the_menu_with_their_counts(): void
    {
        $this->rubric('news')->update(['position' => 2]);
        $repairs = $this->rubric('repairs');
        $repairs->update(['position' => 1]);

        $this->article('changing-a-belt')->rubrics()->attach($repairs);

        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->getJson('/api/cms/blog/rubrics')
            ->assertOk();

        $this->assertSame(['Repairs', 'News'], array_column((array) $response->json('data'), 'name'));
        $this->assertSame(1, $response->json('data.0.articles_count'));
        $this->assertSame('blog/repairs', $response->json('data.0.path'));
        // The form prints the whole address as it is typed, and a slug on its own says nothing
        // about where the blog lives.
        $this->assertSame('blog', $response->json('prefix'));
    }

    #[Test]
    public function a_rubric_is_made_from_its_name_alone_and_lands_at_the_end_of_the_menu(): void
    {
        $this->rubric('news')->update(['position' => 7]);

        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->postJson('/api/cms/blog/rubrics', ['title' => ['en' => 'Spare parts']])
            ->assertCreated();

        $this->assertSame('spare-parts', $response->json('data.slug.en'));
        $this->assertSame(8, $response->json('data.position'));
        $this->get('/blog/spare-parts')->assertOk();
    }

    /**
     * The introduction is a document now, and it goes through the field type both ways.
     *
     * In: the allowlist takes out everything it does not name, because the editor is not the
     * only way to this endpoint. Out: the panel gets the document as it is stored — it edits
     * what is in the column — while the page prints it raw, so a `<script>` that survived here
     * would be a `<script>` on the site.
     */
    #[Test]
    public function the_introduction_is_cleaned_on_the_way_in_and_printed_on_the_page(): void
    {
        $rubric = $this->rubric('repairs');

        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->putJson('/api/cms/blog/rubrics/'.$rubric->getKey(), ['values' => [
                'title' => ['en' => 'Repairs'],
                'slug' => ['en' => 'repairs'],
                'lead' => ['en' => '<p>What <em>we</em> fix.</p><script>alert(1)</script>'],
            ]])
            ->assertOk();

        $this->assertSame('<p>What <em>we</em> fix.</p>', $response->json('data.values.lead.en'));

        $this->get('/blog/repairs')
            ->assertOk()
            ->assertSee('<p>What <em>we</em> fix.</p>', false)
            ->assertDontSee('alert(1)', false);
    }

    /** An emptied editor leaves `<p></p>` behind, and nothing is what that means. */
    #[Test]
    public function an_emptied_introduction_is_stored_as_nothing(): void
    {
        $rubric = $this->rubric('repairs');
        $rubric->setTranslation('lead', 'en', '<p>Something.</p>')->save();

        $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->putJson('/api/cms/blog/rubrics/'.$rubric->getKey(), ['values' => ['lead' => ['en' => '<p></p>']]])
            ->assertOk();

        $this->assertSame('', $rubric->refresh()->leadHtml('en'));
    }

    /**
     * The editor opens a rubric as a page of its own (§3.6 of the services spec): the record, the
     * values of `blog.category-form` and the prefix the address is printed after.
     */
    #[Test]
    public function a_rubric_opens_with_the_values_of_its_screen(): void
    {
        $rubric = $this->rubric('repairs');

        $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->getJson('/api/cms/blog/rubrics/'.$rubric->getKey())
            ->assertOk()
            ->assertJsonPath('data.category.id', $rubric->getKey())
            ->assertJsonPath('data.category.path', 'blog/repairs')
            ->assertJsonPath('data.values.title.en', 'Repairs')
            ->assertJsonPath('data.values.slug.en', 'repairs')
            ->assertJsonPath('data.values.is_visible', true)
            ->assertJsonPath('data.values.cover', null)
            ->assertJsonPath('data.prefix', 'blog');
    }

    /** The SEO card is `module-seo`'s patch on the rubric's screen, and saved into its own table. */
    #[Test]
    public function the_seo_card_of_a_rubric_is_saved_through_its_screen(): void
    {
        $rubric = $this->rubric('repairs');

        $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->putJson('/api/cms/blog/rubrics/'.$rubric->getKey(), ['values' => [
                'seo' => ['title' => ['en' => 'Repairs we do']],
            ]])
            ->assertOk()
            ->assertJsonPath('data.values.seo.title.en', 'Repairs we do');

        $this->assertSame('Repairs we do', $rubric->refresh()->seoValue()['title']['en'] ?? null);
    }

    /**
     * A field a project patched onto the rubric's screen is kept (§3.4): it lands in `extra`,
     * comes back with the values, survives a save that did not send it, and reads on the site
     * through its type.
     */
    #[Test]
    public function a_field_of_the_project_is_kept_on_the_rubric(): void
    {
        Screens::extend(Rubric::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'motto', 'type' => 'wx-input', 'name' => 'motto', 'label' => 'Motto', 'localized' => true],
        ]]);

        $rubric = $this->rubric('repairs');
        $editor = $this->editor(['blog.taxonomy.manage']);

        $this->actingAs($editor, 'cms')
            ->putJson('/api/cms/blog/rubrics/'.$rubric->getKey(), ['values' => ['motto' => ['en' => 'We fix it']]])
            ->assertOk()
            ->assertJsonPath('data.values.motto.en', 'We fix it');

        $this->actingAs($editor, 'cms')
            ->putJson('/api/cms/blog/rubrics/'.$rubric->getKey(), ['values' => ['title' => ['en' => 'Repair shop']]])
            ->assertOk()
            ->assertJsonPath('data.values.motto.en', 'We fix it');

        $rubric->refresh();

        $this->assertSame(['motto' => ['en' => 'We fix it']], $rubric->extraRaw());
        $this->assertSame('We fix it', $rubric->extra('motto', 'en'));
        $this->assertSame('Repair shop', $rubric->getTranslation('title', 'en'));
    }

    #[Test]
    public function a_slug_of_the_wrong_shape_is_refused_under_its_field(): void
    {
        $rubric = $this->rubric('repairs');

        $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->putJson('/api/cms/blog/rubrics/'.$rubric->getKey(), ['values' => ['slug' => ['en' => 'two words']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    #[Test]
    public function a_rubric_that_still_holds_articles_is_refused_with_the_number(): void
    {
        $rubric = $this->rubric('repairs');
        $this->article('changing-a-belt')->rubrics()->attach($rubric);
        $this->article('choosing-a-belt')->rubrics()->attach($rubric);

        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->deleteJson('/api/cms/blog/rubrics/'.$rubric->getKey())
            ->assertStatus(422);

        // Two, and said out loud: one article gets moved, forty means the rubric was the right
        // idea after all (§6).
        $this->assertMatchesRegularExpression('/\b2\b/', (string) $response->json('message'));
        $this->assertNotNull(Rubric::query()->find($rubric->getKey()));
    }

    #[Test]
    public function an_empty_rubric_goes(): void
    {
        $rubric = $this->rubric('repairs');

        $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->deleteJson('/api/cms/blog/rubrics/'.$rubric->getKey())
            ->assertNoContent();

        $this->assertNull(Rubric::query()->find($rubric->getKey()));
        $this->get('/blog/repairs')->assertNotFound();
    }

    #[Test]
    public function the_order_of_the_menu_is_the_whole_list_at_once(): void
    {
        $first = $this->rubric('repairs');
        $second = $this->rubric('news');
        $third = $this->rubric('parts');

        $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->postJson('/api/cms/blog/rubrics/reorder', [
                'ids' => [$third->getKey(), $first->getKey(), $second->getKey()],
            ])
            ->assertNoContent();

        $order = Rubric::query()->inMenuOrder()->pluck('id')->all();

        $this->assertSame([$third->getKey(), $first->getKey(), $second->getKey()], $order);
    }

    #[Test]
    public function the_tags_page_carries_the_counts_the_filters_wear(): void
    {
        $used = $this->tag('belts');
        $this->tag('expo');
        $open = $this->tag('hydraulics', noindex: false);

        $this->article('changing-a-belt')->tags()->attach([$used->getKey(), $open->getKey()]);

        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->getJson('/api/cms/blog/tags')
            ->assertOk();

        $this->assertSame(3, $response->json('filters.total'));
        $this->assertSame(1, $response->json('filters.empty'));
        // Two carry the flag and neither has a rule, so both are out of the index.
        $this->assertSame(2, $response->json('filters.noindex'));
        $this->assertSame('belts', $response->json('data.0.slug'));
    }

    /**
     * The three states, and the one that a merge of fields would never produce (§12).
     *
     * `indexed — SEO rule` is the answer for a tag that carries the flag and whose address a
     * rule matches. It is the same question the rendered page asks, so the row and the page can
     * never disagree — and this is the test that says so, because the alternative is an editor
     * looking at a row saying `noindex` about a page that is in the index.
     */
    #[Test]
    public function the_column_of_indexing_says_the_same_thing_the_page_does(): void
    {
        $open = $this->tag('hydraulics', noindex: false);
        $ruled = $this->tag('belts');
        $out = $this->tag('expo');

        SeoUrl::query()->create([
            'match_type' => UrlMatcher::EXACT,
            'pattern' => '/blog/tag/belts',
            'is_active' => true,
            'title' => ['en' => 'Everything about belts'],
            'description' => ['en' => 'A page worth having in the index.'],
        ]);

        $states = $this->states();

        $this->assertSame(Tag::INDEXING_OPEN, $states[(int) $open->getKey()]);
        $this->assertSame(Tag::INDEXING_RULE, $states[(int) $ruled->getKey()]);
        $this->assertSame(Tag::INDEXING_NOINDEX, $states[(int) $out->getKey()]);

        // The page agrees: the rule's title is printed and no `noindex` with it, although the
        // flag on the tag is still set.
        $page = $this->get('/blog/tag/belts')->assertOk();
        $page->assertSee('Everything about belts', false);
        $page->assertDontSee('noindex', false);

        // And the filter counts the same thing the column says: one tag out of three.
        $filtered = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->getJson('/api/cms/blog/tags?noindex=1')
            ->assertOk();

        $this->assertSame([$out->getKey()], array_column((array) $filtered->json('data'), 'id'));
    }

    #[Test]
    public function turning_the_rule_off_puts_the_tag_back_out_of_the_index(): void
    {
        $tag = $this->tag('belts');

        $rule = SeoUrl::query()->create([
            'match_type' => UrlMatcher::EXACT,
            'pattern' => '/blog/tag/belts',
            'is_active' => true,
            'title' => ['en' => 'Everything about belts'],
        ]);

        $this->assertSame(Tag::INDEXING_RULE, $this->states()[(int) $tag->getKey()]);

        $rule->update(['is_active' => false]);

        $this->assertSame(Tag::INDEXING_NOINDEX, $this->states()[(int) $tag->getKey()]);
        $this->get('/blog/tag/belts')->assertSee('noindex', false);
    }

    #[Test]
    public function renaming_a_tag_leaves_its_address_where_it_was(): void
    {
        $tag = $this->tag('belts');

        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->putJson('/api/cms/blog/tags/'.$tag->getKey(), ['title' => 'Drive belts'])
            ->assertOk();

        $this->assertSame('Drive belts', $response->json('data.title'));
        // A word spelled three ways before lunch would otherwise leave three aliases behind a
        // decision nobody made.
        $this->assertSame('/blog/tag/belts', $response->json('data.path'));
        $this->get('/blog/tag/belts')->assertOk();
    }

    #[Test]
    public function the_selection_bar_opens_and_deletes_a_pile_at_once(): void
    {
        $first = $this->tag('belts');
        $second = $this->tag('expo');

        $editor = $this->editor(['blog.taxonomy.manage']);

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blog/tags/mass', [
                'ids' => [$first->getKey(), $second->getKey()],
                'action' => 'index',
            ])
            ->assertOk()
            ->assertJsonPath('data.affected', 2);

        $this->assertFalse((bool) $first->fresh()?->noindex);

        $this->actingAs($editor, 'cms')
            ->postJson('/api/cms/blog/tags/mass', [
                'ids' => [$second->getKey()],
                'action' => 'delete',
            ])
            ->assertOk();

        $this->assertNull(Tag::query()->find($second->getKey()));
    }

    /**
     * The merge, through the endpoint the dialog presses (§6).
     *
     * The article that carried both tags is the whole point: `unique(article_id, tag_id)` is the
     * guarantee, which is why this is a `syncWithoutDetaching` and not an `update` on the pivot.
     */
    #[Test]
    public function merging_moves_the_articles_and_leaves_the_redirects_behind(): void
    {
        $keep = $this->tag('belts');
        $merged = $this->tag('belt');

        $both = $this->article('changing-a-belt');
        $both->tags()->attach([$keep->getKey(), $merged->getKey()]);

        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->postJson('/api/cms/blog/tags/merge', [
                'ids' => [$keep->getKey(), $merged->getKey()],
                'keep' => $keep->getKey(),
                'redirect' => true,
            ])
            ->assertOk();

        $this->assertSame(1, $response->json('data.articles_count'));
        $this->assertSame(1, $response->json('data.merged'));
        $this->assertSame([$keep->getKey()], $both->fresh()?->tags->modelKeys());
        $this->assertNull(Tag::query()->find($merged->getKey()));

        // An alias of `webx-ui/routing` would have died with the tag; a redirect does not, which
        // is the whole reason the checkbox writes one.
        $this->assertSame(1, SeoRedirect::query()->count());
        $this->get('/blog/tag/belt')->assertRedirect('/blog/tag/belts')->assertStatus(301);
    }

    #[Test]
    public function a_merge_into_nothing_but_itself_is_refused_under_the_field_that_asks(): void
    {
        $keep = $this->tag('belts');

        $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->postJson('/api/cms/blog/tags/merge', [
                'ids' => [$keep->getKey()],
                'keep' => $keep->getKey(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.keep.0', fn (mixed $message): bool => is_string($message));

        $this->assertNotNull(Tag::query()->find($keep->getKey()));
    }

    #[Test]
    public function writing_the_taxonomy_needs_the_permission_for_it(): void
    {
        $rubric = $this->rubric('repairs');

        $this->postJson('/api/cms/blog/rubrics', ['title' => ['en' => 'News']])->assertUnauthorized();

        // Writing articles is not writing the taxonomy — except for making a tag, which is what
        // the article form does (§2.8).
        $writer = $this->editor(['blog.articles.view', 'blog.articles.manage']);

        $this->actingAs($writer, 'cms')
            ->deleteJson('/api/cms/blog/rubrics/'.$rubric->getKey())
            ->assertForbidden();

        $this->actingAs($writer, 'cms')->getJson('/api/cms/blog/rubrics')->assertOk();
        $this->actingAs($writer, 'cms')->postJson('/api/cms/blog/tags', ['title' => 'Belts'])->assertCreated();
    }

    /**
     * The state of every tag, keyed by id, as the screen reads it.
     *
     * @return array<int, string>
     */
    private function states(): array
    {
        $response = $this->actingAs($this->editor(['blog.taxonomy.manage']), 'cms')
            ->getJson('/api/cms/blog/tags')
            ->assertOk();

        $states = [];

        foreach ((array) $response->json('data') as $row) {
            $states[(int) $row['id']] = (string) $row['indexing'];
        }

        return $states;
    }
}
