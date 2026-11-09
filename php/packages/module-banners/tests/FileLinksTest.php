<?php

declare(strict_types=1);

namespace WebxUi\Banners\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Panel\BannerForm;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Media\Models\MediaFile;

/**
 * A button that downloads a file of the library — a brochure from the hero — through every door
 * a banner has: the panel, a layer, an agent; and what the site prints for it.
 */
final class FileLinksTest extends TestCase
{
    private const PDF = 'media/9f/1e/0b7c.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        $this->picture();
        $brochure = $this->picture(self::PDF, 'application/pdf');
        $brochure->update(['file_name' => 'Brochure 2026.pdf', 'name' => 'Renamed in the grid']);
    }

    #[Test]
    public function a_button_of_the_panel_keeps_the_file_and_the_site_downloads_it_under_its_own_name(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('places/promo/banners'), ['values' => [
            'image' => ['path' => 'media/ab/cd/wide.jpg'],
            'title' => ['en' => 'The house by the lake'],
            'enabled' => true,
            'buttons' => [
                ['label' => ['en' => 'Download the brochure'], 'link' => self::file(self::PDF, download: true), 'variant' => 'primary'],
                ['label' => ['en' => 'Open the plan'], 'link' => self::file(self::PDF, newTab: true), 'variant' => 'link'],
                ['label' => ['en' => 'Contacts'], 'link' => self::url('/contacts'), 'variant' => 'secondary'],
            ],
        ]]);

        $response->assertCreated();
        $this->assertSame(self::PDF, $response->json('data.values.buttons.0.link.path'));
        $this->assertTrue($response->json('data.values.buttons.0.link.download'));
        $this->assertArrayNotHasKey('path', (array) $response->json('data.values.buttons.2.link'), 'an address keeps the keys it always had');

        $buttons = banners('promo')->first()['buttons'];

        $this->assertStringEndsWith('/'.self::PDF, explode('?', $buttons[0]['url'])[0]);
        $this->assertSame('Brochure 2026.pdf', $buttons[0]['download']);
        $this->assertNull($buttons[1]['download'], 'opened, not saved');
        $this->assertTrue($buttons[1]['new_tab']);
        $this->assertNull($buttons[2]['download']);

        $html = Blade::render('<x-webx-banners place="promo" />');

        $this->assertMatchesRegularExpression('#<a class="webx-banner__button webx-banner__button--primary" href="[^"]+/'.preg_quote(self::PDF, '#').'[^"]*" download="Brochure 2026.pdf">Download the brochure</a>#', $html);
        $this->assertMatchesRegularExpression('#<a class="webx-banner__button webx-banner__button--link" href="[^"]+" target="_blank" rel="noopener noreferrer">Open the plan</a>#', $html);
        $this->assertStringContainsString('<a class="webx-banner__button webx-banner__button--secondary" href="http://localhost/contacts">Contacts</a>', $html);
    }

    #[Test]
    public function a_button_layer_downloads_the_file_too(): void
    {
        $this->app->make(BannerForm::class)->save($this->banner(), ['layers' => [[
            'type' => 'button',
            'text' => ['en' => 'Brochure'],
            'link' => self::file(self::PDF, download: true),
            'frames' => ['desktop' => ['x' => 5, 'y' => 5, 'w' => 20, 'size' => 2]],
        ]]]);

        $html = Blade::render('<x-webx-banners place="hero" />');

        $this->assertMatchesRegularExpression('#href="[^"]+/'.preg_quote(self::PDF, '#').'[^"]*" download="Brochure 2026.pdf">Brochure</a>#', $html);
    }

    /**
     * A key nobody has is one typed rather than picked, and refused; a file the banner already
     * pointed at, deleted from the library since, does not lock the banner — its button drops out.
     */
    #[Test]
    public function an_unknown_file_is_refused_and_one_deleted_since_does_not_lock_the_banner(): void
    {
        $banner = $this->banner('promo');
        $editor = $this->actingAs($this->editor(), 'cms');

        $editor->putJson($this->api($banner->id), ['values' => ['buttons' => [
            ['label' => ['en' => 'Ghost'], 'link' => self::file('media/no/ne/gone.pdf', download: true), 'variant' => 'primary'],
        ]]])
            ->assertUnprocessable()
            ->assertJsonPath('errors', fn (array $errors): bool => str_contains($errors['buttons.0.link'][0], 'There is no file media/no/ne/gone.pdf in the media library.'));

        $buttons = [['label' => ['en' => 'Brochure'], 'link' => self::file(self::PDF, download: true), 'variant' => 'primary']];
        $editor->putJson($this->api($banner->id), ['values' => ['buttons' => $buttons]])->assertOk();

        MediaFile::query()->where('path', self::PDF)->delete();

        $editor->putJson($this->api($banner->id), ['values' => ['buttons' => $buttons, 'title' => ['en' => 'Again']]])->assertOk();
        $this->assertSame([], banners('promo')->first()['buttons'], 'a button to a file that is gone is not printed');
    }

    #[Test]
    public function a_download_that_is_neither_yes_nor_no_is_refused(): void
    {
        $this->actingAs($this->editor(), 'cms')->putJson($this->api($this->banner('promo')->id), ['values' => ['buttons' => [
            ['label' => ['en' => 'Brochure'], 'link' => ['target' => 'file', 'path' => self::PDF, 'download' => 'yes'], 'variant' => 'primary'],
        ]]])->assertUnprocessable()->assertJsonValidationErrors(['buttons.0.link']);
    }

    #[Test]
    public function an_agent_links_a_button_to_a_file_and_a_dry_run_is_refused_for_an_unknown_one(): void
    {
        $this->agent('banners_create', [
            'place' => 'promo',
            'image' => 'media/ab/cd/wide.jpg',
            'buttons' => [['label' => 'Brochure', 'link' => ['path' => 'media/no/ne/gone.pdf', 'download' => true]]],
            'dry_run' => true,
        ])->assertHasErrors(['buttons.0.link', 'There is no file media/no/ne/gone.pdf in the media library.']);

        $this->agent('banners_create', [
            'place' => 'promo',
            'image' => 'media/ab/cd/wide.jpg',
            'buttons' => [['label' => 'Brochure', 'link' => ['path' => self::PDF, 'download' => true]]],
            'dry_run' => true,
        ])->assertOk();
        $this->assertSame(0, Banner::query()->count(), 'a dry run writes nothing');

        $created = $this->content($this->agent('banners_create', [
            'place' => 'promo',
            'image' => 'media/ab/cd/wide.jpg',
            'buttons' => [['label' => 'Brochure', 'link' => ['path' => self::PDF, 'download' => true, 'new_tab' => true]]],
        ]));

        $link = $created['values']['buttons'][0]['link'];
        $this->assertSame(['file', self::PDF, true, true], [$link['target'], $link['path'], $link['download'], $link['new_tab']]);

        $this->agent('banners_update', [
            'banner' => $created['banner']['id'],
            'values' => ['buttons' => [['label' => 'Brochure', 'link' => ['target' => 'file', 'path' => 'media/no/ne/other.pdf']]]],
            'dry_run' => true,
        ])->assertHasErrors(['There is no file media/no/ne/other.pdf in the media library.']);
    }

    /**
     * The files of every button of every banner are asked of the library once, with the rest of
     * the list — not once per button.
     */
    #[Test]
    public function the_files_of_a_list_are_one_query(): void
    {
        foreach (['media/aa/aa/one.pdf', 'media/bb/bb/two.pdf', 'media/cc/cc/three.pdf'] as $path) {
            $this->picture($path, 'application/pdf');
            $this->banner('promo', attributes: ['buttons' => [
                ['label' => ['en' => 'Get it'], 'link' => self::file($path, download: true), 'variant' => 'primary'],
            ]]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $cards = banners('promo')->take(3)->get();
        // The pictures are looked up before the cards (BannerQuery::models()); the files are not.
        $library = array_values(array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'media_files') && preg_grep('/\.pdf$/', $query['bindings']) !== []));
        DB::disableQueryLog();

        $this->assertCount(3, $cards);
        $this->assertSame(['one.pdf', 'two.pdf', 'three.pdf'], array_map(static fn (array $card): ?string => $card['buttons'][0]['download'], $cards));
        $this->assertCount(1, $library, 'the files of every button in one query');
        $this->assertCount(3, $library[0]['bindings']);
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
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
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
