<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * A slug written by hand ends up Latin whichever door it came in by: the panel, the exchange, an
 * agent or the model itself — in the column and in the address.
 */
final class LatinSlugsTest extends TestCase
{
    #[Test]
    public function the_panel_transliterates_a_new_products_slug_and_shows_the_result(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('products'), [
            'values' => ['name' => ['en' => 'Brake pads'], 'slug' => ['en' => 'тормозные-колодки']],
        ])->assertCreated();

        $id = (int) $response->json('data.product.id');

        $this->assertSame('tormoznye-kolodki', $response->json('data.values.slug.en'));
        $this->assertLatin(Product::query()->findOrFail($id), 'tormoznye-kolodki', "tormoznye-kolodki-{$id}");
    }

    #[Test]
    public function the_panel_transliterates_a_changed_products_slug(): void
    {
        $product = $this->product('Brake pads', $this->category('brakes'));

        $this->actingAs($this->editor(), 'cms')->putJson($this->api("products/{$product->id}"), [
            'values' => ['slug' => ['en' => 'колодки']],
        ])->assertOk()->assertJsonPath('data.values.slug.en', 'kolodki');

        $this->assertLatin($product->refresh(), 'kolodki', "kolodki-{$product->id}");
    }

    #[Test]
    public function the_panel_transliterates_a_categorys_slug(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api('categories'), [
            'values' => ['name' => ['en' => 'Brakes'], 'slug' => ['en' => 'тормоза']],
        ])->assertCreated();

        $this->assertLatin(Category::query()->findOrFail((int) $response->json('data.category.id')), 'tormoza', 'tormoza');
    }

    #[Test]
    public function the_form_still_refuses_a_latin_slug_of_a_wrong_shape(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api('products'), [
            'values' => ['name' => ['en' => 'Brake pads'], 'slug' => ['en' => 'Brake Pads!']],
        ])->assertUnprocessable();
    }

    #[Test]
    public function an_agent_transliterates_a_slug_on_create_and_on_update(): void
    {
        $this->agent('catalog_products_create', ['values' => ['name' => ['en' => 'Agent'], 'slug' => ['en' => 'создан-агентом']]])->assertOk();

        $product = Product::query()->whereTranslation('name', 'Agent', 'en')->firstOrFail();
        $this->assertLatin($product, 'sozdan-agentom', "sozdan-agentom-{$product->id}");

        $this->agent('catalog_products_update', ['product' => $product->id, 'values' => ['slug' => ['en' => 'изменено-агентом']]])->assertOk();

        $this->assertLatin($product->refresh(), 'izmeneno-agentom', "izmeneno-agentom-{$product->id}");
    }

    #[Test]
    public function an_agent_and_a_direct_write_transliterate_a_categorys_slug(): void
    {
        $this->agent('catalog_categories_create', ['values' => ['name' => ['en' => 'Agent'], 'slug' => ['en' => 'создана-агентом']]])->assertOk();

        $this->assertLatin(Category::query()->whereTranslation('name', 'Agent', 'en')->firstOrFail(), 'sozdana-agentom', 'sozdana-agentom');

        $category = $this->category('brakes');
        $category->setTranslation('slug', 'en', 'тормоза');
        $category->save();

        $this->assertLatin($category->refresh(), 'tormoza', 'tormoza');
    }

    #[Test]
    public function a_write_straight_into_the_model_is_transliterated(): void
    {
        $product = $this->product('Direct', $this->category('brakes'));

        $product->setTranslation('slug', 'en', 'прямая-запись');
        $product->save();

        $this->assertLatin($product->refresh(), 'priamaia-zapis', "priamaia-zapis-{$product->id}");
    }

    #[Test]
    public function a_slug_that_leaves_nothing_falls_back_to_the_type_and_the_id(): void
    {
        $id = (int) $this->actingAs($this->editor(), 'cms')->postJson($this->api('products'), [
            'values' => ['name' => ['en' => 'Hieroglyphs'], 'slug' => ['en' => '漢字']],
        ])->assertCreated()->json('data.product.id');

        $this->assertLatin(Product::query()->findOrFail($id), "product-{$id}", "product-{$id}-{$id}");

        $existing = $this->product('Signs', $this->category('brakes'));
        $existing->setTranslation('slug', 'en', '!!!');
        $existing->save();

        $this->assertLatin($existing->refresh(), "product-{$existing->id}", "product-{$existing->id}-{$existing->id}");
    }

    #[Test]
    public function a_name_that_leaves_nothing_falls_back_to_the_type_and_the_id(): void
    {
        $product = $this->product('漢字', $this->category('brakes'));

        $this->assertLatin($product->refresh(), "product-{$product->id}", "product-{$product->id}-{$product->id}");

        $id = (int) $this->actingAs($this->editor(), 'cms')->postJson($this->api('categories'), [
            'values' => ['name' => ['en' => '漢字']],
        ])->assertCreated()->json('data.category.id');

        $this->assertLatin(Category::query()->findOrFail($id), "category-{$id}", "category-{$id}");
    }

    #[Test]
    public function a_saved_slug_nobody_touched_stays_until_the_command_moves_it(): void
    {
        $product = $this->product('Old', $this->category('brakes'));
        $this->storedSlug($product, 'старый');

        $fresh = Product::query()->findOrFail($product->id);
        $fresh->setTranslation('name', 'en', 'Older');
        $fresh->save();

        $this->assertSame('старый', $fresh->refresh()->getTranslation('slug', 'en'));
    }

    private function assertLatin(Product|Category $entity, string $slug, string $path): void
    {
        $this->assertSame($slug, $entity->getTranslation('slug', 'en'));
        $this->assertSame("http://localhost/{$path}", $entity->url('en'));
    }

    /** What an older version left in the table: the save below it would not write this now. */
    private function storedSlug(Product $product, string $slug): void
    {
        DB::table('catalog_products')->where('id', $product->id)->update(['slug' => json_encode(['en' => $slug])]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
    }
}
