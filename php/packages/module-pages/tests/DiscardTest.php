<?php

declare(strict_types=1);

namespace WebxUi\Pages\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;

/**
 * Back to what the site shows: the draft goes, the page stays on the site as it is, and no
 * version is written — nothing was published.
 */
final class DiscardTest extends TestCase
{
    #[Test]
    public function the_panel_drops_the_draft_and_answers_the_page_as_published(): void
    {
        $page = $this->page('about');
        $page->saveDraft(['title' => ['en' => 'About us'], 'slug' => ['en' => 'about']]);
        $versions = $page->versions()->published()->count();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api($page->getKey()).'/discard')
            ->assertOk()
            ->assertJsonPath('data.page.status', Page::STATUS_PUBLISHED)
            ->assertJsonPath('data.values.title.en', 'About');

        $this->assertFalse($page->refresh()->hasDraft());
        $this->assertSame($versions, $page->versions()->published()->count());
    }

    #[Test]
    public function an_agent_discards_and_a_dry_run_names_what_would_go(): void
    {
        $page = $this->page('about');

        $this->agent(['page' => '/about'])->assertHasErrors(['has no draft']);

        $page->saveDraft(['title' => ['en' => 'About us'], 'slug' => ['en' => 'about']]);

        // The title is what changed; the slug the draft carries is the one on the site.
        $this->agent(['page' => '/about', 'dry_run' => true])->assertOk()->assertSee('"would_discard":["title"]');
        $this->assertTrue($page->refresh()->hasDraft(), 'a dry run changes nothing');

        $this->agent(['page' => '/about'])->assertOk();
        $this->assertFalse($page->refresh()->hasDraft());
        $this->assertSame('About', $page->title);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(array $arguments): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('pages_discard'));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }
}
