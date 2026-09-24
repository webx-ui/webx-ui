<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Blocks\Models\Block;
use WebxUi\Pages\Models\Page;
use WebxUi\Pages\Tests\Fixtures\ProseType;
use WebxUi\Pages\Tests\Fixtures\TipSource;

/**
 * The editor: the described screen, the values it opens with, the save that goes into the
 * draft, and the check that keeps two writers from silently overwriting each other (§§6, 10).
 *
 * Values are read out of the array rather than by path: a field name is literal, and
 * `assertJsonPath('data.values.title')` would look for a nested key that is not there.
 */
final class EditorTest extends TestCase
{
    #[Test]
    public function the_form_is_described_and_has_the_four_tabs(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/screens/pages.form')
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
    }

    #[Test]
    public function the_editor_gets_the_values_the_revision_and_a_preview_link(): void
    {
        $page = $this->page('about');

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api($page->getKey()))
            ->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame(['en' => 'About'], $values['title']);
        $this->assertSame(['en' => 'about'], $values['slug']);
        $this->assertSame([], $values['blocks']);
        $this->assertFalse($values['is_home']);

        $this->assertIsString($response->json('data.revision'));
        $this->assertStringContainsString('_preview/page/'.$page->getKey(), (string) $response->json('data.preview_url'));

        // The trail is the breadcrumbs of the head, and the home page is the first of them.
        $this->assertTrue($response->json('data.ancestors.0.is_home'));

