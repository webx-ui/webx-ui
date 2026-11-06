<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Doctor\LatinSlugsCheck;
use WebxUi\Routing\Models\Route;
use WebxUi\Seo\Models\SeoRedirect;

/**
 * The rule outside the catalogue, and the way out for what was saved before it: a page's slug in
 * another alphabet, moved by `webx:slugs:latin`, with a 301 from every address that moved.
 */
final class LatinSlugsTest extends TestCase
{
    #[Test]
    public function a_pages_slug_is_transliterated_in_the_dialog(): void
    {
        $id = (int) $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => 'Delivery', 'slug' => 'доставка'])
            ->assertCreated()
            ->json('data.id');

        $page = Page::query()->findOrFail($id);

        $this->assertSame('dostavka', $page->getTranslation('slug', 'en'));
        $this->assertSame('http://localhost/dostavka', $page->url('en'));
    }

    #[Test]
    public function an_agent_and_a_direct_write_transliterate_a_pages_slug(): void
    {
        WebxServer::actingAs($this->editor(), 'cms')
            ->tool(new RegistryTool($this->app->make(ToolRegistry::class)->tool('pages_create')), [
                'title' => ['en' => 'Agent'],
                'slug' => ['en' => 'создана-агентом'],
            ])
            ->assertOk();

        $page = Page::query()->latest('id')->firstOrFail();

        $this->assertSame('sozdana-agentom', $page->getTranslation('slug', 'en'));
        $this->assertSame('http://localhost/sozdana-agentom', $page->url('en'));

        $page->setTranslation('slug', 'en', 'прямая');
        $page->save();

        $this->assertSame('priamaia', $page->refresh()->getTranslation('slug', 'en'));
        $this->assertSame('http://localhost/priamaia', $page->url('en'));
    }

    #[Test]
    public function a_title_that_leaves_nothing_gives_the_type_and_the_id(): void
    {
        $id = (int) $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['title' => '漢字'])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame("page-{$id}", Page::query()->findOrFail($id)->getTranslation('slug', 'en'));
        $this->assertSame("http://localhost/page-{$id}", Page::query()->findOrFail($id)->url('en'));
    }

    #[Test]
    public function the_command_only_lists_until_told_to_apply(): void
    {
        $page = $this->oldPage('доставка');

        $this->artisan('webx:slugs:latin')
            ->expectsOutputToContain('Nothing was written')
            ->assertSuccessful();

        $this->artisan('webx:slugs:latin', ['--apply' => true, '--dry-run' => true])->assertSuccessful();

        $this->assertSame('доставка', $page->refresh()->getTranslation('slug', 'en'));
        $this->assertSame(0, SeoRedirect::query()->count());
    }

    #[Test]
    public function the_command_moves_a_slug_and_every_address_below_it_with_a_301(): void
    {
        $parent = $this->oldPage('доставка');
        $child = $this->page('courier', $parent);

        $this->assertSame('http://localhost/%D0%B4%D0%BE%D1%81%D1%82%D0%B0%D0%B2%D0%BA%D0%B0/courier', $this->encoded($child));

        $this->artisan('webx:slugs:latin', ['--apply' => true])
            ->expectsOutputToContain('1 record(s) moved, 2 redirect(s) written')
            ->assertSuccessful();

        $this->assertSame('dostavka', $parent->refresh()->getTranslation('slug', 'en'));
        $this->assertSame('http://localhost/dostavka/courier', $child->refresh()->url('en'));

        // What a browser sends: the address percent-encoded.
        $this->get('/%D0%B4%D0%BE%D1%81%D1%82%D0%B0%D0%B2%D0%BA%D0%B0')->assertStatus(301)->assertRedirect('/dostavka');
        $this->get('/%D0%B4%D0%BE%D1%81%D1%82%D0%B0%D0%B2%D0%BA%D0%B0/courier')->assertStatus(301)->assertRedirect('/dostavka/courier');

        $this->assertSame(2, SeoRedirect::query()->where('status', 301)->count());

        $this->artisan('webx:slugs:latin')->expectsOutputToContain('Every slug is in ASCII')->assertSuccessful();
    }

    #[Test]
    public function a_taken_address_is_reported_and_left_for_a_person(): void
    {
        $this->page('dostavka');
        $old = $this->oldPage('доставка');

        $this->artisan('webx:slugs:latin', ['--apply' => true])->assertFailed();

        $this->assertSame('доставка', $old->refresh()->getTranslation('slug', 'en'));
    }

    #[Test]
    public function a_slug_that_leaves_nothing_moves_to_the_type_and_the_id(): void
    {
        $old = $this->oldPage('漢字');

        $this->artisan('webx:slugs:latin', ['--apply' => true])->assertSuccessful();

        $this->assertSame("page-{$old->id}", $old->refresh()->getTranslation('slug', 'en'));
    }

    #[Test]
    public function the_doctor_warns_while_there_is_something_to_move(): void
    {
        $check = $this->app->make(LatinSlugsCheck::class);

        $this->page('dostavka');
        $this->assertSame([], $check->run());

        $this->oldPage('доставка-2');

        $found = $this->app->make(LatinSlugsCheck::class)->run();

        $this->assertCount(1, $found);
        $this->assertSame('warn', $found[0]->state);
        $this->assertStringContainsString('webx:slugs:latin', $found[0]->detail);

        $this->artisan('webx:slugs:latin', ['--apply' => true])->assertSuccessful();

        $this->assertSame([], $check->run());
    }

    /** A page as an older version left it: the slug and its address, written past the model. */
    private function oldPage(string $slug): Page
    {
        $page = $this->page('placeholder-'.bin2hex(random_bytes(3)));

        DB::table('pages')->where('id', $page->id)->update(['slug' => json_encode(['en' => $slug])]);
        Route::query()->forEntity($page)->update(['path' => $slug]);

        return Page::query()->findOrFail($page->id);
    }

    private function encoded(Page $page): string
    {
        $url = $page->url('en');

        return 'http://localhost'.implode('/', array_map(rawurlencode(...), explode('/', (string) parse_url($url, PHP_URL_PATH))));
    }
}
