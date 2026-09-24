<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Tests\Fixtures\Page;

/**
 * The section's API: what an editor can do to a type, and what is refused.
 */
final class PanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The one entity of these tests, so the section knows where a type stands.
        $this->app['config']->set('webx-blocks.entities', [Page::class]);
    }

    #[Test]
    public function the_section_is_in_the_manifest_with_what_the_front_end_needs(): void
    {
        $this->app['config']->set('webx-blocks.provides', ['swiper']);

        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        $module = $this->firstWhere($response->json('data.modules'), 'id', 'blocks');

        $this->assertNotNull($module);
        $this->assertSame(['blocks.view', 'blocks.manage'], $module['permissions']);
        $this->assertSame(['content', 'layout', 'media'], $module['meta']['groups']);
        $this->assertTrue($module['meta']['editing']);
        $this->assertSame(['swiper'], $module['meta']['provides']);
        $this->assertNull($module['meta']['stage']);
    }

    #[Test]
    public function the_stage_is_named_to_the_thumbnails_once_the_site_has_a_layout(): void
    {
        $this->app['config']->set('webx-blocks.layout', 'layout');

        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        $module = $this->firstWhere($response->json('data.modules'), 'id', 'blocks');

        $this->assertNotNull($module);
        $this->assertSame('/_preview/block-stage', $module['meta']['stage']);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $this->postJson($this->api(''), ['slug' => 'hero', 'title' => 'Hero'])->assertUnauthorized();

        $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->postJson($this->api(''), ['slug' => 'hero', 'title' => 'Hero'])
            ->assertForbidden();

        $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->getJson($this->api(''))
            ->assertOk();
    }

    #[Test]
    public function a_new_type_starts_as_draft_version_one(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(''), ['slug' => 'hero', 'title' => 'Hero', 'group' => 'layout'])
            ->assertCreated();

        $this->assertSame('hero', $response->json('data.slug'));
        $this->assertSame(1, $response->json('data.draft.number'));
        $this->assertNull($response->json('data.published'));
        $this->assertSame('panel', $response->json('data.draft.source'));
        $this->assertSame('Editor', $response->json('data.draft.author'));
        $this->assertSame([], $response->json('data.content.schema'));
        $this->assertSame(0, $response->json('data.usage_count'));
    }

    #[Test]
    public function the_slug_is_checked_and_unique(): void
    {
        Block::query()->create(['slug' => 'hero', 'title' => 'Hero']);

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson($this->api(''), ['slug' => 'hero', 'title' => 'Again'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($editor, 'cms')
            ->postJson($this->api(''), ['slug' => 'Hero Block', 'title' => 'Again'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');

        $this->actingAs($editor, 'cms')
            ->postJson($this->api(''), ['slug' => 'promo', 'title' => 'Promo', 'group' => 'nonsense'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('group');
    }

    #[Test]
    public function saving_content_writes_a_version_and_saving_nothing_new_does_not(): void
    {
        $block = $this->draft('hero');
        $editor = $this->editor();

        $content = [
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'label' => 'Title']],
            'template' => '<section data-wx-block="hero" class="b-hero">{{ $title }}</section>',
            'styles' => '.b-hero { padding: 1rem }',
            'sample' => ['title' => 'Welcome'],
        ];

        $response = $this->actingAs($editor, 'cms')
            ->putJson($this->api($block->id), ['content' => $content, 'comment' => 'first draft'])
            ->assertOk();

        $this->assertSame(2, $response->json('data.draft.number'));
        $this->assertSame('first draft', $response->json('data.draft.comment'));
        $this->assertSame([], $response->json('data.warnings'));
        $this->assertStringContainsString('Welcome', $response->json('data.thumbnail.html'));

        // The same content again: the settings changed, the version did not.
        $again = $this->actingAs($editor, 'cms')
            ->putJson($this->api($block->id), ['title' => 'Cover', 'content' => $content])
            ->assertOk();

        $this->assertSame('Cover', $again->json('data.title'));
        $this->assertSame(2, $again->json('data.draft.number'));
        $this->assertSame(2, $block->versions()->count());
    }

    #[Test]
    public function the_lints_come_back_with_the_saved_version(): void
    {
        $block = $this->draft('hero');

        $response = $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($block->id), ['content' => [
                'template' => '<section class="b-hero">Hi</section>',
                'styles' => "h2 { margin: 0 }\n.other { color: red }\n@media (max-width: 600px) { .b-hero { padding: 0 } }",
            ]])
            ->assertOk();

        $codes = array_column($response->json('data.warnings'), 'code');

        $this->assertSame(['no-marker', 'stray-selectors', 'bare-selectors', 'media-query'], $codes);
    }

    #[Test]
    public function a_schema_that_is_not_a_list_of_nodes_is_refused(): void
    {
        $block = $this->draft('hero');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($block->id), ['content' => ['schema' => [['type' => 'wx-input']]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content.schema');
    }

    #[Test]
    public function publishing_moves_the_pointer_and_refuses_a_template_that_fails(): void
    {
        $block = $this->draft('hero', [
            'schema' => [['id' => 'title', 'type' => 'wx-input']],
            'template' => '<section data-wx-block="hero">{{ $title }}</section>',
            'sample' => ['title' => 'Welcome'],
        ]);

        $editor = $this->editor();

        $published = $this->actingAs($editor, 'cms')->postJson($this->api("{$block->id}/publish"))->assertOk();

        $this->assertSame(1, $published->json('data.published.number'));
        $this->assertNull($published->json('data.draft'));

        // No draft to publish now.
        $this->actingAs($editor, 'cms')->postJson($this->api("{$block->id}/publish"))->assertStatus(409);

        // A draft that throws on its sample stays a draft, and the line is named.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api($block->id), ['content' => [
                'template' => "<section data-wx-block=\"hero\">\n{{ \$title }}\n{{ \$missing }}\n</section>",
            ]])
            ->assertOk();

        $refused = $this->actingAs($editor, 'cms')->postJson($this->api("{$block->id}/publish"))->assertStatus(422);

        $this->assertArrayHasKey('template', $refused->json('errors'));
        $this->assertSame(3, $refused->json('line'));
        $this->assertNull($refused->json('entity'));
        $this->assertSame(1, $block->refresh()->publishedVersion?->number);
    }

    #[Test]
    public function publishing_is_refused_when_a_page_would_break(): void
    {
        $block = $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');

        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => [$this->node('hero', ['title' => 'Hi', 'count' => 'many'])]]);

        // The next version does arithmetic on a field one page filled with a word.
        $block->saveVersion([
            'schema' => [['id' => 'title', 'type' => 'wx-input'], ['id' => 'count', 'type' => 'wx-input']],
            'template' => '<section data-wx-block="hero">{{ $title }} {{ $count === null ? 0 : intdiv(10, (int) $count) }}</section>',
            'sample' => ['title' => 'Hi', 'count' => 2],
        ]);

        $refused = $this->actingAs($this->editor(), 'cms')->postJson($this->api("{$block->id}/publish"))->assertStatus(422);

        $this->assertSame('Home', $refused->json('entity.title'));
        $this->assertSame($page->id, $refused->json('entity.id'));
    }

    #[Test]
    public function rendering_draws_the_block_on_the_values_sent_with_the_markers(): void
    {
        $block = $this->publish('hero', '<section data-wx-block="hero" class="b-hero">{{ $title }}</section>', content: [
            'styles' => '.b-hero { color: red }',
            'script' => 'el.dataset.ready = "1"',
        ]);

        $response = $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->postJson($this->api("{$block->id}/render"), ['values' => ['title' => 'Typed'], 'key' => 'k1'])
            ->assertOk();

        $this->assertSame('<!--wx:k1--><section data-wx-block="hero" class="b-hero">Typed</section><!--/wx:k1-->', $response->json('data.html'));
        $this->assertSame('.b-hero { color: red }', $response->json('data.styles'));
        $this->assertStringContainsString('webx.block("hero"', $response->json('data.script'));
        $this->assertStringContainsString('runtime.js', $response->json('data.runtime'));
    }

    #[Test]
    public function rendering_an_unsaved_template_needs_the_right_to_save_one(): void
    {
        $block = $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');

        $unsaved = ['content' => ['template' => '<p data-wx-block="hero">{{ strtoupper($title) }}</p>'], 'values' => ['title' => 'x']];

        $this->actingAs($this->editor(['blocks.view']), 'cms')
            ->postJson($this->api("{$block->id}/render"), $unsaved)
            ->assertForbidden();

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("{$block->id}/render"), $unsaved)
            ->assertOk();

        $this->assertStringContainsString('<p data-wx-block="hero">X</p>', $response->json('data.html'));

        // A failure is a notice in the block's place, not a 500.
        $broken = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api("{$block->id}/render"), ['content' => ['template' => '{{ $title->nothing() }}'], 'values' => ['title' => 'x']])
            ->assertOk();

        $this->assertStringContainsString('wx-block-error', $broken->json('data.html'));
    }

    #[Test]
    public function the_catalog_lists_published_types_only_and_the_list_counts_pages(): void
    {
        $hero = $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');
        $this->draft('promo');

        $page = Page::query()->create(['title' => 'Home', 'blocks' => [$this->node('hero', ['title' => 'Hi'])]]);
        $page->saveDraft(['blocks' => [$this->node('hero', ['title' => 'Hi']), $this->node('hero', ['title' => 'Twice'])]]);
        Page::query()->create(['title' => 'About', 'blocks' => [$this->node('hero', ['title' => 'Hi'])]]);

        $editor = $this->editor(['blocks.view']);

        $catalog = $this->actingAs($editor, 'cms')->getJson($this->api('catalog'))->assertOk();

        $this->assertSame(['hero'], array_column($catalog->json('data'), 'slug'));
        $this->assertArrayHasKey('schema', $catalog->json('data.0.content'));

        $list = $this->actingAs($editor, 'cms')->getJson($this->api(''))->assertOk();

        $this->assertSame(['hero', 'promo'], array_column($list->json('data'), 'slug'));
        $this->assertSame(2, $this->firstWhere($list->json('data'), 'slug', 'hero')['usage_count'] ?? null);
        $this->assertArrayNotHasKey('content', $list->json('data.0'));

        $usage = $this->actingAs($editor, 'cms')->getJson($this->api("{$hero->id}/usage"))->assertOk();

        $this->assertSame(['Home', 'About'], array_column($usage->json('data'), 'title'));
    }

    #[Test]
    public function the_history_lists_versions_and_restores_one_as_a_new_draft(): void
    {
        $block = $this->publish('hero', '<section data-wx-block="hero">one</section>');
        $block->saveVersion(['template' => '<section data-wx-block="hero">two</section>']);

        $editor = $this->editor();

        $versions = $this->actingAs($editor, 'cms')->getJson($this->api("{$block->id}/versions"))->assertOk();

        $this->assertSame([2, 1], array_column($versions->json('data'), 'number'));
        $this->assertArrayNotHasKey('content', $versions->json('data.0'));

        $one = $this->actingAs($editor, 'cms')->getJson($this->api("{$block->id}/versions/1"))->assertOk();

        $this->assertStringContainsString('one', $one->json('data.content.template'));

        $restored = $this->actingAs($editor, 'cms')->postJson($this->api("{$block->id}/versions/1/restore"))->assertOk();

        $this->assertSame(3, $restored->json('data.draft.number'));
        $this->assertSame(1, $restored->json('data.published.number'));
        $this->assertStringContainsString('one', $restored->json('data.content.template'));

        $this->actingAs($editor, 'cms')->getJson($this->api("{$block->id}/versions/9"))->assertNotFound();
    }

    #[Test]
    public function a_type_on_a_page_cannot_be_deleted(): void
    {
        $block = $this->publish('hero', '<section data-wx-block="hero">x</section>');
        $spare = $this->draft('spare');
        Page::query()->create(['title' => 'Home', 'blocks' => [$this->node('hero')]]);

        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->deleteJson($this->api($block->id))->assertStatus(409);
        $this->actingAs($editor, 'cms')->deleteJson($this->api($spare->id))->assertNoContent();

        $this->assertNull(Block::query()->find($spare->id));
    }

    #[Test]
    public function editing_switched_off_makes_the_section_read_only(): void
    {
        $this->app['config']->set('webx-blocks.editing', false);

        $block = $this->publish('hero', '<section data-wx-block="hero">{{ $title }}</section>');
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->getJson($this->api(''))->assertOk();
        $this->actingAs($editor, 'cms')->putJson($this->api($block->id), ['title' => 'x'])->assertForbidden();
        $this->actingAs($editor, 'cms')->postJson($this->api("{$block->id}/publish"))->assertForbidden();

        // What is stored still renders; what is not does not.
        $this->actingAs($editor, 'cms')->postJson($this->api("{$block->id}/render"), ['values' => ['title' => 'x']])->assertOk();
        $this->actingAs($editor, 'cms')->postJson($this->api("{$block->id}/render"), ['content' => ['template' => 'x']])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function draft(string $slug, array $content = []): Block
    {
        $block = Block::query()->create(['slug' => $slug, 'title' => ucfirst($slug)]);
        $block->saveVersion($content);

        return $block->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstWhere(mixed $rows, string $key, string $value): ?array
    {
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && ($row[$key] ?? null) === $value) {
                return $row;
            }
        }

        return null;
    }

    private function api(string|int $path): string
    {
        return rtrim('/api/cms/blocks/'.$path, '/');
    }
}
