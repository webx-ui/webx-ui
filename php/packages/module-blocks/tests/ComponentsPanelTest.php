<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlockComponents;
use WebxUi\Blocks\BlockShapes;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Tests\Fixtures\Page;

/**
 * Components through the section's API and the import: the graph, the checks before publishing,
 * what cannot be deleted, and "Customise" on a place a module declared.
 */
final class ComponentsPanelTest extends TestCase
{
    private string $views;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-blocks.entities', [Page::class]);

        $this->views = storage_path('framework/testing/components-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->views.'/partials');
        File::put($this->views.'/partials/card.blade.php', '<article class="std">{{ $card["title"] }}</article>');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->views);
        File::deleteDirectory(resource_path('views/vendor/webx-demo'));

        parent::tearDown();
    }

    #[Test]
    public function publishing_a_child_that_breaks_its_parent_on_a_page_is_refused_naming_both(): void
    {
        $badge = $this->publish('badge', '<b>{{ $label }}</b>', ['kind' => 'component'], [
            'schema' => [['id' => 'label', 'type' => 'wx-input']],
            'sample' => ['label' => 'New'],
        ]);
        $this->publish('hero', '<section><x-webx-block type="badge" :label="$title" /></section>', [], [
            'sample' => ['title' => 'Sample'],
        ]);
        Page::query()->create(['title' => 'Home', 'blocks' => [$this->node('hero', ['title' => 'Hi'])]]);

        // Fine on its own sample and on the parent's; only the page's value breaks it.
        $badge->saveVersion(['template' => "<b>\n{{ \$label === 'Hi' ? intdiv(1, 0) : \$label }}</b>"]);

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api($badge->id.'/publish'))->assertStatus(422);

        $this->assertSame('hero', $response->json('parent.slug'));
        $this->assertSame('Home', $response->json('entity.title'));
        $this->assertSame(2, $response->json('line'));
        $this->assertStringStartsWith('Breaks "Hero" on "Home":', (string) $response->json('errors.template.0'));
        $this->assertSame(1, $badge->refresh()->publishedVersion?->number);
    }

    #[Test]
    public function a_grandparent_is_checked_through_the_parent(): void
    {
        $badge = $this->publish('badge', '<b>ok</b>', ['kind' => 'component']);
        $this->publish('card', '<i><x-webx-block type="badge" /></i>', ['kind' => 'component']);
        $this->publish('grid', '<ul><x-webx-block type="card" /></ul>');

        $badge->saveVersion(['template' => '<b>{{ $block->key === "sample/card-1/badge-1" ? intdiv(1, 0) : "ok" }}</b>']);

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api($badge->id.'/publish'))->assertStatus(422);

        $this->assertContains($response->json('parent.slug'), ['card', 'grid']);
    }

    #[Test]
    public function a_circle_of_calls_is_refused_at_publishing(): void
    {
        $card = $this->publish('card', '<i>card</i>', ['kind' => 'component']);
        $this->publish('badge', '<b><x-webx-block type="card" /></b>', ['kind' => 'component']);

        $card->saveVersion(['template' => '<i><x-webx-block type="badge" /></i>']);

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api($card->id.'/publish'))->assertStatus(422);

        $this->assertSame(['card', 'badge', 'card'], $response->json('cycle'));
        $this->assertSame('The types call each other in a circle: card → badge → card.', $response->json('errors.template.0'));
    }

    #[Test]
    public function a_type_other_types_call_cannot_be_deleted(): void
    {
        $badge = $this->publish('badge', '<b></b>', ['kind' => 'component']);
        $hero = $this->publish('hero', '<x-webx-block type="badge" />');

        $response = $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($badge->id))->assertStatus(422);

        $this->assertSame([['id' => $hero->id, 'slug' => 'hero', 'title' => 'Hero']], $response->json('used_by'));
        $this->assertSame(['Called by "Hero" (hero)'], $response->json('errors.used_by'));
        $this->assertNotNull(Block::query()->find($badge->id));

        // A draft that calls it is nobody's parent yet.
        $hero->saveVersion(['template' => '<p></p>']);
        $hero->publish();

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($badge->id))->assertNoContent();
    }

    #[Test]
    public function a_block_on_pages_does_not_become_a_component_but_the_other_way_is_fine(): void
    {
        $hero = $this->publish('hero', '<p></p>');
        Page::query()->create(['title' => 'Home', 'blocks' => [$this->node('hero')]]);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($hero->id), ['kind' => 'component'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('kind');

        $badge = $this->publish('badge', '<b></b>', ['kind' => 'component']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($badge->id), ['kind' => 'block'])
            ->assertOk()
            ->assertJsonPath('data.kind', 'block');
    }

    #[Test]
    public function the_list_carries_kinds_the_graph_and_the_declared_places(): void
    {
        $this->declare();
        $this->publish('badge', '<b></b>', ['kind' => 'component']);
        $this->publish('hero', '<x-webx-block type="badge" />');

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api(''))->assertOk();

        $badge = $this->row($response->json('data'), 'badge');
        $hero = $this->row($response->json('data'), 'hero');

        $this->assertSame('component', $badge['kind']);
        $this->assertSame([['id' => $hero['id'], 'slug' => 'hero', 'title' => 'Hero']], $badge['used_by']);
        $this->assertSame(['badge'], $hero['uses']);
        $this->assertSame([[
            'slug' => 'recipe-card',
            'module' => 'recipes',
            'title' => 'Recipe card',
            'description' => 'One recipe in a list.',
            'fallback' => 'webx-demo::partials.card',
            'customised' => false,
        ]], $response->json('declared'));

        // The picker's list has no components in it.
        $catalog = $this->actingAs($this->editor(), 'cms')->getJson($this->api('catalog'))->assertOk();
        $this->assertSame(['hero'], array_column((array) $catalog->json('data'), 'slug'));
    }

    #[Test]
    public function customise_starts_a_draft_from_the_view_the_site_prints_and_publishes_nothing(): void
    {
        $this->declare();

        // The site published the module's view and changed it: that is where the draft starts.
        File::ensureDirectoryExists(resource_path('views/vendor/webx-demo/partials'));
        File::put(resource_path('views/vendor/webx-demo/partials/card.blade.php'), '<article class="site">{{ $card["title"] }}</article>');
        $this->loadViews();

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('components/recipe-card/customise'))
            ->assertCreated();

        $this->assertSame('component', $response->json('data.kind'));
        $this->assertSame('Recipe card', $response->json('data.title'));
        $this->assertNull($response->json('data.published'));
        $this->assertSame('<article class="site">{{ $card["title"] }}</article>', $response->json('data.content.template'));
        $this->assertSame(['card' => ['title' => 'Borscht']], $response->json('data.content.sample'));
        $this->assertSame('From webx-demo::partials.card', $response->json('data.draft.comment'));
        $this->assertSame(['recipes.card' => ['fields' => [['name' => 'title', 'type' => 'string', 'description' => 'What it is called']]]], $response->json('data.shape'));
        $this->assertSame('recipes', $response->json('data.declared.module'));

        // Still the view on the site until somebody publishes.
        $this->assertSame('<article class="site">Soup</article>', Blade::render(
            "@webxPart('recipe-card', ['card' => \$card], 'webx-demo::partials.card')",
            ['card' => ['title' => 'Soup']],
        ));

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('components/recipe-card/customise'))
            ->assertStatus(409)
            ->assertJsonPath('id', $response->json('data.id'));

        $this->actingAs($this->editor(), 'cms')->postJson($this->api('components/nothing/customise'))->assertNotFound();
    }

    #[Test]
    public function customise_takes_the_modules_view_when_the_site_did_not_publish_one(): void
    {
        $this->declare();
        $this->loadViews();

        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('components/recipe-card/customise'))
            ->assertCreated();

        $this->assertSame('<article class="std">{{ $card["title"] }}</article>', $response->json('data.content.template'));
    }

    #[Test]
    public function the_declared_place_is_checked_on_the_modules_data_before_publishing(): void
    {
        $this->declare();
        $this->loadViews();

        $card = $this->actingAs($this->editor(), 'cms')->postJson($this->api('components/recipe-card/customise'))->assertCreated();
        $block = Block::query()->findOrFail($card->json('data.id'));

        // The author's sample says nothing about what the module hands over.
        $block->saveVersion([
            'template' => '<div>{{ $card["title"] === "Borscht" ? intdiv(1, 0) : $card["title"] }}</div>',
            'sample' => ['card' => ['title' => 'Mine']],
        ]);

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api($block->id.'/publish'))->assertStatus(422);
        $this->assertSame('recipes', $response->json('declared'));

        $block->saveVersion(['template' => '<div class="mine">{{ $card["title"] }}</div>']);
        $this->actingAs($this->editor(), 'cms')->postJson($this->api($block->id.'/publish'))->assertOk();

        $this->assertSame('<div class="mine">Soup</div>', Blade::render(
            "@webxPart('recipe-card', ['card' => \$card], 'webx-demo::partials.card')",
            ['card' => ['title' => 'Soup']],
        ));

        // Deleting is "back to the standard look": the declared place is no parent.
        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($block->id))->assertNoContent();
        $this->assertSame('<article class="std">Soup</article>', Blade::render(
            "@webxPart('recipe-card', ['card' => \$card], 'webx-demo::partials.card')",
            ['card' => ['title' => 'Soup']],
        ));
    }

    #[Test]
    public function the_template_is_told_about_calls_it_cannot_make_or_cannot_be_followed(): void
    {
        $hero = Block::query()->create(['slug' => 'hero', 'title' => 'Hero']);
        $hero->saveVersion([
            'template' => "<div data-wx-block=\"hero\">\n<x-webx-block type=\"recpie-card\" />\n<x-webx-block :type=\"\$which\" />\n</div>",
            'schema' => [['id' => 'which', 'type' => 'wx-input']],
        ]);

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api($hero->id))->assertOk();

        $codes = array_column((array) $response->json('data.warnings'), 'line', 'code');

        $this->assertSame(['dynamic-call' => 3, 'unknown-call' => 2], $codes);
    }

    #[Test]
    public function import_publishes_what_is_called_first_and_refuses_a_circle_before_writing(): void
    {
        $dir = storage_path('framework/testing/blocks-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($dir);

        // Alphabetically the parent comes first; the graph puts it last.
        File::put("{$dir}/a-hero.json", (string) json_encode(['slug' => 'a-hero', 'title' => 'Hero', 'template' => '<x-webx-block type="z-badge" />']));
        File::put("{$dir}/z-badge.json", (string) json_encode(['slug' => 'z-badge', 'kind' => 'component', 'title' => 'Badge', 'template' => '<b>{{ $block->type }}</b>']));

        $this->artisan('webx:blocks:import', ['--path' => $dir, '--publish' => true])->assertSuccessful();

        $this->assertSame('component', Block::query()->where('slug', 'z-badge')->value('kind'));
        $this->assertSame('block', Block::query()->where('slug', 'a-hero')->value('kind'));
        $this->assertSame(['z-badge'], Block::query()->where('slug', 'a-hero')->firstOrFail()->publishedVersion?->calls());

        File::put("{$dir}/z-badge.json", (string) json_encode(['slug' => 'z-badge', 'kind' => 'component', 'title' => 'Badge', 'template' => '<x-webx-block type="a-hero" />']));

        $this->artisan('webx:blocks:import', ['--path' => $dir])
            ->expectsOutputToContain('a-hero → z-badge → a-hero')
            ->assertFailed();

        $this->assertSame('<b>{{ $block->type }}</b>', Block::query()->where('slug', 'z-badge')->firstOrFail()->currentVersion()?->template);

        File::deleteDirectory($dir);
    }

    #[Test]
    public function export_writes_the_kind(): void
    {
        $dir = storage_path('framework/testing/blocks-'.bin2hex(random_bytes(4)));
        $this->publish('badge', '<b></b>', ['kind' => 'component']);

        $this->artisan('webx:blocks:export', ['--path' => $dir])->assertSuccessful();

        $this->assertSame('component', json_decode((string) File::get("{$dir}/badge.json"), true)['kind']);

        File::deleteDirectory($dir);
    }

    private function declare(): void
    {
        $this->app->make(BlockShapes::class)->register(
            'recipes.card',
            fields: [['name' => 'title', 'type' => 'string', 'description' => 'What it is called']],
            sample: static fn (): array => ['title' => 'Borscht'],
        );

        $this->app->make(BlockComponents::class)->declare(
            slug: 'recipe-card',
            module: 'recipes',
            fallback: 'webx-demo::partials.card',
            title: 'Recipe card',
            description: 'One recipe in a list.',
            schema: [['type' => 'wx-data', 'id' => 'card', 'label' => 'Recipe', 'props' => ['shape' => 'recipes.card']]],
        );
    }

    /** The module's views, registered the way a module's provider does it — `vendor/` first. */
    private function loadViews(): void
    {
        $views = $this->views;

        $this->app->register(new class($this->app, $views) extends ServiceProvider
        {
            public function __construct($app, private readonly string $path)
            {
                parent::__construct($app);
            }

            public function boot(): void
            {
                $this->loadViewsFrom($this->path, 'webx-demo');
            }
        });

        View::getFinder()->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(mixed $rows, string $slug): array
    {
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && ($row['slug'] ?? null) === $slug) {
                return $row;
            }
        }

        $this->fail("No row {$slug}.");
    }

    private function api(string|int $path): string
    {
        return rtrim('/api/cms/blocks/'.$path, '/');
    }
}
