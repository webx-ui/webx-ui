<?php

declare(strict_types=1);

namespace WebxUi\Auth\Tests;

use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Access\DefaultRoles;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\Locales;
use WebxUi\Localization\Models\Locale;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * A role's code is brought to its shape rather than refused (#125): whatever is typed — in the
 * form, by an agent, by a direct save — becomes `[a-z0-9-]`; one nothing is left of is made from
 * the name, and only a code already taken is refused.
 */
final class RoleCodeTest extends TestCase
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
        $this->actingAs($this->editor(), 'cms');

        $id = (int) $this->postJson('/api/cms/auth/roles', ['name' => 'Pads', 'slug' => 'Тормозные Колодки', 'permissions' => []])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'tormoznye-kolodki')
            ->json('data.id');

        $this->putJson("/api/cms/auth/roles/{$id}", ['slug' => 'Brake Pads & Discs'])->assertOk();
        $this->assertSame('brake-pads-discs', Role::query()->findOrFail($id)->slug);

        // Taken once brought: refused, with the field named.
        $this->postJson('/api/cms/auth/roles', ['name' => 'Again', 'slug' => 'BRAKE pads discs', 'permissions' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('slug');

        // Nothing left of it: the name makes the code, as when it is left out.
        $this->postJson('/api/cms/auth/roles', ['name' => 'Cache readers', 'slug' => '!!!', 'permissions' => []])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'cache-readers');

        // And a rename to nothing keeps the code the role had.
        $this->putJson("/api/cms/auth/roles/{$id}", ['slug' => '!!!'])->assertOk();
        $this->assertSame('brake-pads-discs', Role::query()->whereKey($id)->value('slug'));
    }

    #[Test]
    public function an_agent_and_a_direct_save_get_the_same_code(): void
    {
        $manager = $this->editor();
        $create = $this->app->make(ToolRegistry::class)->tool('admins_create_role')->tool->handler;

        $made = $create(['name' => 'Pads', 'slug' => 'Тормозные колодки', 'permissions' => []], $manager);

        $this->assertSame('tormoznye-kolodki', $made['role']);
        $this->assertTrue(Role::query()->where('slug', 'tormoznye-kolodki')->exists());

        $this->assertSame('pads', $create(['name' => 'Pads', 'slug' => '!!!', 'permissions' => []], $manager)['role']);

        try {
            $create(['name' => 'Again', 'slug' => 'Tormoznye Kolodki', 'permissions' => []], $manager);
            $this->fail('A taken code is refused.');
        } catch (ValidationException $refused) {
            $this->assertArrayHasKey('slug', $refused->errors());
        }

        $this->assertSame('ozhidayut-otveta', Role::query()->create(['name' => 'X', 'slug' => 'Ожидают ответа', 'permissions' => []])->slug);
    }

    #[Test]
    public function the_ready_made_roles_keep_their_codes(): void
    {
        $created = $this->app->make(DefaultRoles::class)->seed();

        $this->assertNotSame([], $created);
        $this->assertSame($created, Role::query()->orderBy('id')->pluck('slug')->all());
        $this->assertSame([], array_diff($created, array_keys($this->app->make(DefaultRoles::class)->definitions())));
    }

    private function editor(): CmsUser
    {
        $editor = $this->admin('editor@example.test');
        $editor->roles()->attach($this->role('editors', ['roles.manage']));

        return $editor->load('roles');
    }
}
