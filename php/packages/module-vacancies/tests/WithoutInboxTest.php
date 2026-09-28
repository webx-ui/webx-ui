<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Relations\Relations;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Vacancies\Demo\VacanciesDemo;

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

    #[Test]
    public function an_agent_is_not_told_about_a_form_and_is_refused_one(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertStringNotContainsString('inbox_forms_list', $registry->tool('vacancies_update')->tool->description);

        $vacancy = $this->vacancy('designer');
        $agent = WebxServer::actingAs($this->editor(), 'cms');

        $agent->tool(new RegistryTool($registry->tool('vacancies_update')), ['vacancy' => $vacancy->id, 'values' => ['form' => 'job-application']])
            ->assertHasErrors(['no inbox module']);

        // And the answer carries no `form` that would always be null.
        $agent->tool(new RegistryTool($registry->tool('vacancies_get')), ['vacancy' => $vacancy->id])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json->missing('vacancy.form')->etc());

        // The demo asks for no inbox demo either: a name it gave that is not installed would skip it whole.
        $this->assertSame([], $this->app->make(VacanciesDemo::class)->requires());

        // Nor the catalogue.
        $vacancy->syncCategories([$this->category('design')->id]);

        foreach ($registry->resources() as $resource) {
            if ($resource->uri === 'vacancies://catalog') {
                $this->assertArrayNotHasKey('form', ($resource->handler)()['categories'][0]['open'][0]);
            }
        }
    }
}
