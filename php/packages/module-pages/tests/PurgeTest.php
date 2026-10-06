<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Models\Route;
use WebxUi\Routing\Models\TrashedAlias;

/**
 * Deleting for good (§7): only out of the bin, the whole branch, and nothing of it left behind —
 * no address, no former address kept for a restore, no SEO card. The history goes through
 * `HasVersions` itself, and is tested where it lives (module-admin).
 */
final class PurgeTest extends TestCase
{
    #[Test]
    public function a_purge_takes_the_branch_and_everything_it_owned(): void
    {
        $catalog = $this->page('catalog');
        $shoes = $this->page('shoes', $catalog);
        $other = $this->page('other');

        // A former address, which the bin keeps aside for a restore, and a card of its own.
        $catalog->saveDraft(['slug' => ['en' => 'shop']]);
        $catalog->publish();
        $catalog->saveSeo(['title' => ['en' => 'The shop']]);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($catalog->getKey()))->assertOk();
        // Both pages had /catalog… before the rename: each keeps its former address aside.
        $this->assertSame(2, TrashedAlias::query()->count());

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api($catalog->getKey()).'/purge')
            ->assertOk()
            ->assertJsonPath('data.purged', 2);

        $ids = [$catalog->getKey(), $shoes->getKey()];

        $this->assertSame(0, Page::withTrashed()->whereIn('id', $ids)->count());
        $this->assertSame(0, Route::query()->where('entity_type', $catalog->getMorphClass())->whereIn('entity_id', $ids)->count());
        $this->assertSame(0, TrashedAlias::query()->count());
        $this->assertSame(0, DB::table('seo_meta')->whereIn('entity_id', $ids)->count());

        // The tree closed over the gap: the page beside it still sits where the tree says.
        $this->assertSame('other', $other->refresh()->routeCanonical('en')?->path);
        $home = Page::home();
        $this->assertSame(3, $home->getRgt() - $home->getLft(), 'home and one page under it');
    }

    #[Test]
    public function a_live_page_is_refused_and_the_bin_empties_at_once(): void
    {
        $about = $this->page('about');

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api($about->getKey()).'/purge')
            ->assertStatus(422);

        $this->assertNotNull(Page::query()->find($about->getKey()));

        $this->page('a')->delete();
        $this->page('b')->delete();

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api('bin'))
            ->assertOk()
            ->assertJsonPath('data.purged', 2);

        $this->assertSame(0, Page::onlyTrashed()->count());
        $this->assertNotNull(Page::query()->find($about->getKey()));
    }

    #[Test]
    public function an_agent_purges_by_id_and_a_dry_run_names_what_would_go(): void
    {
        $catalog = $this->page('catalog');
        $this->page('shoes', $catalog);

        $this->agent(['page' => $catalog->getKey()])
            ->assertHasErrors(['is not in the bin']);

        $catalog->delete();

        $dry = $this->agent(['page' => $catalog->getKey(), 'dry_run' => true])->assertOk();
        $this->assertSame(2, Page::onlyTrashed()->count(), 'a dry run deletes nothing');
        $dry->assertSee(['"would_purge":2', 'Shoes']);

        $this->agent(['page' => $catalog->getKey()])->assertOk()->assertSee('"purged":2');
        $this->assertSame(0, Page::onlyTrashed()->count());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(array $arguments, ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('pages_purge'));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }
}
