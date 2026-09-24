<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Services\ServicesServiceProvider;

/**
 * A site without `module-services` (decision 12): no "Services" field on the form, a save that
 * still names services neither fails nor touches the rows those services left behind, and the
 * rest works as it does anywhere.
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
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/recipes.form')->assertOk()->json('data.root');

        $ids = [];
        array_walk_recursive($root, static function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id') {
                $ids[] = $value;
            }
        });

        $this->assertNotContains('services', $ids);
        $this->assertContains('related', $ids);
    }

    #[Test]
    public function a_save_naming_services_does_not_fail_and_leaves_their_rows_alone(): void
    {
        $recipe = $this->recipe('porridge');

        // A service that was related before the module went: the row waits for it to come back.
        DB::table(Relations::TABLE)->insert([
            'owner_type' => 'recipe',
            'owner_id' => $recipe->id,
            'role' => 'services',
            'target_type' => 'service',
            'target_id' => 7,
            'position' => 0,
        ]);

        $response = $this->actingAs($this->editor(['recipes.view', 'recipes.manage']), 'cms')
            ->putJson($this->api($recipe->id), ['values' => ['services' => [1, 2], 'lead' => ['en' => 'Quick.']]])
            ->assertOk();

        /** @var array<string, mixed> $values */
        $values = $response->json('data.values');

        $this->assertArrayNotHasKey('services', $values);
        $this->assertArrayNotHasKey('services', (array) $recipe->refresh()->extraRaw());
        $this->assertSame([7], $recipe->relatedIds('services'));

        $this->actingAs($this->editor(), 'cms')->getJson($this->api())->assertOk()->assertJsonPath('filters.services', null);

        $this->get('/recipes/porridge')->assertOk();
    }
}
