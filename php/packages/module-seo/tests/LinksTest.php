<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Mcp\Tool;
use WebxUi\Routing\Resolution;
use WebxUi\Routing\Resolver;
use WebxUi\Seo\Links\LinkBlocks;
use WebxUi\Seo\Links\LinkImport;
use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\SeoModule;
use WebxUi\Seo\Tests\Fixtures\MappedEntity;
use WebxUi\Seo\Tests\Fixtures\MapsEntities;
use WebxUi\Settings\Settings;

/**
 * Interlinking (§18.4): a brief comes in as a table, goes out as blocks on donor pages, and keeps
 * working when the pages it names are renamed or taken down.
 */
final class LinksTest extends TestCase
{
    use MapsEntities;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-seo.links.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapEntities();
    }

    #[Test]
    public function the_import_groups_by_donor_keeps_the_file_order_and_previews_first(): void
    {
        foreach (['laptops', 'phones', 'apple', 'samsung', 'dell'] as $slug) {
            $this->entity($slug);
        }

        $csv = "Донор;Акцептор;Анкор;Заголовок\n"
            ."/laptops;/apple;Apple laptops;Similar laptops\n"
            ."/phones;/samsung;Samsung phones;\n"
            ."/laptops;/dell;Dell laptops;Ignored, the first line had one\n"
            ."https://example.test/laptops/;/samsung;Not a laptop;\n";

        $preview = $this->import($csv)->assertOk()->json('data');

        $this->assertFalse($preview['applied']);
        $this->assertSame(2, $preview['donors']);
        $this->assertSame(4, $preview['links']);
        $this->assertSame(2, $preview['created']);
        $this->assertSame(0, SeoLinkBlock::query()->count());

        $this->import($csv, dryRun: false)->assertOk()->assertJsonPath('data.applied', true);

        $laptops = $this->blockOf('laptops');

        $this->assertSame('Similar laptops', $laptops->heading);
        $this->assertSame(['Apple laptops', 'Dell laptops', 'Not a laptop'], $laptops->items->pluck('anchor')->all());
        $this->assertNull($this->blockOf('phones')->heading);
    }

    #[Test]
    public function replace_swaps_a_donors_links_and_append_adds_to_them(): void
    {
        foreach (['hub', 'a', 'b', 'c'] as $slug) {
            $this->entity($slug);
        }

        $this->import("donor,acceptor,anchor\n/hub,/a,A\n/hub,/b,B\n", dryRun: false);

        $this->import("donor,acceptor,anchor\n/hub,/c,C\n", dryRun: false);
        $this->assertSame(['C'], $this->blockOf('hub')->items->pluck('anchor')->all());

        $response = $this->import("donor,acceptor,anchor\n/hub,/a,A\n/hub,/c,C again\n", dryRun: false, mode: LinkImport::APPEND);

        // The link the block already has is a duplicate in append, not a second copy.
        $response->assertJsonPath('data.appended', 1)->assertJsonPath('data.errors', 1);
        $this->assertSame(['C', 'A'], $this->blockOf('hub')->items->pluck('anchor')->all());
    }

    #[Test]
    public function a_link_to_itself_and_a_repeated_link_are_row_errors_and_the_rest_goes_in(): void
    {
        foreach (['donor', 'target', 'other'] as $slug) {
            $this->entity($slug);
        }

        $data = $this->import(
            "donor,acceptor,anchor\n/donor,/donor,Itself\n/donor,/target,Target\n/donor,/target/,Target again\n"
            ."/donor,https://other.test/x,Elsewhere\n/donor,/nowhere-at-all,Typo\n/donor,/other,Other\n",
            dryRun: false,
        )->json('data');

        $codes = array_column($data['problems'], 'code', 'line');

        $this->assertSame([2 => 'self', 4 => 'duplicate', 5 => 'foreign-host', 6 => 'not-found'], $codes);
        $this->assertSame(['Target', 'Other'], $this->blockOf('donor')->items->pluck('anchor')->all());
    }

    #[Test]
    public function an_address_behind_a_redirect_is_replaced_by_its_target_with_a_warning(): void
    {
        $this->entity('donor');
        $target = $this->entity('new-home');
        SeoRedirect::query()->create(['match_type' => 'exact', 'pattern' => '/old-home', 'target' => '/new-home']);

        $data = $this->import("donor,acceptor,anchor\n/donor,/old-home,Home\n", dryRun: false)->json('data');

        $this->assertSame('redirected', $data['problems'][0]['code']);
        $this->assertSame('warning', $data['problems'][0]['level']);
        $this->assertSame($target->id, $this->blockOf('donor')->items->first()?->entity_id);
    }

    #[Test]
    public function a_broken_acceptor_is_not_printed_and_is_flagged_in_the_panel(): void
    {
        $donor = $this->entity('donor');
        $live = $this->entity('live');
        $hidden = $this->entity('hidden');

        $this->import("donor,acceptor,anchor\n/donor,/live,Live\n/donor,/hidden,Hidden\n", dryRun: false);

        $hidden->update(['published' => false]);

        $html = $this->render($donor);

        $this->assertStringContainsString('href="/live"', $html);
        $this->assertStringNotContainsString('Hidden', $html);

        $block = app(LinkBlocks::class)->describe($this->blockOf('donor')->load('items'));

        $this->assertSame(1, $block['broken_count']);
        $this->assertSame([false, true], array_map(static fn (array $item): bool => $item['acceptor']['broken'], $block['items']));

        // Every link broken — nothing at all, not an empty heading.
        $live->delete();
        $this->assertSame('', trim($this->render($donor)));
    }

    #[Test]
    public function donor_and_acceptor_bound_to_entities_survive_a_rename(): void
    {
        $donor = $this->entity('noutbuki');
        $acceptor = $this->entity('noutbuki-apple');

        $this->import("donor,acceptor,anchor\n/noutbuki,/noutbuki-apple,Apple\n", dryRun: false);

        $this->rename($donor, 'laptops');
        $this->rename($acceptor, 'apple-laptops');

        $html = $this->render($donor->refresh());

        $this->assertStringContainsString('href="/apple-laptops"', $html);
    }

    #[Test]
    public function an_empty_heading_falls_back_to_the_setting_and_then_to_the_shipped_word(): void
    {
        $donor = $this->entity('donor');
        $this->entity('target');

        $this->import("donor,acceptor,anchor\n/donor,/target,Target\n", dryRun: false);

        $this->assertStringContainsString('Смотрите также', $this->render($donor));

        app(Settings::class)->save(['seo.links-heading' => ['ru' => 'Похожие разделы']]);

        $this->assertStringContainsString('Похожие разделы', $this->render($donor));
    }

    #[Test]
    public function a_heading_is_set_in_bulk_on_every_donor_under_a_prefix(): void
    {
        $this->entity('target');

        $this->import(
            "donor,acceptor,anchor\n/catalog/tech,/target,T\n/catalog/tech/tv,/target,T\n/catalog/tech-2,/target,T\n/blog,/target,T\n",
            dryRun: false,
        );

        $preview = $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/seo/links/heading', ['prefix' => '/catalog/tech/', 'heading' => 'Similar tech'])
            ->assertOk();

        $preview->assertJsonPath('data.count', 2)->assertJsonPath('data.applied', false);
        $this->assertSame(0, SeoLinkBlock::query()->where('heading', 'Similar tech')->count());

        $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/seo/links/heading', ['prefix' => '/catalog/tech/', 'heading' => 'Similar tech', 'dry_run' => false])
            ->assertJsonPath('data.applied', true);

        $this->assertEqualsCanonicalizing(
            ['/catalog/tech', '/catalog/tech/tv'],
            SeoLinkBlock::query()->where('heading', 'Similar tech')->pluck('path')->all(),
        );
    }

    #[Test]
    public function quotes_and_angle_brackets_in_an_anchor_are_escaped(): void
    {
        $donor = $this->entity('donor');
        $this->entity('target');

        $this->import("donor,acceptor,anchor,heading\n/donor,/target,\"Say \"\"hi\"\" <b>now</b>\",\"A <script>\"\n", dryRun: false);

        $html = $this->render($donor);

        $this->assertStringContainsString('Say &quot;hi&quot; &lt;b&gt;now&lt;/b&gt;', $html);
        $this->assertStringContainsString('A &lt;script&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertMatchesRegularExpression('#<nav class="webx-links" aria-labelledby="(webx-links-[0-9a-f]+)">\s*<h2 id="\1"#', $html);
    }

    #[Test]
    public function the_panel_writes_a_block_as_one_form_and_refuses_what_the_import_refuses(): void
    {
        $this->entity('donor');
        $this->entity('a');
        $this->entity('b');

        $editor = $this->editor();

        $created = $this->actingAs($editor, 'cms')->postJson('/api/cms/seo/links', [
            'donor' => '/donor',
            'heading' => 'Read next',
            'items' => [['acceptor' => '/a', 'anchor' => 'A'], ['acceptor' => '/b', 'anchor' => 'B']],
        ])->assertCreated();

        $id = $created->json('data.id');
        $created->assertJsonPath('data.links_count', 2)->assertJsonPath('data.items.1.acceptor.url', '/b');

        $this->actingAs($editor, 'cms')->putJson('/api/cms/seo/links/'.$id, [
            'donor' => '/donor',
            'items' => [['acceptor' => '/donor', 'anchor' => 'Self'], ['acceptor' => '/a', 'anchor' => 'A']],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.acceptor');

        $this->actingAs($editor, 'cms')->postJson('/api/cms/seo/links', [
            'donor' => '/donor/',
            'items' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('donor');

        $this->actingAs($editor, 'cms')->getJson('/api/cms/seo/links?q=read')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($editor, 'cms')->getJson('/api/cms/seo/links?q=B')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($editor, 'cms')->getJson('/api/cms/seo/links?broken=1')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->editor(['seo.view']), 'cms')->deleteJson('/api/cms/seo/links/'.$id)->assertForbidden();
        $this->actingAs($editor, 'cms')->deleteJson('/api/cms/seo/links/'.$id)->assertNoContent();
        $this->assertSame(0, SeoLinkBlock::query()->count());
    }

    #[Test]
    public function the_export_is_the_flat_file_the_import_reads(): void
    {
        foreach (['hub', 'a', 'b'] as $slug) {
            $this->entity($slug);
        }

        $csv = "donor,acceptor,anchor,heading\n/hub,/a,A,Next\n/hub,/b,B,\n";
        $this->import($csv, dryRun: false);

        $response = $this->actingAs($this->editor(), 'cms')->get('/api/cms/seo/links/export?format=csv')->assertOk();
        $file = (string) file_get_contents($this->exported($response));

        $this->assertSame("\xEF\xBB\xBF".$csv, str_replace("\r\n", "\n", $file));

        // And back in, replacing, it changes nothing.
        $this->import(substr($file, 3), dryRun: false)->assertJsonPath('data.replaced', 1)->assertJsonPath('data.errors', 0);
        $this->assertSame(['A', 'B'], $this->blockOf('hub')->items->pluck('anchor')->all());
    }

    #[Test]
    public function an_xlsx_goes_out_and_comes_back_the_same(): void
    {
        foreach (['hub', 'a'] as $slug) {
            $this->entity($slug);
        }

        // An anchor starting with `=` stays text rather than turning into a formula.
        $this->import("donor,acceptor,anchor,heading\n/hub,/a,=1+1,Next\n", dryRun: false);

        $response = $this->actingAs($this->editor(), 'cms')->get('/api/cms/seo/links/export?format=xlsx')->assertOk();
        $path = $this->exported($response);

        SeoLinkBlock::query()->delete();

        $this->actingAs($this->editor(), 'cms')->post('/api/cms/seo/links/import', [
            'file' => new UploadedFile($path, 'brief.xlsx', null, null, true),
            'dry_run' => '0',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);

        $hub = $this->blockOf('hub');
        $this->assertSame('Next', $hub->heading);
        $this->assertSame('=1+1', $hub->items->first()?->anchor);
    }

    #[Test]
    public function an_agent_gets_the_same_tools_and_the_same_refusals(): void
    {
        $this->entity('donor');
        $this->entity('target');

        $tools = collect(app(SeoModule::class)->mcpTools())->keyBy(static fn (Tool $tool): string => $tool->name);

        $this->assertSame(
            ['links_list', 'links_get', 'links_set', 'links_delete', 'links_import', 'links_heading'],
            array_values(array_filter($tools->keys()->all(), static fn (string $name): bool => str_starts_with($name, 'links_'))),
        );

        $set = $tools['links_set']->handler;
        $refused = $set(['url' => '/donor', 'links' => [['acceptor' => '/donor', 'anchor' => 'Self']]]);
        $this->assertFalse($refused['ok']);

        $this->assertTrue($set(['url' => '/donor', 'links' => [['acceptor' => '/target', 'anchor' => 'Target']]])['ok']);

        $got = ($tools['links_get']->handler)(['url' => '/donor']);
        $this->assertSame('Target', $got['block']['items'][0]['anchor']);

        $preview = ($tools['links_import']->handler)(['rows' => [['donor' => '/donor', 'acceptor' => '/donor', 'anchor' => 'x']], 'dry_run' => true]);
        $this->assertSame(1, $preview['problems'][0]['line']);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function exported(TestResponse $response): string
    {
        $file = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $file);

        return $file->getFile()->getPathname();
    }

    /**
     * @return TestResponse<Response>
     */
    private function import(string $csv, bool $dryRun = true, string $mode = LinkImport::REPLACE): TestResponse
    {
        return $this->actingAs($this->editor(), 'cms')->post('/api/cms/seo/links/import', [
            'file' => UploadedFile::fake()->createWithContent('brief.csv', $csv),
            'mode' => $mode,
            'dry_run' => $dryRun ? '1' : '0',
        ], ['Accept' => 'application/json']);
    }

    private function blockOf(string $slug): SeoLinkBlock
    {
        return SeoLinkBlock::query()->where('path', '/'.$slug)->with('items')->firstOrFail();
    }

    /** The donor's page, as the registry hands it to whatever answers it. */
    private function render(MappedEntity $donor): string
    {
        $request = Request::create($donor->url('ru'));
        $resolution = app(Resolver::class)->lookup($request->getPathInfo());
        $this->assertNotNull($resolution);
        $request->attributes->set(Resolution::ATTRIBUTE, $resolution);
        $this->app->instance('request', $request);

        return Blade::render('<x-webx-seo::links />');
    }
}
