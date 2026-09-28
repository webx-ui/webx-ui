<?php

declare(strict_types=1);

namespace WebxUi\Tariffs\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Services\ServicesServiceProvider;
use WebxUi\Tariffs\Models\Tariff;

/**
 * A site without `module-services` (decision 4): no "Services" field on the form, no relation in
 * the block's choice, no service links on a card — and a save that still names services treats
 * the name as any field the screen does not have, leaving the rows those services left behind.
 */
final class WithoutServicesTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter(
            parent::getPackageProviders($app),
            static fn (string $provider): bool => $provider !== ServicesServiceProvider::class,
        ));
    }

    #[Test]
    public function the_services_field_is_not_on_the_screen(): void
    {
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/tariffs.form')->assertOk()->json('data.root');

        $ids = [];
        array_walk_recursive($root, static function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id') {
                $ids[] = $value;
            }
        });

        $this->assertNotContains('services', $ids);
        $this->assertContains('categories', $ids);
    }

    #[Test]
    public function a_save_naming_services_is_a_field_nobody_has_and_leaves_their_rows_alone(): void
    {
        $tariff = $this->tariff('Starter');

        // A service that was related before the module went: the row waits for it to come back.
        DB::table(Relations::TABLE)->insert([
            'owner_type' => Tariff::TYPE,
            'owner_id' => $tariff->id,
            'role' => Tariff::SERVICES,
            'target_type' => 'service',
            'target_id' => 7,
            'position' => 0,
        ]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($tariff->id), ['values' => ['services' => [1, 2], 'badge' => ['en' => '30 HOURS']]])
            ->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertArrayNotHasKey('services', $values);
        $this->assertSame('30 HOURS', $values['badge']['en'] ?? null);
        $this->assertArrayNotHasKey('services', (array) $tariff->refresh()->extraRaw());
        $this->assertSame([7], $tariff->relatedIds(Tariff::SERVICES));

        $this->assertSame([], tariffs()->first()['service_links'] ?? null);
    }

    #[Test]
    public function the_block_is_offered_groups_and_no_relation(): void
    {
        $response = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/collections')->assertOk();

        $tariffs = null;

        foreach ((array) $response->json('data') as $source) {
            if (is_array($source) && ($source['key'] ?? null) === 'tariffs') {
                $tariffs = $source;
            }
        }

        $this->assertIsArray($tariffs);
        $this->assertSame([], $tariffs['relations'] ?? null);
        $this->assertSame('tariffs/categories', $tariffs['categories'] ?? null);
        $this->assertFalse($tariffs['markup'] ?? $tariffs['supports_markup'] ?? false);
    }
}
