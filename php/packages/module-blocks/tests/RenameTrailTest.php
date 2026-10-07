<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;
use WebxUi\Blocks\Panel\Lints;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * What a rename leaves behind, and the marker a rename rewrites. The editor's save sent its stale
 * content with the new slug and wrote the old template as the next draft; a page restored from
 * before a rename brought back a slug nobody had and published it into a gap; a marker spelled
 * «Quote» on a type called `quote` was said by nobody.
 */
final class RenameTrailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);
    }

    #[Test]
    public function the_editors_form_sent_with_a_new_slug_writes_no_stale_draft(): void
    {
        $template = '<q class="b-quote" data-wx-block="quote">{{ $words }}</q>';
        $styles = '.b-quote { color: red; }';
        $quote = $this->publish('quote', $template, [], ['styles' => $styles]);

        // What the editor holds: the content it opened with, sent beside the new identifier.
        $answer = $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/blocks/'.$quote->id, ['slug' => 'citation', 'content' => ['template' => $template, 'styles' => $styles]])
            ->assertOk();

        $renamed = Block::query()->where('slug', 'citation')->firstOrFail();

        $this->assertNull($renamed->draft_version_id, "no draft after the rename's own version");
        $this->assertStringContainsString('data-wx-block="citation"', (string) $answer->json('data.content.template'));
        $this->assertSame(['quote'], $renamed->former_slugs);

        // The editor's own change in the same save is kept, carried to the new slug.
        $this->actingAs($this->editor(), 'cms')
            ->putJson('/api/cms/blocks/'.$quote->id, ['slug' => 'saying', 'content' => [
                'template' => '<blockquote class="b-citation" data-wx-block="citation">{{ $words }}</blockquote>',
            ]])
            ->assertOk();

        $draft = Block::query()->where('slug', 'saying')->firstOrFail()->draftVersion;
        $this->assertInstanceOf(BlockVersion::class, $draft);

        $this->assertSame('<blockquote class="b-saying" data-wx-block="saying">{{ $words }}</blockquote>', $draft->template);
        $this->assertSame([], array_values(array_filter(
            Lints::check('saying', (string) $draft->template, (string) $draft->styles),
            static fn (array $lint): bool => in_array($lint['code'], ['stray-selectors', 'marker-slug'], true),
        )));
    }

    #[Test]
    public function a_page_restored_from_before_a_rename_follows_the_type(): void
    {
        $quote = $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');
        $page = Page::query()->create(['title' => 'Test']);
        $page->saveDraft(['blocks' => [$this->node('quote', ['words' => 'Old'], 'q1')]]);
        $page->publish();
        $old = $page->publishedVersions()->firstOrFail();

        $this->actingAs($this->editor(), 'cms')->putJson('/api/cms/blocks/'.$quote->id, ['slug' => 'citation'])->assertOk();
        $this->actingAs($this->editor(), 'cms')->putJson('/api/cms/blocks/'.$quote->id, ['slug' => 'saying'])->assertOk();

        $this->assertSame(['quote', 'citation'], Block::query()->findOrFail($quote->id)->former_slugs);

        // The page has moved on since: the restored version is a draft of its own.
        $page->refresh()->saveDraft(['blocks' => [$this->node('saying', ['words' => 'New'], 'q1')]]);
        $page->publish();
        $this->assertSame(['renamed' => ['quote' => 'saying'], 'unknown' => []], $page->refresh()->restoreReport($old->payload));

        $page->restoreVersion($old);

        $this->assertSame('saying', $page->refresh()->draft['blocks'][0]['type']);
        $this->assertSame([], $page->publishProblems());
    }

    #[Test]
    public function a_block_of_a_type_nobody_has_is_not_published(): void
    {
        $this->publish('quote', '<q data-wx-block="quote">{{ $words }}</q>');
        $page = Page::query()->create(['title' => 'Test']);

        // A draft written by something that wrote the column itself, or restored from before a
        // type was deleted.
        $page->saveDraft(['blocks' => [$this->node('quote', ['words' => 'Kept'], 'q1'), $this->node('gone', [], 'g1')]]);

        $this->assertCount(1, $page->publishProblems());
        $this->assertStringContainsString('gone', $page->publishProblems()[0]);

        try {
            $page->publish();
            $this->fail('published a block of a type nobody has');
        } catch (ValidationException $refused) {
            $this->assertArrayHasKey('blocks.g1', $refused->errors());
        }

        $this->assertNull($page->refresh()->published_at);
    }

    #[Test]
    public function a_marker_that_is_not_the_slug_is_said_and_not_published(): void
    {
        $lints = Lints::check('quote', '<q data-wx-block="Quote">x</q>', '');

        $this->assertContains('marker-slug', array_column($lints, 'code'));
        $this->assertNotContains('marker-slug', array_column(Lints::check('quote', '<q data-wx-block="quote">x</q>', ''), 'code'));
        $this->assertNotContains('marker-slug', array_column(Lints::check('quote', '<q data-wx-block="{{ $block }}">x</q>', ''), 'code'));

        $quote = Block::query()->create(['slug' => 'quote', 'title' => 'Quote']);
        $quote->saveVersion(['template' => '<q data-wx-block="Quote">{{ $words }}</q>', 'schema' => [['id' => 'words', 'type' => 'wx-input']]]);

        $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/blocks/'.$quote->id.'/publish')
            ->assertStatus(422)
            ->assertJsonPath('errors.template.0', fn (string $line): bool => str_contains($line, 'Quote'));

        $this->agent('publish', ['slug' => 'quote'])->assertHasErrors(['data-wx-block="Quote"']);
        $this->assertNull($quote->refresh()->published_version_id);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }
}
