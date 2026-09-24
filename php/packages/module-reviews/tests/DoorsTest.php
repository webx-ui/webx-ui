<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;

/**
 * A reviews block written through both doors of a page (§4.11, CLAUDE.md §4 «Валидатор, который
 * собирает объект заново»): the editor's save and an agent's `blocks_edit_content`. What is kept
 * is what `wx-collection` makes of the choice for this source — categories yes, markup never,
 * because the source prints none (decision 3).
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

        $this->page = new Page(['title' => 'About us', 'slug' => 'about']);
        $this->page->appendTo($home);
    }

    #[Test]
    public function the_editors_save_keeps_the_choice_cleaned(): void
    {
        $clinic = $this->category('Clinic');
        $implants = $this->category('Implants');

        $this->actingAs($this->editor(['pages.view', 'pages.manage']), 'cms')
            ->putJson('/api/cms/pages/'.$this->page->getKey(), [
                'values' => [
                    'blocks' => [[
                        'key' => 'k1',
                        'type' => 'reviews',
                        'values' => [
                            'reviews' => [
                                'categories' => [(string) $implants->id, $clinic->id, $implants->id],
                                'limit' => '6',
                                'filter' => true,
                                'markup' => true,
                                'source' => 'faq',
                            ],
                            'layout' => 'slider',
                        ],
                    ]],
                ],
            ])
            ->assertOk();

        $values = $this->page->refresh()->draft['blocks'][0]['values'];

        $this->assertSame(
            ['categories' => [$clinic->id, $implants->id], 'limit' => 6, 'filter' => true, 'markup' => null],
            $values['reviews'],
        );
        $this->assertSame('slider', $values['layout']);
    }

    #[Test]
    public function an_agents_edit_keeps_the_choice_cleaned(): void
    {
        $clinic = $this->category('Clinic');

        $this->page->blocks = [['key' => 'k-reviews', 'type' => 'reviews', 'values' => []]];
        $this->page->save();

        $this->agent('edit_content', [
            'entity' => 'page',
            'id' => $this->page->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k-reviews', 'values' => [
                'reviews' => ['categories' => [$clinic->id, $clinic->id], 'limit' => 500, 'filter' => 'yes', 'markup' => true],
                'layout' => 'marquee',
            ]]],
        ], $this->editor(['pages.view', 'pages.manage', 'blocks.manage']))->assertOk();

        $values = $this->page->refresh()->draft['blocks'][0]['values'];

        $this->assertSame(
            ['categories' => [$clinic->id], 'limit' => 100, 'filter' => false, 'markup' => null],
            $values['reviews'],
        );
        $this->assertSame('marquee', $values['layout']);
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
