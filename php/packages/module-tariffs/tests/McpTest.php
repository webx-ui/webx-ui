<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Tariffs\Models\Tariff;

/**
 * The tariffs by their other doors (§5.5): the same list, the same screen, the same order code —
 * and the shapes an agent writes in turned into the screen's before the form sees them.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_section_offers_its_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['tariffs_list', 'tariffs_get', 'tariffs_create', 'tariffs_update', 'tariffs_delete', 'tariffs_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('tariffs')),
        );
        $this->assertContains('tariff_groups_list', array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('tariff-groups')));

        $this->assertSame(['tariffs.view', 'tariffs.manage'], $registry->tool('tariffs_list')->permissions());
        $this->assertSame(['tariffs.manage'], $registry->tool('tariffs_reorder')->permissions());
        $this->assertArrayHasKey('services', $registry->tool('tariffs_create')->tool->inputSchema['properties'] ?? []);

        $this->assertContains('tariffs://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['tariffs.view']);

        $this->agent('tariffs_list', [], $reader)->assertOk();
        $this->agent('tariffs_create', ['name' => 'Nobody'], $reader)->assertHasErrors(['[tariffs.manage]']);
    }

    #[Test]
    public function create_writes_through_the_screen_in_the_agents_shapes(): void
    {
        $seo = $this->service('seo');
        $design = $this->service('design');
        $business = $this->group('For business');
        $this->group('Для частных');

        $created = $this->content($this->agent('tariffs_create', [
            'name' => 'Combo Starter',
            'badge' => ['en' => '30 HOURS / 25$', 'ru' => '30 ЧАСОВ / 25$'],
            'price' => 750,
            'currency' => 'eur',
            'period' => '/mo',
            // A string, a map, and the row tariffs_get hands out: all three are lines.
            'features' => ['Design', ['en' => 'SEO', 'ru' => 'SEO'], ['text' => ['en' => 'Support']], ''],
            'button' => ['label' => 'Get started', 'link' => '/contacts', 'variant' => 'secondary'],
            'featured' => true,
            // An id and a title in the other language.
            'categories' => [$business->id, 'Для частных'],
            // An id and an address: both are what an agent reads off services://catalog.
            'services' => [$seo->id, '/services/design'],
        ]));

        $values = $created['values'];
        $this->assertFalse($values['published']);
        // A plain string is the default language, not the language of the request.
        $this->assertSame(['en' => 'Combo Starter'], $values['name']);
        $this->assertEquals(750, $values['price']);
        $this->assertSame('EUR', $values['currency']);
        $this->assertSame([
            ['text' => ['en' => 'Design']],
            ['text' => ['en' => 'SEO', 'ru' => 'SEO']],
            ['text' => ['en' => 'Support']],
        ], $values['features'], 'the empty line is dropped, the order kept');
        $this->assertSame(['en' => 'Get started'], $values['button_label']);
        $this->assertSame('/contacts', $values['button_link']['url']);
        $this->assertSame('secondary', $values['button_variant']);
        $this->assertCount(2, $values['categories']);
        $this->assertSame([$seo->id, $design->id], $created['tariff']['services']);
        $this->assertSame('€750 /mo', $created['tariff']['price_line']);
        $this->assertSame(['name' => ['en'], 'description' => []], $created['tariff']['written_in']);
    }

    #[Test]
    public function a_new_tariff_without_a_currency_gets_the_first_one(): void
    {
        $created = $this->content($this->agent('tariffs_create', ['name' => 'Plain', 'price' => 10]));

        $this->assertSame('USD', $created['values']['currency']);
    }

    #[Test]
    public function a_currency_or_a_look_the_site_does_not_have_is_refused_with_the_keys_it_has(): void
    {
        $this->agent('tariffs_create', ['name' => 'Swiss', 'price' => 10, 'currency' => 'CHF'])
            ->assertHasErrors(['no currency [CHF]', 'USD, EUR, UAH, PLN', 'webx-tariffs.currencies']);
        $this->agent('tariffs_create', ['name' => 'Loud', 'button' => ['label' => 'Go', 'link' => '/go', 'variant' => 'ghost']])
            ->assertHasErrors(['no button look [ghost]', 'primary, secondary, link']);

        $this->assertSame(0, Tariff::withTrashed()->count());
    }

    #[Test]
    public function a_currency_and_a_look_since_dropped_go_back_as_they_came(): void
    {
        $tariff = $this->tariff('Old', attributes: [
            'price' => 90, 'currency' => 'CHF',
            'button_label' => ['en' => 'Go'], 'button_link' => self::url('/go'), 'button_variant' => 'ghost',
        ]);

        // What tariffs_get handed out, sent back.
        $values = $this->content($this->agent('tariffs_get', ['tariff' => $tariff->id]))['values'];
        $this->agent('tariffs_update', ['tariff' => $tariff->id, 'values' => [
            'currency' => $values['currency'],
            'button' => ['variant' => $values['button_variant']],
            'price' => 95,
        ]])->assertOk();

        $tariff->refresh();
        $this->assertSame('CHF', $tariff->currency);
        $this->assertSame('ghost', $tariff->button_variant);
        $this->assertSame(95.0, (float) $tariff->price);

        // Another tariff may not choose them.
        $other = $this->tariff('New');
        $this->agent('tariffs_update', ['tariff' => $other->id, 'values' => ['currency' => 'CHF']])->assertHasErrors(['no currency [CHF]']);
    }

    #[Test]
    public function a_refused_create_leaves_nothing_behind(): void
    {
        // A field of the project refusing its value: the tariff named in the same call is not left
        // behind without it.
        Screens::extend('tariffs.form', [['op' => 'add', 'target' => 'project-fields', 'node' => [
            'id' => 'hours', 'type' => 'wx-input-number', 'name' => 'hours', 'label' => 'Hours', 'props' => ['min' => 1],
        ]]]);

        $this->agent('tariffs_create', ['name' => 'Half written', 'values' => ['hours' => 0]])->assertHasErrors(['hours']);
        $this->agent('tariffs_create', ['name' => 'Costly', 'price' => 19.995])->assertHasErrors(['price:']);
        $this->agent('tariffs_create', ['name' => 'Wordy', 'price' => 'a lot'])->assertHasErrors(['`price` is a number']);
        $this->agent('tariffs_create', ['name' => 'Nowhere', 'button' => ['label' => 'Go']])->assertHasErrors(['button_link:']);
        $this->agent('tariffs_create', ['name' => 'Long', 'features' => ['ok', str_repeat('x', 256)]])->assertHasErrors(['features.1.text']);
        $this->agent('tariffs_create', ['name' => 'Linked', 'services' => [999]])->assertHasErrors(['[999]']);
        $this->agent('tariffs_create', ['name' => 'Addressed', 'services' => ['/services/nowhere']])->assertHasErrors(['No service answers']);
        $this->agent('tariffs_create', ['name' => 'Filed', 'categories' => ['No such group']])->assertHasErrors(['tariff_groups_list']);
        $this->agent('tariffs_create', ['name' => ''])->assertHasErrors(['cannot be empty']);

        $this->assertSame(0, Tariff::withTrashed()->count());
    }

    #[Test]
    public function update_keeps_the_languages_it_was_not_given_and_a_null_button_takes_it_away(): void
    {
        $tariff = $this->tariff('Starter', attributes: [
            'description' => ['en' => 'Small.', 'ru' => 'Маленький.'],
            'button_label' => ['en' => 'Go', 'ru' => 'Вперёд'], 'button_link' => self::url('/go'), 'button_variant' => 'primary',
        ]);

        $this->agent('tariffs_update', ['tariff' => $tariff->id, 'values' => ['description' => 'Small, still.']])->assertOk();
        $this->assertSame(['en' => 'Small, still.', 'ru' => 'Маленький.'], $tariff->refresh()->getTranslations('description'));

        $this->agent('tariffs_update', ['tariff' => $tariff->id, 'values' => ['description' => ['ru' => '']]])->assertOk();
        $this->assertSame(['en' => 'Small, still.'], $tariff->refresh()->getTranslations('description'));

        // A button's label alone: the link it had stays.
        $this->agent('tariffs_update', ['tariff' => $tariff->id, 'values' => ['button' => ['label' => 'Start']]])->assertOk();
        $tariff->refresh();
        $this->assertSame(['en' => 'Start', 'ru' => 'Вперёд'], $tariff->getTranslations('button_label'));
        $this->assertSame('/go', $tariff->button_link['url'] ?? null);

        $this->agent('tariffs_update', ['tariff' => $tariff->id, 'values' => ['button' => null]])->assertOk();
        $tariff->refresh();
        $this->assertSame([], $tariff->getTranslations('button_label'));
        $this->assertNull($tariff->button_link);
        $this->assertNull($tariff->button_variant);
    }

    #[Test]
    public function reordering_the_list_and_inside_a_group(): void
    {
        $group = $this->group('For business');
        $first = $this->tariff('First', categories: [$group]);
        $second = $this->tariff('Second', categories: [$group]);
        $third = $this->tariff('Third');

        $listed = $this->content($this->agent('tariffs_reorder', ['tariffs' => [$third->id, $first->id]]));
        // The two named trade the places they held; the one left out stays between them.
        $this->assertSame([$third->id, $second->id, $first->id], array_column($listed['tariffs'], 'id'));

        $inGroup = $this->content($this->agent('tariffs_reorder', ['tariffs' => [$second->id, $first->id], 'group' => 'For business']));
        $this->assertSame('the order of this group', $inGroup['order']);
        $this->assertSame([$second->id, $first->id], array_column($inGroup['tariffs'], 'id'));
        // The whole list kept its own.
        $this->assertSame([$third->id, $second->id, $first->id], array_column($this->content($this->agent('tariffs_list'))['tariffs'], 'id'));

        $this->agent('tariffs_reorder', ['tariffs' => [$third->id], 'group' => $group->id])->assertHasErrors(['Not in this group']);

        // The shared group tools name their list the way the agent sees it, prefix and all.
        $this->agent('tariff_groups_update', ['group' => 'nowhere', 'values' => []])->assertHasErrors(['tariff_groups_list has them all']);
    }

    #[Test]
    public function a_tariff_is_named_by_its_id_only(): void
    {
        $this->tariff('Starter');

        $this->agent('tariffs_get', ['tariff' => 'Starter'])->assertHasErrors(['its id']);
        $this->agent('tariffs_get', ['tariff' => 999])->assertHasErrors(['[999]']);
    }

    #[Test]
    public function delete_puts_it_in_the_bin(): void
    {
        $tariff = $this->tariff('Gone');

        $this->agent('tariffs_delete', ['tariff' => $tariff->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($tariff->refresh()->trashed());

        $this->agent('tariffs_delete', ['tariff' => (string) $tariff->id])->assertOk();

        $this->assertTrue($tariff->refresh()->trashed());
        $this->assertSame([$tariff->id], array_column($this->content($this->agent('tariffs_list', ['trashed' => true]))['tariffs'], 'id'));
        $this->agent('tariffs_update', ['tariff' => $tariff->id, 'values' => ['published' => false]])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_catalogue_lists_groups_in_order_with_the_currencies_and_looks(): void
    {
        $seo = $this->service('seo');
        $group = $this->group('For business');
        $growth = $this->tariff('Growth', categories: [$group], attributes: [
            'price' => 380, 'currency' => 'USD', 'period' => ['en' => '/mo'], 'featured' => true,
            'description' => ['en' => 'Big.'],
        ]);
        $enterprise = $this->tariff('Enterprise', published: false, categories: [$group], attributes: ['price_text' => ['en' => 'On request']]);
        $loose = $this->tariff('Loose');
        $gone = $this->tariff('Gone', categories: [$group]);
        $gone->delete();
        $growth->syncRelated(Tariff::SERVICES, 'service', [$seo->id]);

        $catalog = ($this->resource('tariffs://catalog')->handler)();

        $this->assertSame('€', $catalog['currencies']['EUR']);
        $this->assertSame(['primary', 'secondary', 'link'], $catalog['variants']);
        $this->assertSame([$growth->id, $enterprise->id], array_column($catalog['groups'][0]['tariffs'], 'id'));
        $this->assertSame('$380 /mo', $catalog['groups'][0]['tariffs'][0]['price']);
        $this->assertSame('On request', $catalog['groups'][0]['tariffs'][1]['price']);
        $this->assertTrue($catalog['groups'][0]['tariffs'][0]['featured']);
        $this->assertFalse($catalog['groups'][0]['tariffs'][1]['published']);
        $this->assertSame(['name' => ['en'], 'description' => ['en']], $catalog['groups'][0]['tariffs'][0]['written_in']);
        $this->assertSame([$seo->id], $catalog['groups'][0]['tariffs'][0]['services']);
        $this->assertSame([$loose->id], array_column($catalog['ungrouped'], 'id'));
    }

    private function resource(string $uri): McpResource
    {
        foreach ($this->app->make(ToolRegistry::class)->resources() as $resource) {
            if ($resource->uri === $uri) {
                return $resource;
            }
        }

        $this->fail("No resource [{$uri}].");
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as ?? $this->editor(), 'cms')->tool($bound, $arguments);
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
