<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Mcp\Registry\BoundTool;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Tariffs\Models\Tariff;
use WebxUi\Tariffs\Models\TariffCategory;

/**
 * The section as the panel reaches it (§5.2–§5.4): the manifest, the permissions, the list with
 * its two orders, the form that creates and saves — in the shapes the panel is written against,
 * with every refusal under the name the form looks for it by — and the groups with no address.
 */
final class PanelTest extends TestCase
{
    #[Test]
    public function the_two_sections_are_in_the_manifest_under_one_group(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/manifest')->assertOk();

        $ours = [];

        foreach ((array) $response->json('data.modules') as $module) {
            if (is_array($module) && ($module['group'] ?? null) === 'tariffs') {
                $ours[(string) $module['id']] = $module;
            }
        }

        $this->assertSame(['tariffs', 'tariff-groups'], array_keys($ours));
        $this->assertSame(['tariffs.view', 'tariffs.manage'], $ours['tariffs']['permissions']);
        $this->assertSame(['tariffs.groups.manage'], $ours['tariff-groups']['permissions']);

        $icons = array_column((array) $response->json('data.groups'), 'icon', 'id');
        $this->assertSame('tag', $icons['tariffs'] ?? null);
    }

    #[Test]
    public function an_agent_sees_groups_under_the_modules_id(): void
    {
        $names = array_map(static fn (BoundTool $tool): string => $tool->fullName(), $this->app->make(ToolRegistry::class)->tools());

        $this->assertContains('tariff_groups_list', $names);
        $this->assertContains('tariff_groups_create', $names);
    }

