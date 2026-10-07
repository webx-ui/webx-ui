<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;
use WebxUi\Services\Models\Service;

/**
 * A team block written through both doors of a page and of a service (§5.11, CLAUDE.md §4
 * «Валидатор, который собирает объект заново»): the editor's save and an agent's
 * `blocks_edit_content`. What is kept is what `wx-collection` makes of the choice for this source —
 * no categories and no markup ever, because the source has neither; a relation to services, and
 * "the service of this page".
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
        $implants = $this->service('implants');

        $this->actingAs($this->editor(['pages.view', 'pages.manage']), 'cms')
            ->putJson('/api/cms/pages/'.$this->page->getKey(), [
                'values' => [
                    'blocks' => [[
                        'key' => 'k1',
                        'type' => 'team',
                        'values' => [
                            'team' => [
                                'categories' => [],
                                'limit' => 6,
                                'filter' => true,
                                'markup' => true,
                                'related' => ['type' => 'service', 'ids' => [(string) $implants->id, $implants->id]],
                            ],
                            'layout' => 'list',
                        ],
                    ]],
                ],
            ])
            ->assertOk();

        $values = $this->page->refresh()->draft['blocks'][0]['values'];

        $this->assertSame(
            ['categories' => [], 'limit' => 6, 'filter' => true, 'markup' => null, 'related' => ['type' => 'service', 'ids' => [$implants->id]]],
            $values['team'],
        );
        $this->assertSame('list', $values['layout']);
    }

    #[Test]
    public function an_agents_edit_is_refused_when_dirty_and_kept_cleaned_when_not(): void
    {
        $this->page->blocks = [['key' => 'k-team', 'type' => 'team', 'values' => []]];
        $this->page->save();

        $this->agent('edit_content', [
            'entity' => 'page',
            'id' => $this->page->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k-team', 'values' => [
                'team' => ['categories' => [1], 'limit' => 500, 'related' => ['type' => 'recipe', 'ids' => [1]]],
            ]]],
        ], $this->editor(['pages.view', 'pages.manage', 'blocks.manage']))->assertHasErrors(['field [team]']);

        $this->agent('edit_content', [
            'entity' => 'page',
            'id' => $this->page->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k-team', 'values' => [
                'team' => ['categories' => [], 'limit' => 100, 'markup' => true, 'related' => null],
                'layout' => 'slider',
            ]]],
        ], $this->editor(['pages.view', 'pages.manage', 'blocks.manage']))->assertOk();

        $values = $this->page->refresh()->draft['blocks'][0]['values'];

        $this->assertSame(
            ['categories' => [], 'limit' => 100, 'filter' => false, 'markup' => null, 'related' => null],
            $values['team'],
            'markup the source does not have is never kept',
        );
        $this->assertSame('slider', $values['layout']);
    }

    #[Test]
    public function the_current_service_survives_both_doors_of_a_service(): void
    {
        $service = $this->service('implants');
        $current = ['type' => 'service', 'ids' => [], 'current' => true];

        $this->actingAs($this->editor(['services.view', 'services.manage']), 'cms')
            ->putJson('/api/cms/services/'.$service->getKey(), [
                'values' => ['blocks' => [['key' => 'k1', 'type' => 'team', 'values' => ['team' => ['related' => $current]]]]],
            ])
            ->assertOk();

        $this->assertSame($current, $this->draftOf($service)['blocks'][0]['values']['team']['related'] ?? null);

        $this->agent('edit_content', [
            'entity' => 'service',
            'id' => $service->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['team' => ['related' => $current, 'limit' => 3]]]],
        ], $this->editor(['services.view', 'services.manage', 'blocks.manage']))->assertOk();

        $team = $this->draftOf($service)['blocks'][0]['values']['team'] ?? null;
        $this->assertSame($current, $team['related'] ?? null);
        $this->assertSame(3, $team['limit'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftOf(Service $service): array
    {
        $draft = $service->refresh()->draft;

        return is_array($draft) ? $draft : [];
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
