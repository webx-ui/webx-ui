<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockBundle;
use WebxUi\Blocks\Rendering\Bundles;
use WebxUi\Pages\Models\Page;

/**
 * The structure of a record's blocks edited from the panel on the site (SB5 of
 * WEBX_UI_SITE_BAR.md): add, duplicate, move, hide, show and remove — the agent's operations,
 * through the bar's own address — into the draft, with the steps the page takes to show it and the
 * styles of a type the page did not have.
 */
final class SiteBarStructureTest extends TestCase
{
    private const EDITOR = ['pages.view', 'pages.manage', 'blocks.view'];

    #[Test]
    public function a_block_added_below_goes_into_the_draft_with_a_key_made_on_the_server(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);

        $data = $this->edit($page, $admin, [['op' => 'add', 'type' => 'text', 'after' => 'k1', 'values' => ['text' => 'Hello']]])
            ->assertOk()
            ->json('data');

        $key = $data['key'];
        $this->assertIsString($key);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $key);

        $page->refresh();
        // The draft, not the columns: the site shows what it showed.
        $this->assertSame(['k1', $key], array_column($page->draftValues()['blocks'], 'key'));
        $this->assertCount(1, $page->blocks);
        $this->assertStringNotContainsString('Hello', (string) $this->get('/pricing')->getContent());

        // One step: the new block, drawn from the draft with its markers, after the one it follows.
        $this->assertSame([[
            'do' => 'place',
            'key' => $key,
            'after' => ['k1'],
            'before' => [],
            'top' => true,
            'html' => "<!--wx:{$key}--><p>Hello</p><!--/wx:{$key}-->",
        ]], $data['steps']);
        $this->assertSame("<!--wx:{$key}--><p>Hello</p><!--/wx:{$key}-->", $data['html']);
    }

    #[Test]
    public function a_block_added_inside_a_container_draws_the_container_again(): void
    {
        $page = $this->pageWithColumns();
        $admin = $this->editor(self::EDITOR);

        $data = $this->edit($page, $admin, [['op' => 'add', 'type' => 'text', 'parent' => 'c1', 'field' => 'items', 'values' => ['text' => 'Second']]])
            ->assertOk()
            ->json('data');

        $key = $data['key'];

        $this->assertCount(1, $data['steps']);
        $this->assertSame('place', $data['steps'][0]['do']);
        $this->assertSame('c1', $data['steps'][0]['key']);
        $this->assertSame("<!--wx:c1--><div class=\"cols\"><!--wx:k2--><p>First</p><!--/wx:k2--><!--wx:{$key}--><p>Second</p><!--/wx:{$key}--></div><!--/wx:c1-->", $data['steps'][0]['html']);
        // The new block is the one to open, not the container drawn for it.
        $this->assertSame($key, $data['key']);
    }

    #[Test]
    public function duplicate_move_hide_show_and_remove_go_into_the_draft_step_by_step(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);

        $copy = $this->edit($page, $admin, [['op' => 'duplicate', 'key' => 'k1']])->assertOk()->json('data.key');
        $this->assertNotSame('k1', $copy);
        $this->assertSame(['k1', $copy], $this->draftKeys($page));

        // Up: the block named moves, whichever of the two the trees would let move.
        $moved = $this->edit($page, $admin, [['op' => 'move', 'key' => $copy, 'before' => 'k1']])->assertOk()->json('data.steps');
        $this->assertSame([$copy, 'k1'], $this->draftKeys($page));
        $this->assertSame('move', $moved[0]['do']);
        $this->assertSame($copy, $moved[0]['key']);
        $this->assertSame(['k1'], $moved[0]['before']);
        $this->assertCount(1, $moved);

        // Hidden: the site does not draw it, so its range goes.
        $hidden = $this->edit($page, $admin, [['op' => 'hide', 'key' => 'k1']])->assertOk()->json('data.steps');
        $this->assertSame([['do' => 'remove', 'key' => 'k1']], $hidden);
        $this->assertTrue($page->refresh()->draftValues()['blocks'][1]['hidden']);

        // Shown again: drawn and put back after its neighbour.
        $shown = $this->edit($page, $admin, [['op' => 'show', 'key' => 'k1']])->assertOk()->json('data.steps');
        $this->assertSame('place', $shown[0]['do']);
        $this->assertSame([$copy], $shown[0]['after']);
        $this->assertArrayNotHasKey('hidden', $page->refresh()->draftValues()['blocks'][1]);

        $removed = $this->edit($page, $admin, [['op' => 'remove', 'key' => $copy]])->assertOk()->json('data.steps');
        $this->assertSame([['do' => 'remove', 'key' => $copy]], $removed);
        $this->assertSame(['k1'], $this->draftKeys($page));

        // All of it in the draft: the site has the one block it had all along.
        $this->assertSame(['k1'], array_column($page->refresh()->blocks, 'key'));
    }

    #[Test]
    public function what_the_container_and_the_type_take_is_held_to(): void
    {
        $page = $this->pageWithColumns();
        $admin = $this->editor(self::EDITOR);
        Block::query()->where('slug', 'columns')->firstOrFail()->update(['max_per_entity' => 1]);
        $this->blockType('quote', '<q>{{ $text }}</q>');

        // A type the container does not take.
        $this->assertRefusedUnder('blocks.', $this->edit($page, $admin, [['op' => 'add', 'type' => 'quote', 'parent' => 'c1', 'field' => 'items']]));

        // A second of a type a page takes one of.
        $this->edit($page, $admin, [['op' => 'duplicate', 'key' => 'c1']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['blocks.columns']);

        // A container full to its `max`.
        $this->edit($page, $admin, [
            ['op' => 'add', 'type' => 'text', 'parent' => 'c1', 'field' => 'items'],
            ['op' => 'add', 'type' => 'text', 'parent' => 'c1', 'field' => 'items'],
        ])->assertStatus(422)->assertJsonValidationErrors(['blocks.c1.items']);

        // A block moved into itself, a block that is not there, a type nobody published, a
        // component that only templates call.
        $this->edit($page, $admin, [['op' => 'move', 'key' => 'c1', 'parent' => 'k2', 'field' => 'items']])->assertStatus(422)->assertJsonValidationErrors(['ops.0']);
        $this->edit($page, $admin, [['op' => 'remove', 'key' => 'gone']])->assertStatus(422)->assertJsonValidationErrors(['ops.0']);
        $this->edit($page, $admin, [['op' => 'add', 'type' => 'nothing']])->assertStatus(422)->assertJsonValidationErrors(['ops.0']);
        Block::query()->create(['slug' => 'draft-only', 'title' => 'Draft only'])->saveVersion(['template' => '<p>x</p>', 'schema' => []]);
        $this->edit($page, $admin, [['op' => 'add', 'type' => 'draft-only']])->assertStatus(422)->assertJsonValidationErrors(['ops.0']);
        Block::query()->where('slug', 'quote')->firstOrFail()->update(['kind' => Block::KIND_COMPONENT]);
        $this->edit($page, $admin, [['op' => 'add', 'type' => 'quote']])->assertStatus(422)->assertJsonValidationErrors(['ops.0']);

        $this->assertFalse($page->refresh()->hasDraft());
    }

    #[Test]
    public function a_structural_edit_on_a_revision_that_moved_is_refused(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);
        $revision = $this->revision($page, $admin);
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Theirs']]]], authorId: $admin->getKey());

        $this->actingAs($admin, 'cms')
            ->postJson($this->content($page).'/edit', ['revision' => $revision, 'ops' => [['op' => 'remove', 'key' => 'k1']]])
            ->assertStatus(409);

        $this->assertSame(['k1'], $this->draftKeys($page));
    }

    #[Test]
    public function structure_is_behind_the_records_right(): void
    {
        $page = $this->pageWithBlocks();

        $this->edit($page, $this->editor(['pages.view', 'blocks.view']), [['op' => 'add', 'type' => 'text']])->assertForbidden();
        $this->edit($page, $this->editor(['pages.view', 'pages.manage']), [['op' => 'remove', 'key' => 'k1']])->assertForbidden();

        $this->assertFalse($page->refresh()->hasDraft());
    }

    #[Test]
    public function the_answer_brings_the_styles_of_a_type_the_page_did_not_have(): void
    {
        $page = $this->pageWithBlocks();
        Block::query()->where('slug', 'text')->firstOrFail()->saveVersion(['template' => '<p>{{ $text }}</p>', 'schema' => [['id' => 'text', 'type' => 'wx-input']], 'styles' => 'p { margin: 0; }']);
        Block::query()->where('slug', 'text')->firstOrFail()->publish();
        $promo = Block::query()->create(['slug' => 'promo', 'title' => 'Promo']);
        $promo->saveVersion(['template' => '<aside class="promo">{{ $text }}</aside>', 'schema' => [['id' => 'text', 'type' => 'wx-input']], 'styles' => '.promo { color: red; }', 'script' => 'el.dataset.on = "1"']);
        $promo->publish();
        $admin = $this->editor(self::EDITOR);

        // The page's own set — what it printed — has the text block's styles only.
        $have = $this->app->make(Bundles::class)->forSlugs(['text']);
        $this->assertInstanceOf(BlockBundle::class, $have);

        $assets = $this->edit($page, $admin, [['op' => 'add', 'type' => 'promo', 'values' => ['text' => 'Sale']]], [$have->hash])
            ->assertOk()
            ->json('data.assets');

        $bundle = BlockBundle::query()->findOrFail($assets['hash']);
        $this->assertSame(['promo'], array_keys($bundle->versions()));
        $this->assertStringContainsString('.promo { color: red; }', $bundle->css);
        $this->assertStringContainsString("/blocks/{$bundle->hash}.css", (string) $assets['css']);
        $this->assertStringContainsString("/blocks/{$bundle->hash}.js", (string) $assets['js']);

        // A type the page has brings nothing.
        $this->edit($page, $admin, [['op' => 'add', 'type' => 'text', 'values' => ['text' => 'More']]], [$have->hash])
            ->assertOk()
            ->assertJsonPath('data.assets', null);
    }

    #[Test]
    public function a_visitor_gets_the_page_byte_for_byte_after_a_structural_edit(): void
    {
        $page = $this->pageWithBlocks();
        $this->edit($page, $this->editor(self::EDITOR), [['op' => 'add', 'type' => 'text', 'values' => ['text' => 'Added']]])->assertOk();
        $this->app['auth']->forgetGuards();

        $with = $this->get('/pricing')->assertOk();
        $this->app['config']->set('webx-admin.site_bar.enabled', false);
        $without = $this->get('/pricing')->assertOk();

        $this->assertSame($without->getContent(), $with->getContent());
        $this->assertStringNotContainsString('Added', (string) $with->getContent());
        $this->assertStringNotContainsString('<!--wx', (string) $with->getContent());
    }

    #[Test]
    public function the_context_links_the_draft_to_its_preview(): void
    {
        $page = $this->pageWithBlocks();
        $admin = $this->editor(self::EDITOR);

        $this->actingAs($admin, 'cms')->getJson('/api/cms/site-bar/context?url=/pricing')->assertJsonPath('data.entity.preview', null);

        $this->edit($page, $admin, [['op' => 'hide', 'key' => 'k1']])->assertOk();

        $preview = $this->actingAs($admin, 'cms')->getJson('/api/cms/site-bar/context?url=/pricing')->json('data.entity.preview');
        $this->assertIsString($preview);
        $this->assertStringContainsString('/_preview/page/'.$page->getKey().'?token=', $preview);
    }

    /** A published page at `/pricing` with one text block, keyed `k1`. */
    private function pageWithBlocks(): Page
    {
        $this->blockType('text', '<p>{{ $text }}</p>');

        $page = $this->page('pricing');
        $page->saveDraft(['blocks' => [['key' => 'k1', 'type' => 'text', 'values' => ['text' => 'Live words']]]]);
        $page->publish();

        return $page->refresh();
    }

    /** A page with a container `c1` that takes text blocks, two at most, holding `k2`. */
    private function pageWithColumns(): Page
    {
        $this->blockType('text', '<p>{{ $text }}</p>');
        $columns = Block::query()->create(['slug' => 'columns', 'title' => 'Columns', 'allow' => ['text']]);
        $columns->saveVersion(['template' => '<div class="cols">@blocks(\'items\')</div>', 'schema' => [['id' => 'items', 'type' => 'wx-blocks', 'props' => ['max' => 2]]]]);
        $columns->publish();

        $page = $this->page('pricing');
        $page->saveDraft(['blocks' => [['key' => 'c1', 'type' => 'columns', 'values' => ['items' => [['key' => 'k2', 'type' => 'text', 'values' => ['text' => 'First']]]]]]]);
        $page->publish();

        return $page->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     * @param  list<string>|null  $bundles
     * @return TestResponse<Response>
     */
    private function edit(Page $page, CmsUser $admin, array $ops, ?array $bundles = null): TestResponse
    {
        return $this->actingAs($admin, 'cms')->postJson($this->content($page).'/edit', array_filter([
            'url' => '/pricing',
            'ops' => $ops,
            'bundles' => $bundles,
        ], static fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertRefusedUnder(string $prefix, TestResponse $response): void
    {
        $response->assertStatus(422);
        $under = array_filter(array_keys((array) $response->json('errors')), static fn (string $key): bool => str_starts_with($key, $prefix));

        $this->assertNotSame([], $under, (string) $response->getContent());
    }

    /** @return list<string> */
    private function draftKeys(Page $page): array
    {
        // A draft that comes back to what the site shows is no draft: the tree is then the column's.
        $page->refresh();

        return array_column($page->draftValues()['blocks'] ?? $page->blocks, 'key');
    }

    private function content(Page $page): string
    {
        return '/api/cms/blocks/content/page/'.$page->getKey();
    }

    private function revision(Page $page, CmsUser $admin): string
    {
        return (string) $this->actingAs($admin, 'cms')->getJson($this->content($page))->json('data.revision');
    }
}
