<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\Rendering\Renderer;
use WebxUi\Pages\Models\Page;

/**
 * The block "Recipes" (§5.8): a showcase with a link to all of them, or the whole catalogue with
 * pages and the nutrient filter — the module's own catalogue fragment either way. Written through
 * the real doors (CLAUDE.md §4 «Валидатор, который собирает объект заново»).
 */
final class BlockTest extends TestCase
{
    #[Test]
    public function the_offered_type_is_installed_and_draws_on_its_sample(): void
    {
        $block = $this->installBlock()->load('publishedVersion');

        $this->assertSame('Recipes', $block->title);
        $this->assertSame(['title', 'recipes', 'mode', 'per_page', 'columns'], array_column((array) $block->publishedVersion?->schema, 'id'));

        $sample = (array) $block->publishedVersion?->sample;

        $this->assertStringContainsString('No recipes here yet.', $this->render([$this->node($sample)]), 'nothing is on the site yet');

        foreach (['a', 'b', 'c', 'd'] as $slug) {
            $this->recipe($slug);
        }

        $html = $this->render([$this->node($sample)]);

        // Three on the sample, and a link to all of them — the index, while it is on.
        $this->assertSame(3, substr_count($html, 'class="wx-recipes__card"'));
        $this->assertStringContainsString('href="http://localhost/recipes">All recipes</a>', $html);
        $this->assertStringNotContainsString('wx-recipes__pages', $html);
    }

    #[Test]
    public function an_untouched_view_is_the_showcase(): void
    {
        $this->installBlock();

        foreach (['a', 'b'] as $slug) {
            $this->recipe($slug);
        }

        $html = $this->render([$this->node(['recipes' => ['limit' => 1]])]);

        $this->assertSame(1, substr_count($html, 'class="wx-recipes__card"'));
        $this->assertStringContainsString('All recipes', $html);
    }

    #[Test]
    public function the_catalogue_on_a_page_has_pages_and_a_filter_and_keeps_the_filtered_copy_out_of_the_index(): void
    {
        $this->installBlock();

        $iron = $this->nutrient('Iron');

        foreach (['a', 'b', 'c'] as $slug) {
            $this->recipe($slug)->syncCategories([$iron->id], 'nutrients');
        }

        $this->recipe('d');

        $this->page([$this->node(['title' => 'All our recipes', 'recipes' => [], 'mode' => 'catalog', 'per_page' => 2])]);

        $first = (string) $this->get('/cookbook')->assertOk()->getContent();

        $this->assertStringContainsString('All our recipes', $first);
        $this->assertSame(2, substr_count($first, 'class="wx-recipes__card"'));
        $this->assertStringContainsString('href="http://localhost/cookbook?page=2" rel="next"', $first);
        $this->assertStringNotContainsString('All recipes</a>', $first);

        $second = (string) $this->get('/cookbook?page=2')->assertOk()->getContent();

        $this->assertStringContainsString('href="http://localhost/recipes/c"', $second);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/cookbook?page=2">', $second);

        $filtered = (string) $this->get('/cookbook?nutrient='.$iron->id.'&page=2')->assertOk()->getContent();

        $this->assertSame(1, substr_count($filtered, 'class="wx-recipes__card"'));
        $this->assertStringNotContainsString('recipes/d"', $filtered);
        $this->assertStringContainsString('<meta name="robots" content="noindex, follow">', $filtered);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/cookbook?page=2">', $filtered);

    }

    #[Test]
    public function the_showcase_of_one_services_recipes_is_written_by_the_editor_and_read_by_the_site(): void
    {
        $this->installBlock();

        $implants = $this->service('implants');
        $mine = $this->recipe('mine');
        $mine->syncRelated('services', 'service', [$implants->id]);
        $this->recipe('other');

        $page = $this->page([]);

        $this->actingAs($this->editor(['pages.view', 'pages.manage']), 'cms')
            ->putJson('/api/cms/pages/'.$page->getKey(), ['values' => ['blocks' => [[
                'key' => 'k1',
                'type' => 'recipes',
                'values' => [
                    'recipes' => [
                        'categories' => [],
                        'limit' => 3,
                        'related' => ['type' => 'service', 'ids' => [(string) $implants->id, $implants->id]],
                    ],
                ],
            ]]]])
            ->assertOk();

        $page->refresh();

        $this->assertSame(
            ['categories' => [], 'limit' => 3, 'filter' => false, 'markup' => null, 'related' => ['type' => 'service', 'ids' => [$implants->id]]],
            $page->draft['blocks'][0]['values']['recipes'] ?? null,
        );

        $page->publish();

        $html = (string) $this->get('/cookbook')->assertOk()->getContent();

        $this->assertStringContainsString('href="http://localhost/recipes/mine"', $html);
        $this->assertStringNotContainsString('recipes/other', $html);
        // No markup of its own: `Recipe` belongs to the recipe's page.
        $this->assertStringNotContainsString('"@type":"Recipe"', $html);
    }

    #[Test]
    public function on_a_services_page_it_shows_the_recipes_of_that_service(): void
    {
        $this->installBlock();

        $implants = $this->service('implants');
        $crowns = $this->service('crowns');
        $this->recipe('for-implants')->syncRelated('services', 'service', [$implants->id]);
        $this->recipe('for-crowns')->syncRelated('services', 'service', [$crowns->id]);

        foreach ([$implants, $crowns] as $service) {
            $service->blocks = [[
                'key' => 'k1',
                'type' => 'recipes',
                'values' => ['recipes' => ['related' => ['type' => 'service', 'ids' => [], 'current' => true]]],
            ]];
            $service->save();
        }

        $html = (string) $this->get('/services/implants')->assertOk()->getContent();

        $this->assertStringContainsString('recipes/for-implants"', $html);
        $this->assertStringNotContainsString('recipes/for-crowns"', $html);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function render(array $blocks): string
    {
        return (string) $this->app->make(Renderer::class)->render($blocks);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function node(array $values = []): array
    {
        static $count = 0;
        $count++;

        return ['key' => "k{$count}", 'type' => 'recipes', 'values' => $values];
    }

    /**
     * A published page at `/cookbook` with these blocks.
     *
     * @param  list<array<string, mixed>>  $blocks
     */
    private function page(array $blocks): Page
    {
        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);

        $page = new Page(['title' => 'Cookbook', 'slug' => 'cookbook', 'blocks' => $blocks]);
        $page->appendTo($home);
        $page->publish();

        return $page->refresh();
    }
}
