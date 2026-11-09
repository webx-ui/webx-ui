<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\Models\MediaDirectory;
use WebxUi\Media\Models\MediaFile;
use WebxUi\Tariffs\Models\Tariff;

/**
 * The button of a tariff that downloads a file of the library — the price list in full — through
 * the panel and an agent, and what the block prints for it.
 */
final class FileLinksTest extends TestCase
{
    private const PDF = 'media/7a/2c/91d0.pdf';

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
            'file_name' => 'Price list.pdf',
            'extension' => 'pdf',
            'mime' => 'application/pdf',
            'size' => 2048,
        ]);
    }

    #[Test]
    public function the_panel_keeps_a_file_link_and_the_block_downloads_it_under_its_own_name(): void
    {
        $this->installBlock();
        $starter = $this->tariff('Starter', attributes: ['button_label' => ['en' => 'Get the price list']]);
        $growth = $this->tariff('Growth', attributes: ['button_label' => ['en' => 'Open the terms']]);
        $this->tariff('Enterprise', attributes: ['button_label' => ['en' => 'Contact us'], 'button_link' => self::url('/contacts')]);
        $editor = $this->actingAs($this->editor(), 'cms');

        $editor->putJson($this->api($starter->id), ['values' => ['button_link' => self::file(self::PDF, download: true)]])
            ->assertOk()
            ->assertJsonPath('data.values.button_link.path', self::PDF)
            ->assertJsonPath('data.values.button_link.download', true);
        $editor->putJson($this->api($growth->id), ['values' => ['button_link' => self::file(self::PDF, newTab: true)]])->assertOk();

        $cards = tariffs()->get();

        $this->assertSame('Price list.pdf', $cards[0]['button']['download']);
        $this->assertStringEndsWith('/'.self::PDF, explode('?', $cards[0]['button']['url'])[0]);
        $this->assertNull($cards[1]['button']['download'], 'opened, not saved');
        $this->assertNull($cards[2]['button']['download']);

        $html = (string) $this->app->make(Renderer::class)->render([['key' => 'k1', 'type' => 'tariffs', 'values' => ['layout' => 'grid']]]);

        $this->assertMatchesRegularExpression('#<a class="b-tariffs__button b-tariffs__button--primary" href="[^"]+/'.preg_quote(self::PDF, '#').'[^"]*" download="Price list.pdf">Get the price list</a>#', $html);
        $this->assertMatchesRegularExpression('#href="[^"]+/'.preg_quote(self::PDF, '#').'[^"]*" target="_blank" rel="noopener noreferrer">Open the terms</a>#', $html);
        $this->assertStringContainsString('<a class="b-tariffs__button b-tariffs__button--primary" href="http://localhost/contacts">Contact us</a>', $html);
    }

    /**
     * A key nobody has is one typed rather than picked, and refused; a file the tariff already
     * pointed at, deleted from the library since, does not lock it — its button drops out.
     */
    #[Test]
    public function an_unknown_file_is_refused_and_one_deleted_since_does_not_lock_the_tariff(): void
    {
        $tariff = $this->tariff('Starter', attributes: ['button_label' => ['en' => 'Price list']]);
        $editor = $this->actingAs($this->editor(), 'cms');

        $editor->putJson($this->api($tariff->id), ['values' => ['button_link' => self::file('media/no/ne/gone.pdf')]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.button_link.0', 'There is no file media/no/ne/gone.pdf in the media library.');

        $editor->putJson($this->api($tariff->id), ['values' => ['button_link' => ['target' => 'file', 'path' => self::PDF, 'download' => 'yes']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_link']);

        $editor->putJson($this->api($tariff->id), ['values' => ['button_link' => self::file(self::PDF, download: true)]])->assertOk();

        MediaFile::query()->where('path', self::PDF)->delete();

        $editor->putJson($this->api($tariff->id), ['values' => ['button_link' => self::file(self::PDF, download: true), 'name' => ['en' => 'Starter 2']]])->assertOk();
        $this->assertNull(tariffs()->first()['button'] ?? null, 'a button to a file that is gone is not printed');
    }

    #[Test]
    public function an_agent_links_the_button_to_a_file_and_a_dry_run_is_refused_for_an_unknown_one(): void
    {
        $this->agent('tariffs_create', [
            'name' => 'Starter',
            'button' => ['label' => 'Price list', 'link' => ['path' => 'media/no/ne/gone.pdf', 'download' => true]],
            'dry_run' => true,
        ])->assertHasErrors(['button_link', 'There is no file media/no/ne/gone.pdf in the media library.']);

        $this->agent('tariffs_create', [
            'name' => 'Starter',
            'button' => ['label' => 'Price list', 'link' => ['path' => self::PDF, 'download' => true]],
            'dry_run' => true,
        ])->assertOk();
        $this->assertSame(0, Tariff::query()->count(), 'a dry run writes nothing');

        $created = $this->content($this->agent('tariffs_create', [
            'name' => 'Starter',
            'published' => true,
            'button' => ['label' => 'Price list', 'link' => ['path' => self::PDF, 'download' => true]],
        ]));

        $this->assertSame('Price list.pdf', tariffs()->first()['button']['download'] ?? null);

        $this->agent('tariffs_update', [
            'tariff' => $created['tariff']['id'],
            'values' => ['button' => ['link' => ['target' => 'file', 'path' => 'media/no/ne/other.pdf']]],
            'dry_run' => true,
        ])->assertHasErrors(['There is no file media/no/ne/other.pdf in the media library.']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function file(string $path, bool $download = false, bool $newTab = false): array
    {
        return ['target' => 'file', 'path' => $path, 'download' => $download, 'entity_type' => null, 'entity_id' => null, 'url' => null, 'hash' => null, 'new_tab' => $newTab, 'rel' => []];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
