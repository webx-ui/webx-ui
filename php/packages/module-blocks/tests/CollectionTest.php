<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Collections\CollectionSources;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\Blocks\Tests\Fixtures\QuoteSource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A block that shows another section's records (§3 of the FAQ spec): what an agent writes into
 * it is kept as the field type keeps it, and what the page prints is the records — or nothing,
 * quietly, once the section is gone.
 */
final class CollectionTest extends TestCase
{
    private QuoteSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $this->source = new QuoteSource;
        $this->app->make(CollectionSources::class)->register($this->source);

        $this->publish('quotes', '<ul data-wx-block="quotes">@foreach ($list[\'items\'] as $quote)<li id="{{ $quote[\'anchor\'] }}">{{ $quote[\'words\'] }}</li>@endforeach</ul>', [], [
            'schema' => [['id' => 'list', 'type' => 'wx-collection', 'props' => ['source' => 'quotes']]],
        ]);
    }

    #[Test]
    public function what_an_agent_writes_into_the_field_is_kept_as_the_type_keeps_it(): void
    {
        $page = Page::query()->create([
            'title' => 'Sayings',
            'slug' => 'sayings',
            'blocks' => [$this->node('quotes', [], 'k-quotes')],
        ]);

        // Through the tool rather than the walk: the question is whether this door runs the
        // type at all. A source without categories keeps none; a limit that came as a string is
        // a number; a key the value does not have is not kept.
        $this->agent('edit_content', [
            'entity' => 'note',
            'id' => $page->id,
            'ops' => [['op' => 'set', 'key' => 'k-quotes', 'values' => [
                'list' => ['categories' => [3], 'limit' => '2', 'filter' => true, 'markup' => false, 'source' => 'faq'],
            ]]],
        ], $this->editor())->assertOk();

        $this->assertSame(
            ['categories' => [], 'limit' => 2, 'filter' => true, 'markup' => false],
            $page->refresh()->draft['blocks'][0]['values']['list'],
        );
    }

    #[Test]
    public function the_page_prints_the_records_and_nothing_once_the_source_is_gone(): void
    {
        $blocks = [$this->node('quotes', ['list' => ['categories' => [], 'limit' => 2, 'filter' => false, 'markup' => null]])];

        $html = $this->render($blocks);

        $this->assertStringContainsString('<li id="brevity">Brevity is the soul of wit</li>', $html);
        $this->assertStringContainsString('<li id="less">Less is more</li>', $html);
        $this->assertStringNotContainsString('Enough said', $html);
        $this->assertTrue($this->source->asked?->markup, 'no category chosen: markup by default');

        // A block put on the page and never touched still shows the collection.
        $this->assertStringContainsString('Enough said', $this->render([$this->node('quotes')]));

        $this->app->make(CollectionSources::class)->forget();

        $this->assertStringContainsString('<ul data-wx-block="quotes"></ul>', $this->render($blocks));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool('blocks_'.$tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
    }
}
