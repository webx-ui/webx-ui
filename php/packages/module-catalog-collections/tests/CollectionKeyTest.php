<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A key is brought to its shape rather than refused (#125): whatever is typed — in the panel, by an
 * agent — becomes `[a-z0-9-]` in the site's main language; only a taken key and a change to the key
 * of a saved collection are refused.
 */
final class CollectionKeyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-localization.locales', [
            ['code' => 'ru', 'default' => true],
            ['code' => 'en'],
        ]);
        $this->app->make(Locales::class)->forget();
    }

    #[Test]
    public function the_form_makes_a_key_of_what_was_typed(): void
    {
        $manager = $this->manager();

        $id = $this->actingAs($manager, 'cms')->postJson($this->api(), ['title' => ['ru' => 'Колодки'], 'key' => 'Тормозные колодки'])
            ->assertCreated()
            ->assertJsonPath('data.key', 'tormoznye-kolodki')
            ->json('data.id');

        // The same key typed again another way is no change of a fixed key; another one is.
        $this->actingAs($manager, 'cms')->putJson($this->api((string) $id), ['key' => 'Tormoznye Kolodki'])->assertOk();
        $this->actingAs($manager, 'cms')->putJson($this->api((string) $id), ['key' => 'Диски'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        $this->assertSame('tormoznye-kolodki', ProductCollection::query()->findOrFail($id)->key);
    }

    #[Test]
    public function an_agent_gets_the_same_key_and_a_taken_one_is_refused(): void
    {
        $made = $this->content($this->agent('catalog_collections_create', ['values' => ['title' => 'Шины', 'key' => 'Летние Шины!']]));

        $this->assertSame('letnie-shiny', $made['collection']['key']);

        $this->agent('catalog_collections_create', ['values' => ['title' => 'Другие', 'key' => 'letnie shiny']])->assertHasErrors(['taken']);
        $this->assertSame(1, ProductCollection::query()->count());
    }

    #[Test]
    public function a_key_that_leaves_nothing_is_made_of_the_title(): void
    {
        $this->actingAs($this->manager(), 'cms')->postJson($this->api(), ['title' => ['ru' => 'Новинки'], 'key' => '!!!'])
            ->assertCreated()
            ->assertJsonPath('data.key', 'novinki');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->manager(), 'cms')->tool($bound, $arguments);
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
