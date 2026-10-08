<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Pages\Models\Page;
use WebxUi\Services\Models\Service;

/**
 * A tariffs block written through both doors of a page and of a service (§5.8, CLAUDE.md §4
 * «Валидатор, который собирает объект заново»): the editor's save and an agent's
 * `blocks_edit_content`. What is kept is what `wx-collection` makes of the choice for this source —
 * groups, a relation to services and "the service of this page", and never markup.
 */
final class DoorsTest extends TestCase
{
    private Page $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installBlock();
        $this->pricing = $this->page('pricing', published: false);
    }

    #[Test]
    public function the_editors_save_keeps_the_choice_cleaned(): void
    {
        $business = $this->group('For business');
        $seo = $this->service('seo');

        $this->actingAs($this->editor(['pages.view', 'pages.manage']), 'cms')
            ->putJson('/api/cms/pages/'.$this->pricing->getKey(), [
                'values' => [
                    'blocks' => [[
                        'key' => 'k1',
                        'type' => 'tariffs',
                        'values' => [
                            'tariffs' => [
                                'categories' => [(string) $business->id],
                                'limit' => 3,
                                'filter' => true,
                                'markup' => true,
                                'related' => ['type' => 'service', 'ids' => [(string) $seo->id, $seo->id]],
                            ],
                            'layout' => 'grid',
                        ],
                    ]],
                ],
            ])
            ->assertOk();

        $values = $this->pricing->refresh()->draft['blocks'][0]['values'];

        $this->assertSame(
            ['categories' => [$business->id], 'limit' => 3, 'filter' => true, 'markup' => null, 'related' => ['type' => 'service', 'ids' => [$seo->id]]],
            $values['tariffs'],
        );
        $this->assertSame('grid', $values['layout']);
    }

    #[Test]
    public function an_agents_edit_is_refused_when_dirty_and_kept_cleaned_when_not(): void
    {
        $business = $this->group('For business');
        $this->pricing->blocks = [['key' => 'k-tariffs', 'type' => 'tariffs', 'values' => []]];
        $this->pricing->save();

        $this->agent('edit_content', ['force' => true,
            'entity' => 'page',
            'id' => $this->pricing->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k-tariffs', 'values' => [
                'tariffs' => ['limit' => 500, 'related' => ['type' => 'recipe', 'ids' => [1]]],
            ]]],
        ], $this->editor(['pages.view', 'pages.manage', 'blocks.manage']))->assertHasErrors(['field [tariffs]']);

        $this->agent('edit_content', ['force' => true,
            'entity' => 'page',
            'id' => $this->pricing->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k-tariffs', 'values' => [
                'tariffs' => ['categories' => [$business->id], 'limit' => 100, 'markup' => true, 'related' => null],
                'layout' => 'slider',
            ]]],
        ], $this->editor(['pages.view', 'pages.manage', 'blocks.manage']))->assertOk();

        $values = $this->pricing->refresh()->draft['blocks'][0]['values'];

        $this->assertSame(
            ['categories' => [$business->id], 'limit' => 100, 'filter' => false, 'markup' => null, 'related' => null],
            $values['tariffs'],
            'markup is never kept',
        );
        $this->assertSame('slider', $values['layout']);
    }

    #[Test]
    public function the_current_service_survives_both_doors_of_a_service(): void
    {
        $service = $this->service('seo');
        $current = ['type' => 'service', 'ids' => [], 'current' => true];

        $this->actingAs($this->editor(['services.view', 'services.manage']), 'cms')
            ->putJson('/api/cms/services/'.$service->getKey(), [
                'values' => ['blocks' => [['key' => 'k1', 'type' => 'tariffs', 'values' => ['tariffs' => ['related' => $current]]]]],
            ])
            ->assertOk();

        $this->assertSame($current, $this->draftOf($service)['blocks'][0]['values']['tariffs']['related'] ?? null);

        $this->agent('edit_content', ['force' => true,
            'entity' => 'service',
            'id' => $service->getKey(),
            'ops' => [['op' => 'set', 'key' => 'k1', 'values' => ['tariffs' => ['related' => $current, 'limit' => 3]]]],
        ], $this->editor(['services.view', 'services.manage', 'blocks.manage']))->assertOk();

        $tariffs = $this->draftOf($service)['blocks'][0]['values']['tariffs'] ?? null;
        $this->assertSame($current, $tariffs['related'] ?? null);
        $this->assertSame(3, $tariffs['limit'] ?? null);
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
