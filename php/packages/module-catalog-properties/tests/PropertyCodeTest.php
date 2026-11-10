<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The code of a property and its word of «yes» are brought to their shape rather than refused
 * (#125), each language read its own way — «г» is `g` in Russian and `h` in Ukrainian — whatever
 * door they came in by; only a code another facet holds is refused.
 */
final class PropertyCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('webx-localization.locales', [
            ['code' => 'ru', 'default' => true],
            ['code' => 'uk', 'default' => false],
        ]);
        $this->app->make(Locales::class)->forget();
    }

    #[Test]
    public function the_form_makes_a_code_of_what_was_typed_in_each_language(): void
    {
        $editor = $this->editor();

        $id = (int) $this->actingAs($editor, 'cms')->postJson($this->api('properties'), ['values' => [
            'title' => ['ru' => 'Колодки', 'uk' => 'Колодки'],
            'type' => Property::BOOL,
            'code' => ['ru' => 'Гальмівні колодки', 'uk' => 'Гальмівні колодки'],
            'toggle_slug' => ['ru' => 'Есть, конечно!', 'uk' => 'Так_точно'],
        ]])->assertCreated()->json('data.property.id');

        $property = Property::query()->findOrFail($id);
        $this->assertSame(['ru' => 'galmivni-kolodki', 'uk' => 'halmivni-kolodky'], $property->getTranslations('code'));
        $this->assertSame(['ru' => 'est-konechno', 'uk' => 'tak-tochno'], $property->getTranslations('toggle_slug'));

        // Capitals, signs, and a text longer than a code is cut, not refused.
        $this->actingAs($editor, 'cms')->putJson($this->api("properties/{$id}"), ['values' => [
            'code' => ['ru' => '  Brake Pads & Discs, for every single car on the road!  '],
        ]])->assertOk();

        $code = (string) Property::query()->findOrFail($id)->getTranslation('code', 'ru');
        $this->assertSame('brake-pads-discs-for-every-single-car-on-the-roa', $code);
        $this->assertLessThanOrEqual(Property::CODE_LENGTH, strlen($code));
    }

    #[Test]
    public function an_agent_and_a_direct_save_get_the_same_code(): void
    {
        $made = $this->content($this->agent('catalog_properties_create', [
            'title' => 'Колодки',
            'type' => Property::SELECT,
            'values' => ['code' => 'Тормозные Колодки'],
        ]));

        $this->assertSame('tormoznye-kolodki', $made['property']['code']['ru']);

        $this->agent('catalog_properties_update', ['property' => 'tormoznye-kolodki', 'values' => ['code' => ['uk' => 'Гальмівні Колодки']]])->assertOk();
        $this->assertSame('halmivni-kolodky', Property::query()->sole()->getTranslation('code', 'uk'));

        $direct = $this->property('Диски', attributes: ['code' => ['ru' => 'Тормозные диски', 'uk' => 'Гальмівні диски']]);
        $this->assertSame(['ru' => 'tormoznye-diski', 'uk' => 'halmivni-dysky'], $direct->getTranslations('code'));
    }

    #[Test]
    public function a_code_that_leaves_nothing_is_made_of_the_name_and_a_taken_one_is_refused(): void
    {
        $editor = $this->editor();

        $id = (int) $this->actingAs($editor, 'cms')->postJson($this->api('properties'), ['values' => [
            'title' => ['ru' => 'Цвет корпуса'],
            'type' => Property::SELECT,
            'code' => ['ru' => '!!!'],
        ]])->assertCreated()->json('data.property.id');

        $this->assertSame('tsvet-korpusa', Property::query()->findOrFail($id)->getTranslation('code', 'ru'));

        $this->actingAs($editor, 'cms')->postJson($this->api('properties'), ['values' => [
            'title' => ['ru' => 'Оттенок'],
            'type' => Property::SELECT,
            'code' => ['ru' => 'Цвет Корпуса'],
        ]])->assertStatus(422)->assertJsonValidationErrors('code.ru');
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = []): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($this->editor(), 'cms')->tool($bound, $arguments);
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
