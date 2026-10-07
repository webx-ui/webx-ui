<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Press\Models\Article;
use WebxUi\Press\Models\Outlet;

/**
 * A dry run is the write, rolled back — the form's refusal of a row included; one name is one
 * outlet; what the tools do not take is refused; the bin can be undone and emptied by an agent.
 */
final class McpDryRunTest extends TestCase
{
    #[Test]
    public function the_dry_run_of_an_article_is_refused_where_the_form_refuses_it(): void
    {
        $outlet = $this->outlet('Daily News');

        // No address and no PDF: the form refuses the row.
        $this->agent('press_articles_add', ['outlet' => $outlet->id, 'title' => 'Interview', 'dry_run' => true])->assertHasErrors(['new article']);

        $dry = $this->content($this->agent('press_articles_add', ['outlet' => $outlet->id, 'title' => 'Interview', 'url' => 'https://news.example/a', 'dry_run' => true]));

        $this->assertTrue($dry['dry_run']);
        $this->assertNull($dry['article']['id']);
        $this->assertSame(0, Article::query()->count());
    }

    #[Test]
    public function a_second_outlet_by_one_name_is_refused(): void
    {
        $daily = $this->outlet('Daily News');

        $this->agent('press_create', ['title' => 'daily news'])->assertHasErrors(['#'.$daily->id]);
        $this->agent('press_create', ['title' => 'Daily News', 'dry_run' => true])->assertHasErrors(['already exists']);

        $weekly = $this->outlet('Weekly');

        $this->agent('press_update', ['outlet' => $weekly->id, 'values' => ['title' => 'Daily News']])->assertHasErrors(['already exists']);
        $this->assertSame(2, Outlet::query()->count());
    }

    #[Test]
    public function unknown_arguments_and_fields_are_refused(): void
    {
        $outlet = $this->outlet('Daily News', [$this->row('First')]);
        $article = $outlet->articles()->firstOrFail();

        $this->agent('press_create', ['title' => 'Weekly', 'country' => 'DE'])->assertHasErrors(['press_create has no argument [country]']);
        $this->agent('press_create', ['title' => 'Weekly', 'articles' => [['title' => 'A', 'link' => 'https://x.example']]])
            ->assertHasErrors(['press_create articles[0] has no field [link]']);
        $this->agent('press_update', ['outlet' => $outlet->id, 'values' => ['country' => 'DE']])->assertHasErrors(['press_update has no field [country]']);
        $this->agent('press_articles_update', ['article' => $article->id, 'values' => ['link' => 'https://x.example']])
            ->assertHasErrors(['press_articles_update has no field [link]']);
    }

    #[Test]
    public function an_outlet_in_the_bin_is_restored_or_purged_with_its_articles(): void
    {
        $kept = $this->outlet('Daily News', [$this->row('First')]);
        $gone = $this->outlet('Weekly', [$this->row('Second')]);

        $this->agent('press_purge', ['outlet' => $kept->id])->assertHasErrors(['not in the bin']);

        $kept->delete();
        $gone->delete();

        $restored = $this->content($this->agent('press_restore', ['outlet' => $kept->id]));

        $this->assertCount(1, $restored['articles']);
        $this->assertFalse($kept->refresh()->trashed());

        $this->agent('press_purge', ['outlet' => $gone->id])->assertOk();
        $this->assertNull(Outlet::withTrashed()->find($gone->id));
        $this->assertSame(1, Article::query()->count());
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
