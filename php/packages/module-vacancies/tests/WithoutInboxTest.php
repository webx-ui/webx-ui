<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Inbox\InboxServiceProvider;

/**
 * A site without `module-inbox` (decision 3): no "Application form" field on the screen, no `form`
 * in the values, a save that still names one neither fails nor touches the rows the forms left
 * behind, and the rest works as it does anywhere.
 */
final class WithoutInboxTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter(
            parent::getPackageProviders($app),
            static fn (string $provider): bool => $provider !== InboxServiceProvider::class,
        ));
    }

    #[Test]
    public function the_field_is_not_on_the_screen_and_not_in_the_values(): void
    {
        $root = (array) $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/screens/vacancies.form')->assertOk()->json('data.root');

        $ids = [];
        array_walk_recursive($root, static function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id') {
                $ids[] = $value;
            }
        });

        $this->assertNotContains('form', $ids);
        $this->assertContains('categories', $ids);

        $vacancy = $this->vacancy('designer');

        $values = (array) $this->actingAs($this->editor(), 'cms')->getJson($this->api($vacancy->id))->assertOk()->json('data.values');
        $this->assertArrayNotHasKey('form', $values);
    }

    #[Test]
    public function a_save_naming_a_form_does_not_fail_and_leaves_its_rows_alone(): void
    {
        $vacancy = $this->vacancy('designer');

        // A form chosen before the module went: the row waits for it to come back.
        DB::table(Relations::TABLE)->insert([
            'owner_type' => 'vacancy',
            'owner_id' => $vacancy->id,
            'role' => 'form',
            'target_type' => 'inbox-form',
            'target_id' => 7,
            'position' => 0,
        ]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api($vacancy->id), ['values' => ['form' => [1], 'lead' => ['en' => 'Quick.']]])
            ->assertOk();

        $this->assertArrayNotHasKey('form', (array) $response->json('data.values'));
        $this->assertArrayNotHasKey('form', (array) $vacancy->refresh()->extraRaw());
        $this->assertSame([7], $vacancy->relatedIds('form'));
        $this->assertNull($vacancy->formSlug());

        $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/publish'))->assertOk();
        $this->get('/careers/designer')->assertOk();
        $this->assertNull(vacancies()->first()['form'] ?? null);

        // A copy of it does not fail either, and does not carry what nobody can see.
        $copy = $this->actingAs($this->editor(), 'cms')->postJson($this->api($vacancy->id.'/duplicate'))->assertCreated();
        $this->assertSame([], DB::table(Relations::TABLE)->where('owner_id', $copy->json('data.vacancy.id'))->pluck('target_id')->all());
    }
}
