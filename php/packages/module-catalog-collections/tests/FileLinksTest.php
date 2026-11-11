<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Media\Screens\MediaFiles;

/**
 * The link of a collection to a file of the library — the catalogue as a PDF beside the new
 * arrivals: kept, printed with `download`, refused for a key nobody has.
 */
final class FileLinksTest extends TestCase
{
    private const PDF = 'media/3e/5b/c0de.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        $root = MediaDirectory::query()->whereNull('parent_id')->firstOrFail();

        MediaFile::query()->create([
            'directory_id' => $root->getKey(),
            'disk' => 'public',
            'path' => self::PDF,
            'hash' => md5(self::PDF),
            'name' => 'Renamed in the grid',
            'file_name' => 'Catalogue 2026.pdf',
            'extension' => 'pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
        ]);
    }

    #[Test]
    public function the_link_downloads_the_file_under_its_own_name_or_opens_it_in_a_new_tab(): void
    {
        $collection = $this->collection(['link' => ['target' => 'file', 'path' => self::PDF, 'download' => true], 'link_text' => 'The catalogue']);

        $this->assertSame(['target' => 'file', 'path' => self::PDF, 'download' => true], array_intersect_key((array) $collection->link, array_flip(['target', 'path', 'download'])));

        $html = $this->render();

        $this->assertMatchesRegularExpression('#<a class="webx-catalog-collection__all" href="[^"]+/'.preg_quote(self::PDF, '#').'[^"]*" download="Catalogue 2026.pdf">The catalogue</a>#', $html);

        $collection->update(['link' => ['target' => 'file', 'path' => self::PDF, 'new_tab' => true]]);

        $this->assertMatchesRegularExpression('#href="[^"]+/'.preg_quote(self::PDF, '#').'[^"]*" target="_blank" rel="noopener noreferrer">The catalogue</a>#', $this->render());
    }

    #[Test]
    public function an_address_prints_as_it_always_did(): void
    {
        $this->collection(['link' => ['target' => 'url', 'url' => '/catalog/new']]);

        $this->assertStringContainsString('<a class="webx-catalog-collection__all" href="http://localhost/catalog/new">All products</a>', $this->render());
    }

    /**
     * A key nobody has is one typed rather than picked, and refused; a file the collection already
     * pointed at, deleted from the library since, does not stop a save — the link drops out.
     */
    #[Test]
    public function an_unknown_file_is_refused_and_one_deleted_since_does_not_lock_the_collection(): void
    {
        try {
            $this->collection(['link' => ['target' => 'file', 'path' => 'media/no/ne/gone.pdf']]);
            $this->fail('The link was saved.');
        } catch (ValidationException $refusal) {
            $this->assertSame('There is no file media/no/ne/gone.pdf in the media library.', $refusal->errors()['link'][0] ?? null);
        }

        $collection = $this->collection(['link' => ['target' => 'file', 'path' => self::PDF, 'download' => true]]);
        MediaFile::query()->where('path', self::PDF)->delete();
        // What one response knew of the library; the next one asks again.
        $this->app->make(MediaFiles::class)->flush();

        $collection->update(['title' => 'Picks again', 'link' => ['target' => 'file', 'path' => self::PDF, 'download' => false]]);

        $this->assertSame(self::PDF, $collection->refresh()->link['path'] ?? null);
        $this->assertStringNotContainsString('webx-catalog-collection__all', $this->render());
    }

    #[Test]
    public function an_agent_links_to_a_file_and_a_dry_run_is_refused_for_an_unknown_one(): void
    {
        $this->collection();

        $this->agent('catalog_collections_update', [
            'collection' => 'picks',
            'values' => ['link' => ['target' => 'file', 'path' => 'media/no/ne/gone.pdf', 'download' => true]],
            'dry_run' => true,
        ])->assertHasErrors(['link', 'There is no file media/no/ne/gone.pdf in the media library.']);

        $this->agent('catalog_collections_update', [
            'collection' => 'picks',
            'values' => ['link' => ['target' => 'file', 'path' => self::PDF, 'download' => true]],
            'dry_run' => true,
        ])->assertOk();
        $this->assertNull(ProductCollection::query()->where('key', 'picks')->firstOrFail()->link, 'a dry run writes nothing');

        $this->agent('catalog_collections_update', [
            'collection' => 'picks',
            'values' => ['link' => ['target' => 'file', 'path' => self::PDF, 'download' => true]],
        ])->assertOk();

        $this->assertMatchesRegularExpression('#download="Catalogue 2026.pdf">All products</a>#', $this->render());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function collection(array $attributes = []): ProductCollection
    {
        $collection = ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks', ...$attributes])->refresh();
        $collection->products()->attach($this->product('One')->id, ['position' => 0]);
        $collection->touch();

        return $collection;
    }

    private function render(): string
    {
        return trim(Blade::render('<x-webx-catalog-collections::collection key="picks" />'));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->manager(), 'cms')->tool($bound, $arguments);
    }
}
