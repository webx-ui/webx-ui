<?php

declare(strict_types=1);

namespace WebxUi\Snippets\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Snippets\Models\Snippet;

/**
 * A dry run is the write, rolled back: refused where the write would be, answering what it would
 * answer, and leaving the table — and the site — as it was.
 */
final class McpDryRunTest extends TestCase
{
    #[Test]
    public function create_answers_with_its_warnings_and_keeps_nothing(): void
    {
        $dry = $this->content($this->agent('snippets_create', [
            'name' => 'Chat',
            'place' => 'body_end',
            'code' => '<body><script>chat()</script>',
            'enabled' => true,
            'dry_run' => true,
        ]));

        $this->assertTrue($dry['dry_run']);
        $this->assertNull($dry['snippet']['id']);
        $this->assertTrue($dry['values']['enabled']);
        $this->assertCount(1, $dry['warnings']);
        $this->assertSame(0, Snippet::withTrashed()->count());
    }

    #[Test]
    public function a_refused_value_is_refused_in_the_dry_run_too(): void
    {
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'sidebar', 'code' => '<script></script>', 'dry_run' => true])
            ->assertHasErrors(['Place [sidebar] is not declared']);
        $this->agent('snippets_create', ['name' => 'Chat', 'place' => 'head', 'code' => ' ', 'dry_run' => true])
            ->assertHasErrors(['code:']);
    }

    #[Test]
    public function update_delete_and_reorder_change_nothing(): void
    {
        $a = $this->snippet('head', '<script>a</script>', ['enabled' => false]);
        $b = $this->snippet('head', '<script>b</script>');

        $updated = $this->content($this->agent('snippets_update', ['snippet' => $a->id, 'enabled' => true, 'code' => '<script>new</script>', 'dry_run' => true]));
        $this->assertTrue($updated['dry_run']);
        $this->assertSame(['enabled', 'code'], $updated['fields']);
        $this->assertTrue($updated['values']['enabled']);
        $this->assertFalse($a->refresh()->enabled);
        $this->assertSame('<script>a</script>', $a->code);

        $this->agent('snippets_delete', ['snippet' => $b->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($b->refresh()->trashed());

        $ordered = $this->content($this->agent('snippets_reorder', ['place' => 'head', 'snippets' => [$b->id, $a->id], 'dry_run' => true]));
        $this->assertSame([$b->id, $a->id], $ordered['would_order']);
        $this->assertSame([$a->id, $b->id], Snippet::query()->where('place', 'head')->orderBy('position')->pluck('id')->all());
    }

    #[Test]
    public function the_site_prints_what_it_printed_before_the_dry_run(): void
    {
        $this->snippet('body_end', '<i>kept</i>');
        $this->at('/');
        $this->assertSame('<i>kept</i>', Blade::render('<x-webx-snippets place="body_end" />'));

        $this->agent('snippets_create', ['name' => 'More', 'place' => 'body_end', 'code' => '<i>added</i>', 'enabled' => true, 'on_stage' => true, 'dry_run' => true])->assertOk();

        // The rehearsal forgot the cached list; built again, it has only what the table has.
        $this->assertStringNotContainsString('<i>added</i>', Blade::render('<x-webx-snippets place="body_end" />'));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->admin(), 'cms')->tool($bound, $arguments);
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
