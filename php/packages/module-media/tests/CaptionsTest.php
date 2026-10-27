<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Blocks\Rendering\TemplateCompiler;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Routing\RoutingServiceProvider;

/**
 * The words beside a picture, on a page that is being read in one language.
 *
 * `alt` and `title` are written per language — the field gives each of them a language switcher
 * — and until this they reached a template as the whole map. `{{ $picture['alt'] }}` is then
 * Blade printing an array, which it refuses to do; the renderer catches that and the block
 * disappears from the page rather than looking wrong.
 *
 * Which is why this renders a real block rather than calling the field type: what the fix is
 * about is a template, and a test of the resolve alone would have stayed green while the page
 * stayed empty.
 *
 * Blocks are a dev dependency here for exactly that reason, and only here — the library does
 * not know they exist.
 */
final class CaptionsTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            RoutingServiceProvider::class,
            McpServiceProvider::class,
            BlocksServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-localization.locales', [
            ['code' => 'en', 'default' => true],
            ['code' => 'ru'],
            ['code' => 'uk'],
        ]);
    }

    #[Test]
    public function a_picture_reaches_the_template_with_its_caption_in_one_language(): void
    {
        $file = $this->file();

        $this->publish('figure', '<figure><img src="{{ $picture[\'url\'] }}" alt="{{ $picture[\'alt\'] }}"></figure>', 'wx-media');

        $node = $this->node('figure', [
            'picture' => ['path' => $file->path, 'alt' => ['en' => 'A cat', 'ru' => 'Кот']],
        ]);

        $this->app->setLocale('ru');
        $this->assertStringContainsString('alt="Кот"', $this->render([$node]));

        $this->app->setLocale('en');
        $this->assertStringContainsString('alt="A cat"', $this->render([$node]));
    }

    #[Test]
    public function a_language_nobody_wrote_the_caption_in_falls_back(): void
    {
        // The chain every localized value is read through: the one asked for, the site's
        // default, its fallback. A picture captioned in one language out of three keeps the
        // caption it has rather than losing it on the other two pages.
        $file = $this->file();

        $this->publish('figure', '<img alt="{{ $picture[\'alt\'] }}">', 'wx-media');

        $this->app->setLocale('uk');

        $html = $this->render([
            $this->node('figure', [
                'picture' => ['path' => $file->path, 'alt' => ['en' => 'A cat', 'uk' => '']],
            ]),
        ]);

        $this->assertStringContainsString('alt="A cat"', $html);
    }

    #[Test]
    public function the_other_three_kinds_of_field_read_the_same_way(): void
    {
        // All four hold the same element, so all four had the same hole. A gallery is the one
        // where it would have been noticed last: the page keeps its pictures and loses only
        // the words that describe them.
        $file = $this->file();

        $this->publish('one-file', '{{ $document[\'title\'] }}', 'wx-file', 'document');
        $this->publish('gallery', '@foreach ($shots as $shot){{ $shot[\'alt\'] }}@endforeach', 'wx-gallery', 'shots');
        $this->publish('downloads', '@foreach ($papers as $paper){{ $paper[\'title\'] }}@endforeach', 'wx-files', 'papers');

        $this->app->setLocale('ru');

        $one = ['path' => $file->path, 'alt' => ['en' => 'A cat', 'ru' => 'Кот'], 'title' => ['en' => 'Terms', 'ru' => 'Условия']];

        $this->assertSame('Условия', $this->render([$this->node('one-file', ['document' => $one])]));
        $this->assertSame('Кот', $this->render([$this->node('gallery', ['shots' => [$one]])]));
        $this->assertSame('Условия', $this->render([$this->node('downloads', ['papers' => [$one]])]));
    }

    #[Test]
    public function a_caption_that_was_never_a_map_is_left_alone(): void
    {
        // What a single-language site wrote before it had a second language, and what an agent
        // writing through a tool has no reason to send as a map.
        $file = $this->file();

        $this->publish('figure', '<img alt="{{ $picture[\'alt\'] }}">', 'wx-media');

        $html = $this->render([
            $this->node('figure', ['picture' => ['path' => $file->path, 'alt' => 'A cat']]),
        ]);

        $this->assertStringContainsString('alt="A cat"', $html);
    }

    /**
     * The library's alt is what a picture says wherever the place using it wrote none: on the
     * client's site every hero printed `alt=""`, because the only alt there was was the field's.
     * Rendered with the theme's own `hero`, whose template had the empty alt written in.
     */
    #[Test]
    public function the_files_alt_is_printed_where_the_block_wrote_none(): void
    {
        $file = $this->file(['alt' => ['en' => 'A sofa by the window', 'ru' => 'Диван у окна']]);
        $this->publishThemeBlock('hero');

        $node = $this->node('hero', [
            'title' => ['en' => 'Sofas'],
            'tone' => 'image',
            'image' => ['path' => $file->path],
        ]);

        $this->assertStringContainsString('alt="A sofa by the window"', $this->render([$node]));

        $this->app->setLocale('ru');
        $this->assertStringContainsString('alt="Диван у окна"', $this->render([$node]));

        // The block's own words win: one picture, two places, two captions.
        $own = $this->node('hero', [
            'title' => ['en' => 'Sofas'],
            'tone' => 'image',
            'image' => ['path' => $file->path, 'alt' => ['en' => 'Our grey sofa', 'ru' => 'Наш серый диван']],
        ]);

        $this->assertStringContainsString('alt="Наш серый диван"', $this->render([$own]));
    }

    #[Test]
    public function a_picture_nobody_described_stays_decorative(): void
    {
        $file = $this->file();
        $this->publishThemeBlock('hero');

        $html = $this->render([$this->node('hero', ['title' => ['en' => 'Sofas'], 'tone' => 'image', 'image' => ['path' => $file->path]])]);

        $this->assertStringContainsString('alt=""', $html);
    }

    /** A block type of the default theme, published as the theme ships it. */
    private function publishThemeBlock(string $slug): void
    {
        /** @var array{template: string, schema: list<array<string, mixed>>, sample: array<string, mixed>, title: string} $type */
        $type = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/theme-default/blocks/'.$slug.'.json'), true, flags: JSON_THROW_ON_ERROR);

        $block = Block::query()->create(['slug' => $slug, 'title' => $type['title']]);
        $block->saveVersion(['template' => $type['template'], 'schema' => $type['schema'], 'sample' => $type['sample']]);
        $block->publish();
    }

    /**
     * A published block type with one field of the given kind.
     *
     * The sample is not decoration: publishing renders the template against it, so a type whose
     * template reaches into a picture cannot be published without one. A list samples as empty,
     * which is all a `@foreach` needs.
     */
    private function publish(string $slug, string $template, string $type, string $field = 'picture'): void
    {
        $block = Block::query()->create(['slug' => $slug, 'title' => ucfirst($slug)]);

        $block->saveVersion([
            'template' => $template,
            'schema' => [['id' => $field, 'type' => $type]],
            'sample' => [$field => in_array($type, ['wx-gallery', 'wx-files'], true)
                ? []
                : ['path' => 'media/ab/cd/one.jpg', 'alt' => '', 'title' => '']],
        ]);

        $block->publish();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function node(string $type, array $values): array
    {
        static $count = 0;
        $count++;

        return ['key' => "k{$count}", 'type' => $type, 'values' => $values];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function render(array $blocks): string
    {
        return (string) $this->app->make(Renderer::class)->render($blocks);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function file(array $attributes = []): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create($attributes + [
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => 'media/ab/cd/one.jpg',
            'hash' => str_repeat('a', 32),
            'name' => 'A picture',
            'file_name' => 'a-picture.jpg',
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
        ]);
    }

    protected function tearDown(): void
    {
        // One compiled file per version, and every test starts its versions at 1: a file left
        // by the previous test would be served for a template it was not compiled from.
        File::deleteDirectory($this->app->make(TemplateCompiler::class)->directory());

        parent::tearDown();
    }
}
