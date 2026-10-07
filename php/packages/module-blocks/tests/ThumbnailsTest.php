<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Thumbnails;

/**
 * The pictures of the list and the picker: drawn once and kept, thrown away by any change to any
 * type, drawn in a language of the site's content, and saying when they drew nothing.
 */
final class ThumbnailsTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-blocks.thumbnails.cache', true);
    }

    #[Test]
    public function a_thumbnail_is_kept_until_any_type_changes(): void
    {
        $block = $this->publish('hello', '<p data-wx-block="hello">{{ $words }}</p>', [], ['sample' => ['words' => 'First']]);
        $thumbnails = $this->app->make(Thumbnails::class);

        $first = $thumbnails->of($this->typeOf($block));
        $this->assertStringContainsString('First', $first['html']);
        $this->assertFalse($first['empty']);

        // Kept: the same version drawn again is not drawn again, so a change nobody saved through
        // the model — a row edited behind its back — does not show.
        Block::query()->whereKey($block->id)->update(['title' => 'Quietly']);
        $this->assertSame($first, $thumbnails->of($this->typeOf($block)));

        // Any save of any type throws every picture away.
        $this->publish('other', '<p data-wx-block="other">x</p>');
        $block->refresh();
        $block->saveVersion(['sample' => ['words' => 'Second']]);
        $block->publish();

        $this->assertStringContainsString('Second', $thumbnails->of($this->typeOf($block->refresh()))['html']);
    }

    #[Test]
    public function a_block_that_prints_nothing_on_its_sample_says_so(): void
    {
        $block = $this->publish('maybe', '@if ($words)<p data-wx-block="maybe">{{ $words }}</p>@endif', [], ['sample' => ['words' => null]]);

        $this->assertTrue($this->app->make(Thumbnails::class)->of($this->typeOf($block))['empty']);
    }

    #[Test]
    public function the_panels_language_is_not_the_one_a_thumbnail_reads_in(): void
    {
        $block = $this->publish('lang', '<p data-wx-block="lang">{{ app()->getLocale() }}</p>');

        // The administrator reads the panel in a language this site does not publish in.
        $this->app->setLocale('de');

        $thumbnail = $this->app->make(Thumbnails::class)->of($this->typeOf($block));

        $this->assertStringContainsString('<p data-wx-block="lang">en</p>', $thumbnail['html']);
        $this->assertSame('de', $this->app->getLocale(), 'the request goes on in its own language');
    }

    #[Test]
    public function the_list_answers_with_the_thumbnails_and_clear_forgets_them(): void
    {
        $this->publish('hello', '<p data-wx-block="hello">{{ $words }}</p>', [], ['sample' => ['words' => 'Kept']]);

        $this->actingAs($this->editor(), 'cms')
            ->getJson('/api/cms/blocks')
            ->assertOk()
            ->assertJsonPath('data.0.thumbnail.empty', false);

        $this->artisan('webx:blocks:clear')->expectsOutputToContain('Thumbnails forgotten')->assertSuccessful();
    }

    private function typeOf(Block $block): BlockType
    {
        $block->loadMissing('publishedVersion');

        return BlockType::fromModels($block, $block->publishedVersion ?? $block->draftVersion);
    }
}