    #[Test]
    public function a_stranger_and_a_viewer_are_kept_out_of_writing(): void
    {
        $tariff = $this->tariff('Starter');

        $this->getJson($this->api())->assertUnauthorized();

        $viewer = $this->editor(['tariffs.view']);

        $this->actingAs($viewer, 'cms')->getJson($this->api())->assertOk();
        $this->actingAs($viewer, 'cms')->getJson($this->api($tariff->id))->assertOk();
        $this->actingAs($viewer, 'cms')->postJson($this->api(), ['values' => ['name' => ['en' => 'Growth']]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->putJson($this->api($tariff->id), ['values' => ['published' => false]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->deleteJson($this->api($tariff->id))->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson($this->api('reorder'), ['ids' => [$tariff->id]])->assertForbidden();
        $this->actingAs($viewer, 'cms')->postJson('/api/cms/tariffs/categories', ['title' => 'Business'])->assertForbidden();

        // The groups are read by anybody who may open a tariff: the form files into them.
        $this->actingAs($viewer, 'cms')->getJson('/api/cms/tariffs/categories')->assertOk();

        // Writing tariffs is not writing groups.
        $this->actingAs($this->editor(['tariffs.view', 'tariffs.manage']), 'cms')
            ->postJson('/api/cms/tariffs/categories', ['title' => 'Business'])
            ->assertForbidden();
    }

    #[Test]
    public function the_list_is_every_tariff_in_its_order_in_the_shape_of_the_spec(): void
    {
        $business = $this->group('For business');
        $this->tariff('Starter', categories: [$business], attributes: [
            'badge' => ['en' => '30 HOURS'],
            'price' => 750,
            'currency' => 'USD',
            'period' => ['en' => '/mo'],
            'featured' => true,
        ]);
        $this->tariff('Enterprise', published: false, attributes: ['price_text' => ['en' => 'On request']]);
        $this->tariff('Gone')->delete();

        $response = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();

        $this->assertNull($response->json('meta'));
        $this->assertSame(['Starter', 'Enterprise'], array_column((array) $response->json('data'), 'name'));

        $row = (array) $response->json('data.0');
        $this->assertSame(
            ['id', 'name', 'badge', 'price', 'currency', 'symbol', 'period', 'price_text', 'featured', 'published', 'position', 'categories', 'updated_at', 'deleted_at'],
            array_keys($row),
        );
        $this->assertSame('30 HOURS', $row['badge']);
        $this->assertEquals(750, $row['price']);
        $this->assertSame('USD', $row['currency']);
        $this->assertSame('$', $row['symbol']);
        $this->assertSame('/mo', $row['period']);
        $this->assertTrue($row['featured']);
        $this->assertSame([['id' => $business->id, 'title' => 'For business']], $row['categories']);

        $second = (array) $response->json('data.1');
        $this->assertNull($second['price']);
        $this->assertSame('On request', $second['price_text']);
        $this->assertFalse($second['published']);

        $this->assertSame([['id' => $business->id, 'title' => 'For business']], $response->json('filters.categories'));

        $bin = $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?trashed=1')->assertOk();
        $this->assertSame(['Gone'], array_column((array) $bin->json('data'), 'name'));

        $found = $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?search=hours')->assertOk();
        $this->assertSame(['Starter'], array_column((array) $found->json('data'), 'name'), 'the badge is searched too');
    }

    #[Test]
    public function a_row_is_named_in_the_panels_language_then_the_default_then_by_its_number(): void
    {
        $this->tariff('Starter', attributes: ['name' => ['en' => 'Starter', 'ru' => 'Старт']]);
        $nameless = Tariff::query()->create(['published' => true]);

        $inRussian = $this->actingAs($this->editor(), 'cms')
            ->withHeader('X-Webx-Locale', 'ru')
            ->getJson($this->api())
            ->assertOk();

        $this->assertSame(['Старт', '#'.$nameless->id], array_column((array) $inRussian->json('data'), 'name'));
    }

    #[Test]
    public function a_drag_writes_the_order_it_was_made_in(): void
    {
        $business = $this->group('For business');
        $a = $this->tariff('A', categories: [$business]);
        $b = $this->tariff('B', categories: [$business]);
        $c = $this->tariff('C');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$b->id, $a->id], 'category' => $business->id])
            ->assertNoContent();

        $inside = $this->actingAs($this->editor(), 'cms')->getJson($this->api().'?category='.$business->id)->assertOk();
        $this->assertSame([$b->id, $a->id], array_column((array) $inside->json('data'), 'id'));

        $whole = $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk();
        $this->assertSame([$a->id, $b->id, $c->id], array_column((array) $whole->json('data'), 'id'), 'the order inside a group is its own');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertNoContent();

        $this->assertSame([$c->id, $a->id, $b->id], array_column((array) $this->actingAs($this->editor(), 'cms')->getJson($this->api())->json('data'), 'id'));
    }

    #[Test]
    public function a_new_tariff_is_created_from_the_values_of_its_form(): void
    {
        $business = $this->group('For business');

        $response = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => [
                'name' => ['en' => 'Combo Starter', 'ru' => 'Комбо Старт'],
                'badge' => ['en' => '30 HOURS / 25$'],
                'price' => '750.5',
                'period' => ['en' => '/mo'],
                'features' => [
                    ['text' => ['en' => 'Design', 'ru' => 'Дизайн']],
                    ['text' => ['en' => '', 'ru' => '']],
                    ['text' => ['en' => ' SEO ']],
                ],
                'description' => ['en' => 'For a start.'],
                'button_label' => ['en' => 'Get started'],
                'button_link' => self::url('/contacts'),
                'button_variant' => 'secondary',
                'featured' => true,
                'categories' => [$business->id],
            ],
        ])->assertCreated();