        // The whole address is the page above plus the slug, so the form is handed the first
        // half — and the home page's half is the empty string, not the absence of one.
        $this->assertSame(['en' => ''], $response->json('data.address_prefix'));
    }

    #[Test]
    public function a_save_writes_the_draft_and_leaves_the_site_alone(): void
    {
        $page = $this->page('about');

        $response = $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($page->getKey()), [
                'values' => [
                    'title' => ['en' => 'About us'],
                    'blocks' => [['type' => 'text', 'key' => 'a1', 'values' => ['text' => 'Hello']]],
                ],
            ])
            ->assertOk();

        $this->assertSame('About', $page->refresh()->title, 'the columns are what the site shows');
        $this->assertSame('About us', $page->withDraft()->title);
        $this->assertSame(Page::STATUS_MODIFIED, $response->json('data.page.status'));

        // What was not sent is not lost: the slug of a page saved from the content tab stays.
        $this->assertSame(['en' => 'about'], $page->withDraft()->getTranslations('slug'));

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');
        $this->assertCount(1, $values['blocks']);
    }

    #[Test]
    public function a_save_keeps_a_block_value_the_way_its_field_type_keeps_it(): void
    {
        // `wx-blocks` is the one field on this screen the server does not register, so its
        // value arrives whole and what is inside it is nobody's until `module-blocks` is
        // asked. Through the endpoint rather than through the form object: the question is
        // whether the editor's own save runs this at all.
        $this->app->make(FieldTypes::class)->register('wx-prose', new ProseType);

        $block = Block::query()->create(['slug' => 'article', 'title' => 'Article']);
        $block->saveVersion([
            'template' => '<article data-wx-block="article">{!! $body !!}</article>',
            'schema' => [['id' => 'body', 'type' => 'wx-prose', 'localized' => true]],
        ]);
        $block->publish();

        $page = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($page->getKey()), [
                'values' => [
                    'blocks' => [[
                        'key' => 'k1',
                        'type' => 'article',
                        'hidden' => true,
                        'values' => ['body' => ['en' => '<p>Hello<script>steal()</script></p>']],
                    ]],
                ],
            ])
            ->assertOk();

        $node = $page->refresh()->draftValues()['blocks'][0];

        $this->assertSame('<p>Hello</p>', $node['values']['body']['en']);
        $this->assertTrue($node['hidden'], 'the node keeps every key it came with');
        $this->assertSame('k1', $node['key']);
    }

    #[Test]
    public function a_save_keeps_a_collection_block_as_the_choice_the_source_can_act_on(): void
    {
        // The door that matters for `wx-collection` (§3.3 of the FAQ spec): the editor's save,
        // through the endpoint. A source without categories or markup keeps neither, whatever
        // the request said, and a string that is a number is kept as one.
        $this->app->make(CollectionSources::class)->register(new TipSource);

        $block = Block::query()->create(['slug' => 'tips', 'title' => 'Tips']);
        $block->saveVersion([
            'template' => '<ul data-wx-block="tips">@foreach ($list[\'items\'] as $tip)<li>{{ $tip[\'text\'] }}</li>@endforeach</ul>',
            'schema' => [['id' => 'list', 'type' => 'wx-collection', 'props' => ['source' => 'tips']]],
        ]);
        $block->publish();

        $page = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($page->getKey()), [
                'values' => [
                    'blocks' => [[
                        'key' => 'k1',
                        'type' => 'tips',
                        'values' => ['list' => ['categories' => [4, 2], 'limit' => '3', 'filter' => true, 'markup' => true]],
                    ]],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            ['categories' => [], 'limit' => 3, 'filter' => true, 'markup' => null],
            $page->refresh()->draftValues()['blocks'][0]['values']['list'],
        );
    }

    #[Test]
    public function a_field_the_screen_does_not_name_is_dropped(): void
    {
        $page = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($page->getKey()), [
                'values' => ['title' => ['en' => 'About us'], 'published_at' => null, 'lft' => 1],
            ])
            ->assertOk();

        $draft = $page->refresh()->draftValues();

        $this->assertSame(['title', 'slug', 'blocks'], array_keys($draft));
    }

    #[Test]
    public function the_home_page_cannot_be_given_an_address_through_the_form(): void
    {
        $home = $this->home();

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($home->getKey()), [
                'values' => ['title' => ['en' => 'Front'], 'slug' => ['en' => 'front']],
            ])
            ->assertOk();

        $this->assertSame([], array_filter($home->refresh()->withDraft()->getTranslations('slug')));
    }

    #[Test]
    public function a_save_that_read_the_page_before_somebody_else_wrote_is_refused(): void
    {
        $page = $this->page('about');
        $editor = $this->editor();

        $stale = (string) $this->actingAs($editor, 'cms')
            ->getJson($this->api($page->getKey()))
            ->json('data.revision');

        // Somebody else, in between.
        $page->saveDraft(['title' => ['en' => 'Theirs'], 'slug' => $page->getTranslations('slug')]);

        $response = $this->actingAs($editor, 'cms')
            ->putJson($this->api($page->getKey()), [
                'values' => ['title' => ['en' => 'Mine']],
                'revision' => $stale,
            ])
            ->assertStatus(409);

        $this->assertSame('Theirs', $page->refresh()->withDraft()->title, 'the other edit stands');

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');
        $this->assertSame(['en' => 'Theirs'], $values['title'], 'the answer carries the page as it now is');
        $this->assertNotSame($stale, $response->json('data.revision'));
    }

    #[Test]
    public function the_revision_that_came_back_saves(): void
    {
        $page = $this->page('about');
        $editor = $this->editor();

        $revision = (string) $this->actingAs($editor, 'cms')
            ->getJson($this->api($page->getKey()))
            ->json('data.revision');

        $this->actingAs($editor, 'cms')
            ->putJson($this->api($page->getKey()), [
                'values' => ['title' => ['en' => 'Mine']],
                'revision' => $revision,
            ])
            ->assertOk();

        $this->assertSame('Mine', $page->refresh()->withDraft()->title);
    }

    #[Test]
    public function a_request_with_no_revision_is_let_through(): void
    {
        $page = $this->page('about');
        $page->saveDraft(['title' => ['en' => 'Theirs'], 'slug' => $page->getTranslations('slug')]);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($page->getKey()), ['values' => ['title' => ['en' => 'Mine']]])
            ->assertOk();

        $this->assertSame('Mine', $page->refresh()->withDraft()->title);
    }

    #[Test]
    public function the_history_lists_publications_with_who_and_where_from(): void
    {
        $page = $this->page('about', published: false);
        $editor = $this->editor();

        $page->saveDraft(['title' => ['en' => 'First']]);
        $page->publish($editor->getKey());

        $page->saveDraft(['title' => ['en' => 'Second']], null, EntityVersion::SOURCE_MCP);
        $page->publish(null, EntityVersion::SOURCE_MCP);

        $response = $this->actingAs($editor, 'cms')
            ->getJson($this->api($page->getKey().'/versions'))
            ->assertOk();

        $this->assertSame([2, 1], $response->json('data.*.number'));
        $this->assertSame(EntityVersion::SOURCE_MCP, $response->json('data.0.source'));
        $this->assertNull($response->json('data.0.author'));
        $this->assertSame('Editor', $response->json('data.1.author'));
    }

    #[Test]
    public function an_autosave_is_not_part_of_the_history(): void
    {
        $page = $this->page('about');
        $page->saveDraft(['title' => ['en' => 'Being written']]);

        $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api($page->getKey().'/versions'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function restoring_a_version_puts_it_into_the_draft_and_not_onto_the_site(): void
    {
        $page = $this->page('about', published: false);

        $page->saveDraft(['title' => ['en' => 'First']]);
        $page->publish();
        $page->saveDraft(['title' => ['en' => 'Second']]);
        $page->publish();

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($page->getKey().'/versions/1/restore'))
            ->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame(['en' => 'First'], $values['title']);
        $this->assertSame('Second', $page->refresh()->title, 'the site still shows what was published');
        $this->assertSame(Page::STATUS_MODIFIED, $response->json('data.page.status'));
    }

    #[Test]
    public function a_version_that_is_not_there_is_a_404(): void
    {
        $page = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($page->getKey().'/versions/7/restore'))
            ->assertNotFound();
    }

    #[Test]
    public function a_viewer_may_read_the_history_and_not_write(): void
    {
        $page = $this->page('about');

        $this->actingAs($this->editor(['pages.view']), 'cms')
            ->getJson($this->api($page->getKey().'/versions'))
            ->assertOk();

        $this->actingAs($this->editor(['pages.view']), 'cms')
            ->putJson($this->api($page->getKey()), ['values' => ['title' => ['en' => 'Mine']]])
            ->assertForbidden();
    }
}
