<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Models\Block;
use WebxUi\Pages\Models\Page;

/**
 * The blocks of the panel on the site (WEBX_UI_SITE_BAR.md, §8, §9, §11): the markers an
 * administrator's page is printed with, a block's fields written into the draft from the site,
 * and «Publish» and «Discard» in the bar's head.
 */
final class SiteBarBlocksTest extends TestCase
{
    private const EDITOR = ['pages.view', 'pages.manage', 'blocks.view'];

    #[Test]
    public function a_visitor_gets_the_page_with_blocks_byte_for_byte_as_without_the_bar(): void
    {
        $this->pageWithBlocks();

        $with = $this->get('/pricing')->assertOk();

        $this->app['config']->set('webx-admin.site_bar.enabled', false);
        $without = $this->get('/pricing')->assertOk();

        $this->assertSame($without->getContent(), $with->getContent());
        $this->assertStringContainsString('<p>Live words</p>', (string) $with->getContent());
        $this->assertStringNotContainsString('<!--wx', (string) $with->getContent());
    }

    #[Test]
    public function an_administrators_page_marks_the_blocks_and_the_record(): void
    {
        $page = $this->pageWithBlocks();
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Draft words']]]]);
        $this->signIn($this->editor(self::EDITOR));

        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        $id = $page->getKey();

        // Published values, the markers around them, and the record's pair around all of it.
        $this->assertStringContainsString("<!--wx-entity:page:{$id}--><!--wx:k1--><p>Live words</p><!--/wx:k1--><!--/wx-entity:page:{$id}-->", $html);
        $this->assertStringNotContainsString('Draft words', $html);
    }