        $this->assertSame(['id', 'name', 'published', 'deleted_at'], array_keys((array) $response->json('data.tariff')));
        $this->assertSame('Combo Starter', $response->json('data.tariff.name'));
        $this->assertFalse($response->json('data.tariff.published'), 'a new tariff is not on the site until somebody says so');

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertSame(['en' => 'Combo Starter', 'ru' => 'Комбо Старт'], $values['name']);
        $this->assertEquals(750.5, $values['price']);
        $this->assertSame('USD', $values['currency'], 'a new tariff starts in the first currency of the config');
        $this->assertSame(
            [['text' => ['en' => 'Design', 'ru' => 'Дизайн']], ['text' => ['en' => 'SEO']]],
            $values['features'],
            'the empty row is dropped, the order kept',
        );
        $this->assertSame('/contacts', $values['button_link']['url'] ?? null);
        $this->assertSame('secondary', $values['button_variant']);
        $this->assertTrue($values['featured']);
        $this->assertSame([$business->id], $values['categories']);
        $this->assertSame([], $values['services'] ?? null, 'the field is there on a site with services');
    }

    #[Test]
    public function the_name_in_the_default_language_is_required_under_that_language(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['values' => ['name' => ['ru' => 'Старт']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name.en']);

        $this->assertSame(0, Tariff::withTrashed()->count(), 'a refusal leaves no row behind');
    }

    #[Test]
    public function a_price_that_is_not_one_is_refused_under_price(): void
    {
        $tariff = $this->tariff('Starter');

        foreach (['-1', '12.345', 100_000_000, 'cheap'] as $price) {
            $this->actingAs($this->editor(), 'cms')
                ->putJson($this->api($tariff->id), ['values' => ['price' => $price]])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['price']);
        }

        foreach ([0, '19.99', 99_999_999.99, null] as $price) {
            $this->actingAs($this->editor(), 'cms')
                ->putJson($this->api($tariff->id), ['values' => ['price' => $price]])
                ->assertOk();
        }

        $this->assertNull($tariff->refresh()->price);
    }

    #[Test]
    public function zero_is_a_price(): void
    {
        $tariff = $this->tariff('Free');

        $this->actingAs($this->editor(), 'cms')->putJson($this->api($tariff->id), ['values' => ['price' => 0]])->assertOk();

        $this->assertSame(0.0, $tariff->refresh()->price);
        $this->assertSame('0', tariffs()->first()['amount'] ?? null);
    }

    /** Decision 15: a currency the config lost does not lock the tariff that has it. */
    #[Test]
    public function a_currency_taken_out_of_the_config_passes_back_and_a_new_one_is_refused(): void
    {
        $swiss = $this->tariff('Swiss', attributes: ['price' => 10, 'currency' => 'CHF']);
        $other = $this->tariff('Other', attributes: ['price' => 10, 'currency' => 'USD']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($swiss->id), ['values' => ['currency' => 'CHF', 'price' => 12]])
            ->assertOk()
            ->assertJsonPath('data.values.currency', 'CHF');

        $refused = $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($other->id), ['values' => ['currency' => 'CHF']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);

        $this->assertStringContainsString('USD, EUR, UAH, PLN', (string) $refused->json('errors.currency.0'));
        $this->assertSame('USD', $other->refresh()->currency);

        // An empty currency with no price is no mistake either.
        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($other->id), ['values' => ['currency' => '', 'price' => null]])
            ->assertOk();
        $this->assertNull($other->refresh()->currency);
    }

    /**
     * A site's own list, set where a site sets it — in its config, before the panel boots — with
     * a key the column could not hold among them.
     *
     * @param  Application  $app
     */
    protected function withPounds($app): void
    {
        $app['config']->set('webx-tariffs.currencies', ['USD' => '$', 'GBP' => '£', 'bad' => '?']);
    }

    #[Test]
    #[DefineEnvironment('withPounds')]
    public function a_currency_the_site_added_is_taken(): void
    {
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/tariffs.form')->assertOk()->json('data.root');
        $this->assertSame(['USD', 'GBP'], array_column((array) ($this->node($root, 'currency')['props']['options'] ?? []), 'value'));

        $tariff = $this->tariff('London');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['currency' => 'GBP', 'price' => 99]])
            ->assertOk();

        $this->assertSame('GBP', $tariff->refresh()->currency);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['currency' => 'bad']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    }

    /** Decision 11: the look of a button, the way banners keep it. */
    #[Test]
    public function a_look_taken_out_of_the_config_passes_back_and_a_new_one_is_refused(): void
    {
        $old = $this->tariff('Old', attributes: ['button_label' => ['en' => 'Go'], 'button_link' => self::url('/go'), 'button_variant' => 'outline']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($old->id), ['values' => ['button_variant' => 'outline', 'button_label' => ['en' => 'Go on']]])
            ->assertOk()
            ->assertJsonPath('data.values.button_variant', 'outline');

        $new = $this->tariff('New');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($new->id), ['values' => ['button_variant' => 'outline']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_variant']);
    }

    #[Test]
    public function a_label_without_a_link_is_refused_under_the_link_and_a_link_without_a_label_passes(): void
    {
        $tariff = $this->tariff('Starter');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['button_label' => ['en' => 'Get started']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_link']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['button_link' => self::url('/contacts')]])
            ->assertOk();

        // Now the link is there, the label alone is fine — and taking the link away again is not.
        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['button_label' => ['en' => 'Get started']]])
            ->assertOk();

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['button_link' => null]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['button_link']);
    }

    #[Test]
    public function a_line_of_the_list_is_refused_under_its_row_as_the_editor_counts_them(): void
    {
        $tariff = $this->tariff('Starter');

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['features' => [
                ['text' => ['en' => 'Design']],
                ['text' => ['en' => '']],
                ['text' => ['en' => str_repeat('a', 256)]],
            ]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['features.2.text']);

        $this->assertNull($tariff->refresh()->features);
    }

    #[Test]
    public function a_save_lays_one_language_over_the_others(): void
    {
        $tariff = $this->tariff('Starter', attributes: ['period' => ['en' => '/mo', 'ru' => '/мес']]);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['period' => ['ru' => '']]])
            ->assertOk();

        $this->assertSame(['en' => '/mo'], $tariff->refresh()->getTranslations('period'));
    }

    #[Test]
    public function a_field_of_the_project_goes_into_extra(): void
    {
        Screens::extend(Tariff::SCREEN, [[
            'op' => 'add',
            'target' => 'project-fields',
            'node' => ['id' => 'note', 'type' => 'wx-input', 'name' => 'note', 'label' => 'Note'],
        ]]);

        $tariff = $this->tariff('Starter');

        $response = $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['note' => 'VAT included']])
            ->assertOk();

        $this->assertSame('VAT included', $response->json('data.values.note'));
        $this->assertSame(['note' => 'VAT included'], $tariff->refresh()->extraRaw());
    }

    #[Test]
    public function the_selects_have_the_options_of_the_config(): void
    {
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/tariffs.form')->assertOk()->json('data.root');

        $this->assertSame(
            [['value' => 'USD', 'label' => 'USD — $'], ['value' => 'EUR', 'label' => 'EUR — €'], ['value' => 'UAH', 'label' => 'UAH — ₴'], ['value' => 'PLN', 'label' => 'PLN — zł']],
            $this->node($root, 'currency')['props']['options'] ?? null,
        );
        $this->assertSame(['primary', 'secondary', 'link'], array_column((array) ($this->node($root, 'button-variant')['props']['options'] ?? []), 'value'));
        $this->assertNotNull($this->node($root, 'services'), 'the services field is there on a site with services');
    }

    #[Test]
    public function the_bin_keeps_the_tariff_and_gives_it_back_as_a_row_of_the_list(): void
    {
        $business = $this->group('For business');
        $tariff = $this->tariff('Starter', categories: [$business]);

        $this->actingAs($this->editor(), 'cms')->deleteJson($this->api($tariff->id))->assertNoContent();
        $this->assertSoftDeleted($tariff);
        $this->assertNull(tariffs()->first());

        $restored = $this->actingAs($this->editor(), 'cms')->postJson($this->api($tariff->id.'/restore'))->assertOk();

        $this->assertSame('Starter', $restored->json('data.name'));
        $this->assertSame([['id' => $business->id, 'title' => 'For business']], $restored->json('data.categories'));
        $this->assertNull($restored->json('data.deleted_at'));
    }

    #[Test]
    public function a_group_has_no_address_and_holds_its_tariffs(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')
            ->postJson('/api/cms/tariffs/categories', ['title' => ['en' => 'For business']])
            ->assertCreated();

        $group = TariffCategory::query()->findOrFail($response->json('data.id'));
        $this->assertNull($group->getRawOriginal('slug'));

        $this->tariff('Starter', categories: [$group]);

        $refused = $this->actingAs($this->editor(), 'cms')->deleteJson('/api/cms/tariffs/categories/'.$group->id)->assertUnprocessable();
        $this->assertStringContainsString(': 1.', (string) json_encode($refused->json(), JSON_UNESCAPED_UNICODE), 'the count stands at the end of the line');
    }

    #[Test]
    public function a_refused_group_leaves_nothing_behind(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), [
            'values' => ['name' => ['en' => 'Orphan'], 'categories' => [999]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['categories']);

        $this->assertSame(0, Tariff::withTrashed()->count());
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @return array<string, mixed>|null
     */
    private function node(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['id'] ?? null) === $id) {
                return $node;
            }

            $found = $this->node((array) ($node['children'] ?? []), $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
