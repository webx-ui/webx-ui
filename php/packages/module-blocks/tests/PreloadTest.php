<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Media\MediaServiceProvider;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;

/**
 * A page of picture blocks asks the library once, not once per block: the renderer walks the
 * tree before rendering it and hands every field type what it loads ahead
 * (`PreloadsValues`), nested blocks included.
 */
final class PreloadTest extends TestCase
{
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), MediaServiceProvider::class];
    }

    #[Test]
    public function the_pictures_of_every_block_on_the_page_are_loaded_in_one_query(): void
    {
        $this->publish('figure', '<img src="{{ $image["url"] ?? "" }}" alt="{{ $image["alt"] ?? "" }}">', [], [
            'schema' => [['id' => 'image', 'type' => 'wx-media']],
        ]);
        $this->publish('album', '@foreach($pictures ?? [] as $picture)<img src="{{ $picture["url"] ?? "" }}">@endforeach', [], [
            'schema' => [['id' => 'pictures', 'type' => 'wx-gallery']],
        ]);
        $this->publish('section', '<section>@blocks(\'inner\')</section>', [], [
            'schema' => [['id' => 'inner', 'type' => 'wx-blocks']],
        ]);
        // Cards: a repeater whose items each hold a picture — the shape most theme blocks take.
        $this->publish('cards', '@foreach($cards ?? [] as $card)<img src="{{ $card["image"]["url"] ?? "" }}">@endforeach', [], [
            'schema' => [['id' => 'cards', 'type' => 'wx-repeater', 'children' => [['id' => 'image', 'type' => 'wx-media']]]],
        ]);

        $paths = [];

        for ($i = 1; $i <= 9; $i++) {
            $paths[] = $this->picture("picture-{$i}")->path;
        }

        $blocks = [
            $this->node('figure', ['image' => ['path' => $paths[0], 'alt' => 'One']]),
            $this->node('figure', ['image' => ['path' => $paths[1]]]),
            $this->node('album', ['pictures' => [['path' => $paths[2]], ['path' => $paths[3]]]]),
            $this->node('section', ['inner' => [
                $this->node('figure', ['image' => ['path' => $paths[4]]]),
                $this->node('album', ['pictures' => [['path' => $paths[5]], ['path' => $paths[6]]]]),
            ]]),
            $this->node('cards', ['cards' => [['image' => ['path' => $paths[7]]], ['image' => ['path' => $paths[8]]]]]),
        ];

        $tally = $this->recordQueries(fn (): string => $this->render($blocks));
        $library = array_filter($tally->queries, static fn (array $query): bool => str_contains($query['query'], 'media_files'));

        $this->assertCount(1, $library, $tally->describe());

        $html = $tally->result;

        foreach ($paths as $path) {
            $this->assertStringContainsString($path, $html);
        }

        $this->assertStringContainsString('alt="One"', $html);
    }

    private function picture(string $name): MediaFile
    {
        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        return MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => "media/ab/cd/{$name}.jpg",
            'hash' => substr(md5($name), 0, 32),
            'name' => $name,
            'file_name' => "{$name}.jpg",
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'size' => 2048,
            'width' => 800,
            'height' => 600,
        ]);
    }
}
