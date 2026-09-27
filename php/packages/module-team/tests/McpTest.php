<?php

declare(strict_types=1);

namespace WebxUi\Team\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Facades\Screens;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Team\Models\Member;

/**
 * The team by its other doors (§5.8): the same list, the same screen, the same order code.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_section_offers_its_tools_and_the_catalogue(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['team_list', 'team_get', 'team_create', 'team_update', 'team_delete', 'team_reorder'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('team')),
        );

        $this->assertSame(['team.view', 'team.manage'], $registry->tool('team_list')->permissions());
        $this->assertSame(['team.manage'], $registry->tool('team_reorder')->permissions());
        $this->assertArrayHasKey('services', $registry->tool('team_create')->tool->inputSchema['properties'] ?? []);

        $this->assertContains('team://catalog', array_map(static fn ($resource): string => $resource->uri, $registry->resources()));
    }

    #[Test]
    public function a_reader_lists_and_is_refused_a_write(): void
    {
        $reader = $this->editor(['team.view']);

        $this->agent('team_list', [], $reader)->assertOk();
        $this->agent('team_create', ['name' => 'Nobody'], $reader)->assertHasErrors(['[team.manage]']);
    }

    #[Test]
    public function create_writes_through_the_screen(): void
    {
        $braces = $this->service('braces');
        $implants = $this->service('implants');
        $this->picture('media/ab/cd/anna.jpg');

        $created = $this->content($this->agent('team_create', [
            'name' => 'Anna Petrova',
            'job_title' => ['en' => 'Orthodontist', 'ru' => 'Ортодонт'],
            'text' => 'Twelve years of braces.',
            'photo' => 'media/ab/cd/anna.jpg',
            'socials' => [['network' => 'instagram', 'url' => 'https://instagram.com/anna']],
            // An id and an address: both are what an agent reads off services://catalog.
            'services' => [$braces->id, '/services/implants'],
        ]));

        $this->assertFalse($created['values']['published']);
        // A plain string is the default language, not the language of the request.
        $this->assertSame(['en' => 'Anna Petrova'], $created['values']['name']);
        $this->assertSame(['en' => 'Twelve years of braces.'], $created['values']['text']);
        $this->assertSame('media/ab/cd/anna.jpg', $created['member']['photo']);
        $this->assertSame([['network' => 'instagram', 'url' => 'https://instagram.com/anna']], $created['member']['socials']);
        $this->assertSame([$braces->id, $implants->id], $created['member']['services']);
        $this->assertSame(['en'], $created['member']['written_in']);

        $id = $created['member']['id'];

        $this->agent('team_update', ['member' => $id, 'values' => ['published' => true, 'text' => ['ru' => 'Двенадцать лет брекетов.'], 'services' => []]])->assertOk();

        $member = Member::query()->findOrFail($id);
        $this->assertTrue($member->published);
        $this->assertSame(['en' => 'Twelve years of braces.', 'ru' => 'Двенадцать лет брекетов.'], $member->getTranslations('text'));
        $this->assertSame([], $member->relatedIds(Member::SERVICES));
    }

    #[Test]
    public function a_refused_create_leaves_nobody_behind(): void
    {
        // A field of the project refusing its value: the person named in the same call is not left
        // behind without it.
        Screens::extend('team.form', [['op' => 'add', 'target' => 'project-fields', 'node' => [
            'id' => 'experience', 'type' => 'wx-input-number', 'name' => 'experience', 'label' => 'Experience', 'props' => ['min' => 1],
        ]]]);

        $this->agent('team_create', ['name' => 'Half written', 'values' => ['experience' => 0]])->assertHasErrors(['experience']);
        $this->agent('team_create', ['name' => 'Scripted', 'socials' => [['network' => 'x', 'url' => 'javascript:alert(1)']]])->assertHasErrors(['socials.0.url']);
        $this->agent('team_create', ['name' => 'Linked', 'services' => [999]])->assertHasErrors(['[999]']);
        $this->agent('team_create', ['name' => 'Addressed', 'services' => ['/services/nowhere']])->assertHasErrors(['No service answers']);
        $this->agent('team_create', ['name' => 'Pictured', 'photo' => 'media/no/such.jpg'])->assertHasErrors(['no file']);
        $this->agent('team_create', ['name' => ''])->assertHasErrors(['cannot be empty']);

        $this->assertSame(0, Member::withTrashed()->count());
    }

    #[Test]
    public function a_network_the_site_does_not_have_is_refused_with_the_ones_it_has(): void
    {
        $this->agent('team_create', ['name' => 'Anna', 'socials' => [['network' => 'myspace', 'url' => 'https://myspace.com/anna']]])
            ->assertHasErrors(['no network [myspace]', 'instagram', 'tiktok']);

        $this->assertSame(0, Member::withTrashed()->count());
    }

    #[Test]
    public function a_link_to_a_network_since_dropped_goes_back_as_it_came(): void
    {
        $member = $this->member('Igor', attributes: ['socials' => [
            ['network' => 'vk', 'url' => 'https://vk.com/igor'],
            ['network' => 'x', 'url' => 'https://x.com/igor'],
        ]]);

        // What team_get handed out, sent back with one more link.
        $socials = $this->content($this->agent('team_get', ['member' => $member->id]))['values']['socials'];
        $socials[] = ['network' => 'telegram', 'url' => 'https://t.me/igor'];

        $this->agent('team_update', ['member' => $member->id, 'values' => ['socials' => $socials]])->assertOk();

        $this->assertSame(['vk', 'x', 'telegram'], array_column($member->refresh()->socialLinks(), 'network'));
        // A new link to it is still a network the site does not have.
        $this->agent('team_update', ['member' => $member->id, 'values' => ['socials' => [['network' => 'vk', 'url' => 'https://vk.com/other']]]])
            ->assertHasErrors(['no network [vk]']);
    }

    #[Test]
    public function update_keeps_the_languages_it_was_not_given(): void
    {
        $member = $this->member('Anna', 'Работает здесь.');

        $this->agent('team_update', ['member' => $member->id, 'values' => ['text' => 'Works here, still.']])->assertOk();

        $this->assertSame(['en' => 'Works here, still.', 'ru' => 'Работает здесь.'], $member->refresh()->getTranslations('text'));

        $this->agent('team_update', ['member' => $member->id, 'values' => ['text' => ['ru' => '']]])->assertOk();
        $this->assertSame(['en' => 'Works here, still.'], $member->refresh()->getTranslations('text'));
    }

    #[Test]
    public function reordering_is_the_one_order_and_the_list_shows_it(): void
    {
        $first = $this->member('First');
        $second = $this->member('Second');
        $third = $this->member('Third');

        $listed = $this->content($this->agent('team_reorder', ['members' => [$third->id, $first->id]]));

        $this->assertSame([$third->id, $first->id, $second->id], array_column($listed['members'], 'id'));
        $this->agent('team_reorder', ['members' => [$first->id, 'Anna']])->assertHasErrors(['their id']);
    }

    #[Test]
    public function a_person_is_named_by_their_id_only(): void
    {
        $this->member('Anna');

        $this->agent('team_get', ['member' => 'Anna'])->assertHasErrors(['their id']);
        $this->agent('team_get', ['member' => 999])->assertHasErrors(['[999]']);
    }

    #[Test]
    public function delete_puts_them_in_the_bin(): void
    {
        $member = $this->member('Gone');

        $this->agent('team_delete', ['member' => $member->id, 'dry_run' => true])->assertOk();
        $this->assertFalse($member->refresh()->trashed());

        $this->agent('team_delete', ['member' => (string) $member->id])->assertOk();

        $this->assertTrue($member->refresh()->trashed());
        $this->assertSame([$member->id], array_column($this->content($this->agent('team_list', ['trashed' => true]))['members'], 'id'));
        $this->agent('team_update', ['member' => $member->id, 'values' => ['published' => false]])->assertHasErrors(['in the bin']);
    }

    #[Test]
    public function the_catalogue_lists_everybody_in_order_with_the_networks(): void
    {
        $braces = $this->service('braces');
        $anna = $this->member('Anna', 'Работает здесь.');
        $draft = $this->member('Draft', published: false);
        $gone = $this->member('Gone');
        $gone->delete();
        $anna->syncRelated(Member::SERVICES, 'service', [$braces->id]);

        $catalog = ($this->resource('team://catalog')->handler)();

        $this->assertSame('Instagram', $catalog['networks']['instagram']);
        $this->assertSame([$anna->id, $draft->id], array_column($catalog['members'], 'id'));
        $this->assertSame('Anna', $catalog['members'][0]['name']);
        $this->assertSame(['en', 'ru'], $catalog['members'][0]['written_in']);
        $this->assertSame([$braces->id], $catalog['members'][0]['services']);
        $this->assertFalse($catalog['members'][1]['published']);
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