    #[Test]
    public function a_failing_block_is_a_gap_on_an_administrators_page_not_a_notice(): void
    {
        $page = $this->pageWithBlocks();
        // Fails on the page's values only: its own sample has to pass for it to be published.
        $this->blockType('broken', '@if ($text === \'boom\') @php throw new RuntimeException(\'boom\'); @endphp @endif<p>{{ $text }}</p>');
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'broken', 'values' => ['text' => 'boom']]]]);
        $page->publish();
        $this->signIn($this->editor(self::EDITOR));

        $html = (string) $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('<!--wx:k1--><!--/wx:k1-->', $html);
        $this->assertStringNotContainsString('wx-block-error', $html);
    }

    #[Test]
    public function markers_for_a_bar_that_does_not_come_are_taken_out(): void
    {
        $page = $this->pageWithBlocks();
        // `seo.manage` gives the bar on any address — so the markers are printed — but this
        // response has no `</body>` to put the bar before.
        $this->signIn($this->editor([...self::EDITOR, 'seo.manage']));

        Route::middleware('web')->get('/fragment', static fn (): string => (string) $page->renderBlocks());

        $this->assertSame('<p>Live words</p>', $this->get('/fragment')->assertOk()->getContent());
    }

    #[Test]
    public function the_context_offers_the_blocks_tab_with_blocks_view_only(): void
    {
        $this->pageWithBlocks();

        $with = $this->actingAs($this->editor(self::EDITOR), 'cms')
            ->getJson('/api/cms/site-bar/context?url=/pricing')
            ->assertOk()
            ->assertJsonPath('data.entity.editing', 'pages')
            ->assertJsonPath('data.entity.can.publish', true);

        $this->assertContains('blocks', $with->json('data.sections'));

        $without = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/site-bar/context?url=/pricing')->assertOk();

        $this->assertNotContains('blocks', $without->json('data.sections'));
    }

    #[Test]
    public function the_tab_reads_the_draft_with_its_map_and_revision(): void
    {
        $page = $this->pageWithBlocks();
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Draft words']]]]);

        $data = $this->actingAs($this->editor(self::EDITOR), 'cms')
            ->getJson($this->content($page))
            ->assertOk()
            ->json('data');

        $this->assertSame('draft', $data['editing']);
        $this->assertSame('Draft words', $data['tree'][0]['values']['text']);
        $this->assertSame('k1', $data['outline'][0]['key']);
        $this->assertIsString($data['revision']);
    }

    #[Test]
    public function an_edit_goes_into_the_draft_and_comes_back_drawn(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);
        $revision = $this->revision($page, $admin);

        $response = $this->actingAs($admin, 'cms')
            ->postJson($this->content($page).'/edit', [
                'revision' => $revision,
                'locale' => 'en',
                'ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['text' => 'Edited words']]],
            ])
            ->assertOk();

        $page->refresh();

        // The draft, not the columns: the site shows what it showed.
        $this->assertSame('Edited words', $page->draftValues()['blocks'][0]['values']['text']);
        $this->assertSame('Live words', $page->blocks[0]['values']['text']);
        $this->assertStringContainsString('<p>Live words</p>', (string) $this->get('/pricing')->getContent());

        // Drawn from the draft, with its markers, for the page to put in place of the old block.
        $this->assertSame('<!--wx:k1--><p>Edited words</p><!--/wx:k1-->', $response->json('data.html'));
        $this->assertNotSame($revision, $response->json('data.revision'));
        $this->assertSame($this->revision($page, $admin), $response->json('data.revision'));

        // Through the panel's door, by this administrator — what the history and the editor say.
        $version = $page->versions()->first();
        $this->assertInstanceOf(EntityVersion::class, $version);
        $this->assertSame(EntityVersion::SOURCE_PANEL, $version->source);
        $this->assertSame($admin->getKey(), $version->author_id);
    }

    #[Test]
    public function an_edit_on_a_revision_that_moved_is_refused_with_who_moved_it(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);
        $revision = $this->revision($page, $admin);

        // A colleague's save in the panel since the form read the page.
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Theirs']]]], authorId: $admin->getKey());

        $this->actingAs($admin, 'cms')
            ->postJson($this->content($page).'/edit', [
                'revision' => $revision,
                'ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['text' => 'Mine']]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('revision', $this->revision($page, $admin))
            ->assertJsonPath('changed.source', EntityVersion::SOURCE_PANEL);

        $this->assertSame('Theirs', $page->refresh()->draftValues()['blocks'][0]['values']['text']);
    }

    #[Test]
    public function a_localized_field_takes_the_pages_language_and_keeps_the_others(): void
    {
        $this->useLocales('en', 'ru');
        $block = Block::query()->create(['slug' => 'heading', 'title' => 'Heading']);
        $block->saveVersion(['template' => '<h2>{{ $title }}</h2>', 'schema' => [['id' => 'title', 'type' => 'wx-input', 'localized' => true]]]);
        $block->publish();

        $page = $this->page('pricing');
        $page->saveDraft(['blocks' => [['key' => 'h1', 'type' => 'heading', 'values' => ['title' => ['en' => 'Prices', 'ru' => 'Цены']]]]]);
        $page->publish();
        $admin = $this->editor(self::EDITOR);

        $response = $this->actingAs($admin, 'cms')
            ->postJson($this->content($page).'/edit', [
                'locale' => 'ru',
                'ops' => [['op' => 'set', 'key' => 'h1', 'values' => ['title' => 'Стоимость']]],
            ])
            ->assertOk();

        $this->assertSame(['en' => 'Prices', 'ru' => 'Стоимость'], $page->refresh()->draftValues()['blocks'][0]['values']['title']);
        // Drawn in the page's language, whatever the panel speaks.
        $this->assertStringContainsString('<h2>Стоимость</h2>', (string) $response->json('data.html'));
    }

    #[Test]
    public function a_field_the_type_does_not_have_or_a_list_of_blocks_is_refused(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);

        $this->actingAs($admin, 'cms')
            ->postJson($this->content($page).'/edit', ['ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['colour' => 'red']]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ops.0']);

        $this->actingAs($admin, 'cms')
            ->postJson($this->content($page).'/edit', ['ops' => [['op' => 'remove', 'key' => 'k1']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ops.0']);

        $this->actingAs($admin, 'cms')
            ->postJson($this->content($page).'/edit', ['ops' => [['op' => 'set', 'key' => 'nope', 'values' => ['text' => 'x']]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ops.0']);

        $this->assertFalse($page->refresh()->hasDraft());
    }

    #[Test]
    public function the_tab_is_behind_the_records_right_and_reading_blocks(): void
    {
        $page = $this->pageWithBlocks();
        $edit = ['ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['text' => 'x']]]];

        $this->actingAs($this->editor(['pages.view', 'blocks.view']), 'cms')->getJson($this->content($page))->assertForbidden();
        $this->actingAs($this->editor(['pages.view', 'pages.manage']), 'cms')->postJson($this->content($page).'/edit', $edit)->assertForbidden();
        $this->actingAs($this->editor(self::EDITOR), 'cms')->getJson('/api/cms/blocks/content/nothing/1')->assertNotFound();
        $this->actingAs($this->editor(self::EDITOR), 'cms')->getJson('/api/cms/blocks/content/page/999')->assertNotFound();

        $this->assertFalse($page->refresh()->hasDraft());
    }

    #[Test]
    public function the_context_counts_the_changes_and_says_whose_draft_it_is(): void
    {
        $page = $this->pageWithBlocks();
        $colleague = $this->editor(self::EDITOR);
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Theirs']]]], authorId: $colleague->getKey());
        $me = $this->editor(self::EDITOR);

        $this->actingAs($me, 'cms')
            ->getJson('/api/cms/site-bar/context?url=/pricing')
            ->assertJsonPath('data.entity.draft.differs', true)
            ->assertJsonPath('data.entity.draft.changes', 1)
            ->assertJsonPath('data.entity.draft.mine', false);

        $this->actingAs($me, 'cms')->postJson($this->content($page).'/edit', ['ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['text' => 'Mine']]]])->assertOk();

        $this->actingAs($me, 'cms')
            ->getJson('/api/cms/site-bar/context?url=/pricing')
            ->assertJsonPath('data.entity.draft.mine', true);
    }

    #[Test]
    public function publish_puts_the_draft_on_the_site(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);
        $this->actingAs($admin, 'cms')->postJson($this->content($page).'/edit', ['ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['text' => 'New words']]]])->assertOk();

        $this->actingAs($admin, 'cms')
            ->postJson('/api/cms/site-bar/publish', ['type' => 'page', 'id' => $page->getKey(), 'revision' => $this->revision($page, $admin)])
            ->assertOk();

        $page->refresh();
        $this->assertFalse($page->hasDraft());
        $this->assertSame('New words', $page->blocks[0]['values']['text']);
        $version = $page->versions()->first();
        $this->assertInstanceOf(EntityVersion::class, $version);
        $this->assertSame(EntityVersion::KIND_PUBLISHED, $version->kind);
        $this->assertSame(EntityVersion::SOURCE_PANEL, $version->source);
    }

    #[Test]
    public function publish_on_a_revision_that_moved_is_refused(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);
        $held = $this->revision($page, $admin);
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Unseen']]]]);

        $this->actingAs($admin, 'cms')
            ->postJson('/api/cms/site-bar/publish', ['type' => 'page', 'id' => $page->getKey(), 'revision' => $held])
            ->assertStatus(409);

        $this->assertSame('Live words', $page->refresh()->blocks[0]['values']['text']);
    }

    #[Test]
    public function discard_goes_back_to_what_the_site_shows(): void
    {
        $page = $this->pageWithBlocks();
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Dropped']]]]);

        $this->actingAs($this->editor(self::EDITOR), 'cms')
            ->postJson('/api/cms/site-bar/discard', ['type' => 'page', 'id' => $page->getKey()])
            ->assertOk();

        $page->refresh();
        $this->assertFalse($page->hasDraft());
        $this->assertSame('Live words', $page->blocks[0]['values']['text']);
    }

    #[Test]
    public function publish_and_discard_are_behind_the_records_right(): void
    {
        $page = $this->pageWithBlocks();
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Kept']]]]);
        $reader = $this->editor(['pages.view', 'blocks.view']);

        $this->actingAs($reader, 'cms')->postJson('/api/cms/site-bar/publish', ['type' => 'page', 'id' => $page->getKey()])->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson('/api/cms/site-bar/discard', ['type' => 'page', 'id' => $page->getKey()])->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson('/api/cms/site-bar/publish', ['type' => 'page', 'id' => 999])->assertNotFound();

        $this->assertTrue($page->refresh()->hasDraft());
    }

    /** A published page at `/pricing` with one text block, keyed `k1`. */
    private function pageWithBlocks(): Page
    {
        if (Block::query()->where('slug', 'text')->doesntExist()) {
            $this->blockType('text', '<p>{{ $text }}</p>');
        }

        $page = $this->page('pricing');
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Live words']]]]);
        $page->publish();

        return $page->refresh();
    }

    private function content(Page $page): string
    {
        return '/api/cms/blocks/content/page/'.$page->getKey();
    }

    private function revision(Page $page, CmsUser $admin): string
    {
        return (string) $this->actingAs($admin, 'cms')->getJson($this->content($page))->json('data.revision');
    }

    private function signIn(CmsUser $admin): void
    {
        // By name, as a real session would: a page of the site has no `cms` default guard.
        $this->app['auth']->guard('cms')->setUser($admin);
    }
}
