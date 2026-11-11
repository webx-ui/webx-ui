<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Tests;

use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A code is brought to its shape rather than refused (#125): whatever is typed — in the panel, by
 * an agent, by a direct save — becomes `[a-z0-9-]`, and only a code that is taken is refused.
 */
final class StockCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Locale::fromCode('ru', ['is_default' => true, 'sort' => 0])->save();
        $this->app->make(Locales::class)->forget();
    }

    #[Test]
    public function the_form_makes_a_code_of_what_was_typed(): void
    {
        $editor = $this->editor();

        $id = (int) $this->actingAs($editor, 'cms')
            ->postJson($this->api('stock'), ['title' => ['ru' => 'Колодки']])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($editor, 'cms')
            ->putJson($this->api("stock/{$id}"), ['values' => ['code' => 'тормозные колодки']])
            ->assertOk();

        $this->assertSame('tormoznye-kolodki', StockStatus::query()->findOrFail($id)->code);

        // Capitals, spaces, an underscore, signs; and a text longer than a code is cut, not refused.
        $this->actingAs($editor, 'cms')
            ->putJson($this->api("stock/{$id}"), ['values' => ['code' => '  Brake_Pads & Discs, for every car on the road!  ']])
            ->assertOk();

        $code = StockStatus::query()->findOrFail($id)->code;
        $this->assertSame('brake-pads-discs-for-every-car-o', $code);
        $this->assertLessThanOrEqual(StockStatus::CODE_LENGTH, strlen($code));
    }

    #[Test]
    public function an_agent_and_a_direct_save_get_the_same_code(): void
    {
        $this->agent('catalog_stock_create', ['title' => 'Под заказ', 'values' => ['code' => 'Тормозные Колодки']])->assertOk();

        $this->assertSame('tormoznye-kolodki', StockStatus::query()->latest('id')->firstOrFail()->code);

        $status = StockStatus::query()->create(['title' => ['ru' => 'Ожидается'], 'code' => 'Ожидается в продаже']);

        $this->assertSame('ozhidaetsya-v-prodazhe', $status->code);
    }

    #[Test]
    public function a_code_that_leaves_nothing_is_made_of_the_name_and_a_taken_one_is_refused(): void
    {
        $editor = $this->editor();

        $id = (int) $this->actingAs($editor, 'cms')
            ->postJson($this->api('stock'), ['title' => ['ru' => 'Под заказ']])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($editor, 'cms')->putJson($this->api("stock/{$id}"), ['values' => ['code' => '!!!']])->assertOk();

        $this->assertSame('pod-zakaz', StockStatus::query()->findOrFail($id)->code);

        // Given its own code back when what was typed leaves nothing, not `pod-zakaz-2`.
        $this->actingAs($editor, 'cms')->putJson($this->api("stock/{$id}"), ['values' => ['code' => '漢字']])->assertOk();
        $this->assertSame('pod-zakaz', StockStatus::query()->findOrFail($id)->code);

        $other = $this->stockStatus('in-stock');

        $this->actingAs($editor, 'cms')->putJson($this->api("stock/{$other->id}"), ['values' => ['code' => 'Pod Zakaz']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
    }
}
