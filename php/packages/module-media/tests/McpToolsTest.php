<?php

declare(strict_types=1);

namespace WebxUi\Media\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Media\MediaModule;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Storage\FileStore;

final class McpToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[Test]
    public function the_module_offers_the_tools_the_specification_names(): void
    {
        $names = array_map(static fn ($tool): string => $tool->name, (new MediaModule)->mcpTools());

        $this->assertSame([
            'list_directories',
            'list_files',
            'search_files',
            'get_file',
            'create_directory',
            'rename_file',
            'move_files',
            'upload_from_url',
            'delete_files',
        ], $names);
    }

    #[Test]
    public function there_is_no_tool_that_deletes_a_folder(): void
    {
        $names = array_map(static fn ($tool): string => $tool->name, (new MediaModule)->mcpTools());

        // Recursive deletion is the one thing here a mistaken call cannot take back, and an
        // agent cannot ask the question the panel asks first.
        $this->assertSame([], array_filter($names, static fn (string $name): bool => str_contains($name, 'directory') && str_contains($name, 'delete')));
    }

    #[Test]
    public function listing_is_paginated_and_files_are_described(): void
    {
        $this->file('One.jpg');
        $this->file('Two.jpg');

        $answer = $this->invoke('list_files', []);

        $this->assertSame(2, $answer['total']);
        $this->assertSame(1, $answer['pages']);
        $this->assertArrayHasKey('url', $answer['files'][0]);
        $this->assertSame('image', $answer['files'][0]['type']);
    }

    #[Test]
    public function searching_narrows_to_the_name(): void
    {
        $this->file('Sofa Oslo.jpg');
        $this->file('Chair Bergen.jpg');

        $answer = $this->invoke('search_files', ['query' => 'bergen']);

        $this->assertSame(1, $answer['total']);
        $this->assertSame('Chair Bergen', $answer['files'][0]['name']);
    }

    #[Test]
    public function a_dry_run_says_what_it_would_do_and_changes_nothing(): void
    {
        $file = $this->file('Sofa Oslo.jpg');

        $answer = $this->invoke('rename_file', ['id' => $file->id, 'name' => 'Sofa, grey', 'dry_run' => true]);

        $this->assertTrue($answer['ok']);
        $this->assertStringContainsString('Sofa, grey', $answer['would']);
        $this->assertSame('Sofa Oslo', $file->refresh()->name);
    }

    #[Test]
    public function deleting_through_a_tool_takes_the_bytes_as_well(): void
    {
        $file = $this->file('Sofa Oslo.jpg');

        $this->assertTrue($this->invoke('delete_files', ['ids' => [$file->id]])['ok']);

        $this->assertSame(0, MediaFile::query()->count());
        Storage::disk('public')->assertMissing($file->path);
    }

    #[Test]
    public function a_folder_is_created_through_a_tool(): void
    {
        $answer = $this->invoke('create_directory', [
            'parent_id' => $this->root()->getKey(),
            'title' => 'Pictures',
        ]);

        $this->assertTrue($answer['ok']);
        $this->assertSame('Pictures', MediaDirectory::query()->find($answer['id'])?->title);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function invoke(string $name, array $arguments): array
    {
        foreach ((new MediaModule)->mcpTools() as $tool) {
            if ($tool->name === $name) {
                /** @var array<string, mixed> $result */
                $result = ($tool->handler)($arguments);

                return $result;
            }
        }

        $this->fail("No tool named [{$name}].");
    }

    private function file(string $name): MediaFile
    {
        return $this->app->make(FileStore::class)->store(
            UploadedFile::fake()->image($name, random_int(100, 900), 400),
            $this->root(),
        );
    }

    private function root(): MediaDirectory
    {
        return MediaDirectory::query()->whereNull('parent_id')->firstOrFail();
    }
}
