<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Exceptions\BlockNotPublishable;
use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

final class VersionsTest extends TestCase
{
    #[Test]
    public function saving_writes_a_numbered_snapshot_and_points_the_draft_at_it(): void
    {
        $block = Block::query()->create(['slug' => 'hero', 'title' => 'Hero']);

        $first = $block->saveVersion([
            'schema' => [['id' => 'title', 'type' => 'wx-input', 'label' => 'Title']],
            'template' => '<h1>{{ $title }}</h1>',
            'styles' => '.b-hero{}',
            'sample' => ['title' => 'Sample'],
        ], BlockVersion::SOURCE_MCP, 7, 'first cut');

        // Only what changed has to be sent: the rest is carried over from the version being edited.
        $second = $block->saveVersion(['template' => '<h1 class="b-hero">{{ $title }}</h1>']);

        $this->assertSame([1, 2], [$first->number, $second->number]);
        $this->assertSame('.b-hero{}', $second->styles);
        $this->assertSame(['title' => 'Sample'], $second->sample);
        $this->assertSame([['id' => 'title', 'type' => 'wx-input', 'label' => 'Title']], $second->schema);
        $this->assertNull($second->script);
        $this->assertSame(['mcp', 7, 'first cut'], [$first->source, $first->author_id, $first->comment]);
        $this->assertSame(['panel', null, null], [$second->source, $second->author_id, $second->comment]);

        $block->refresh();
        $this->assertSame($second->id, $block->draft_version_id);
        $this->assertNull($block->published_version_id);
        $this->assertSame([2, 1], $block->versions->pluck('number')->all());
    }

    #[Test]
    public function publishing_moves_the_pointer_and_clears_the_draft(): void
    {
        $block = Block::query()->create(['slug' => 'hero', 'title' => 'Hero']);
        $version = $block->saveVersion(['template' => '<h1>{{ $title }}</h1>', 'sample' => ['title' => 'x']]);

        $this->assertSame($version->id, $block->publish()->id);

        $block->refresh();
        $this->assertSame($version->id, $block->published_version_id);
        $this->assertNull($block->draft_version_id);
        $this->assertSame($version->id, $block->currentVersion()?->id);

        // A new draft on top of a published version leaves the site on the published one.
        $draft = $block->saveVersion(['template' => '<h1>next</h1>']);
        $block->refresh();
        $this->assertSame([$version->id, $draft->id], [$block->published_version_id, $block->draft_version_id]);

        // Rolling back is publishing an older version; the draft is somebody else's and stays.
        $block->publish($version);
        $block->refresh();
        $this->assertSame([$version->id, $draft->id], [$block->published_version_id, $block->draft_version_id]);
    }

    #[Test]
    public function there_is_nothing_to_publish_without_a_draft(): void
    {
        $block = Block::query()->create(['slug' => 'hero', 'title' => 'Hero']);

        $this->expectException(BlocksException::class);
        $this->expectExceptionMessage("Block 'hero' has no draft to publish.");

        $block->publish();
    }

    #[Test]
    public function a_version_of_another_block_cannot_be_published_here(): void
    {
        $other = $this->publish('text', '<p></p>');
        $block = Block::query()->create(['slug' => 'hero', 'title' => 'Hero']);

        $this->expectException(BlocksException::class);

        $block->publish($other->publishedVersion);
    }

    #[Test]
    public function a_template_that_fails_on_its_sample_stays_a_draft(): void
    {
        $block = Block::query()->create(['slug' => 'hero', 'title' => 'Hero']);
        $block->saveVersion([
            'template' => "<h1>\n{{ \$title }}\n{{ \$missing->name }}\n</h1>",
            'sample' => ['title' => 'x'],
        ]);

        try {
            $block->publish();
            $this->fail('The version was published although its template throws on the sample.');
        } catch (BlockNotPublishable $refused) {
            $this->assertSame(['hero', 1, 3], [$refused->slug, $refused->version, $refused->templateLine]);
            $this->assertStringContainsString('cannot be published', $refused->getMessage());
            $this->assertStringContainsString('on line 3', $refused->getMessage());
        }

        $block->refresh();
        $this->assertNull($block->published_version_id);
        $this->assertNotNull($block->draft_version_id);
    }

    #[Test]
    public function the_registry_lists_what_an_editor_may_add_in_order(): void
    {
        $this->publish('text', '<p></p>', ['sort' => 20]);
        $this->publish('hero', '<h1></h1>', ['sort' => 10, 'allow' => ['text']]);
        $this->publish('old', '<i></i>', ['sort' => 10, 'is_enabled' => false]);
        $this->publish('aside', '<a></a>', ['sort' => 10]);
        Block::query()->create(['slug' => 'unpublished', 'title' => 'Never'])->saveVersion(['template' => '<u></u>']);

        $types = $this->app->make(BlockTypes::class);

        $this->assertSame(['aside', 'hero', 'text'], array_map(static fn ($type) => $type->slug, $types->all()));

        $hero = $types->find('hero');
        $this->assertNotNull($hero);
        $this->assertTrue($hero->isContainer());
        $this->assertTrue($hero->accepts('text'));
        $this->assertFalse($hero->accepts('hero'));
        $this->assertSame(1, $hero->version);

        $this->assertNotNull($types->find('old'), 'a disabled type still renders where it stands');
        $this->assertNull($types->find('unpublished'));
        $this->assertNull($types->find('nothing'));

        $this->assertSame(1, $types->draft('unpublished')?->version);
    }

    #[Test]
    public function the_registry_forgets_what_it_cached_when_a_type_changes(): void
    {
        $types = $this->app->make(BlockTypes::class);
        $key = (string) $this->app['config']->get('webx-blocks.cache.key');

        $this->assertSame([], $types->all());
        $this->assertTrue(Cache::has($key));

        // Through a closure each time: the assertion would otherwise remember the expression's type.
        $template = static fn (): ?string => $types->find('hero')?->template;

        $hero = $this->publish('hero', '<h1>one</h1>');
        $this->assertSame('<h1>one</h1>', $template());

        $hero->saveVersion(['template' => '<h1>two</h1>']);
        $this->assertSame('<h1>one</h1>', $template(), 'a draft changes nothing on the site');

        $hero->publish();
        $this->assertSame('<h1>two</h1>', $template());

        $hero->update(['is_enabled' => false]);
        $this->assertSame([], $types->all());

        $hero->delete();
        $this->assertNull($types->find('hero'));
        $this->assertSame(0, BlockVersion::query()->count(), 'versions go with their block');
    }

    #[Test]
    public function the_registry_answers_before_the_tables_exist(): void
    {
        Schema::drop('block_versions');
        Schema::drop('blocks');

        $types = $this->app->make(BlockTypes::class);
        $types->forget();

        $this->assertSame([], $types->all());
        $this->assertNull($types->find('hero'));
    }

    #[Test]
    public function a_type_knows_which_of_its_fields_hold_blocks(): void
    {
        $block = $this->publish('section', '<s>@blocks</s>', [], [
            'schema' => [
                ['id' => 'background', 'type' => 'wx-select'],
                ['id' => 'layout', 'type' => 'wx-tabs', 'children' => [
                    ['id' => 'content', 'type' => 'wx-blocks', 'props' => ['allow' => ['text']]],
                ]],
                ['id' => 'aside', 'type' => 'wx-blocks'],
            ],
        ]);

        $type = $this->app->make(BlockTypes::class)->find($block->slug);

        $this->assertSame(['content', 'aside'], $type?->nestedFields());
    }
}
