<?php

declare(strict_types=1);

namespace WebxUi\Faq\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;

/**
 * A FAQ block written through both doors of a page (§4.10, CLAUDE.md §4 «Валидатор, который
 * собирает объект заново»): the editor's save and an agent's `blocks_edit_content`. What is kept
 * is what `wx-collection` makes of the choice for this source — which has categories and markup,
 * so both survive, cleaned.
 */
final class DoorsTest extends TestCase
{
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installBlock();

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $this->page = new Page(['title' => 'Help', 'slug' => 'help']);
        $this->page->appendTo($home);
    }

    #[Test]
    public function the_editors_save_keeps_the_choice_cleaned(): void
    {
        $billing = $this->category('Billing');
        $delivery = $this->category('Delivery');

        $this->actingAs($this->editor(['pages.view', 'pages.manage']), 'cms')
            ->putJson('/api/cms/pages/'.$this->page->getKey(), [
                'values' => [
                    'blocks' => [[
                        'key' => 'k1',
                        'type' => 'faq',
                        'values' => ['questions' => [
                            'categories' => [(string) $delivery->id, $billing->id, $delivery->id],
                            'limit' => '5',
                            'filter' => true,
                            'markup' => true,
                            'source' => 'reviews',
                        ]],
                    ]],
                ],
            ])
            ->assertOk();

        $this->assertSame(
            ['categories' => [$billing->id, $delivery->id], 'limit' => 5, 'filter' => true, 'markup' => true],
            $this->page->refresh()->draft['blocks'][0]['values']['questions'],
        );
    }

    #[Test]
    public function an_agents_edit_keeps_the_choice_cleaned(): void
    {
        $billing = $this->category('Billing');

        $this->page->blocks = [['key' => 'k-faq', 'type' => 'faq', 'values' => []]];
        $this->page->save();

        $this->agent('edit_content', [
            'entity' => 'page',
            'id' => $this->page->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k-faq', 'values' => [
                'questions' => ['categories' => [$billing->id, $billing->id], 'limit' => 500, 'filter' => 'yes', 'markup' => null],
            ]]],
        ], $this->editor(['pages.view', 'pages.manage', 'blocks.manage']))->assertOk();

        $this->assertSame(
            ['categories' => [$billing->id], 'limit' => 100, 'filter' => false, 'markup' => null],
            $this->page->refresh()->draft['blocks'][0]['values']['questions'],
        );
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
