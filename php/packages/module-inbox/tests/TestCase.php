<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\InboxServiceProvider;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            AdminServiceProvider::class,
            AuthServiceProvider::class,
            // The door an agent will come through in session E; the section is registered
            // into the same registry either way.
            McpServiceProvider::class,
            InboxServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        $app['config']->set('webx-localization.cache.enabled', false);

        // Half of what this module promises is a foreign key — a form that cannot be deleted
        // while it has submissions, fields that go with the form, values that go with the
        // submission — and SQLite ignores every one of them unless asked not to.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);

        // Testbench has no `local` disk pointed anywhere a test should write to.
        $app['config']->set('filesystems.disks.inbox', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/inbox'),
        ]);
        $app['config']->set('webx-inbox.disk', 'inbox');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * A form with the fields it was given, each one a `[name, type, ...attributes]`.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $options
     */
    protected function form(string $slug = 'contact', array $fields = [], array $options = []): Form
    {
        $form = Form::query()->create([
            'slug' => $slug,
            'title' => ['en' => ucfirst($slug)],
            'is_enabled' => true,
            'options' => $options,
        ]);

        foreach ($fields === [] ? $this->defaultFields() : $fields as $position => $field) {
            $this->field($form, [...$field, 'position' => $position]);
        }

        return $form->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function field(Form $form, array $attributes): Field
    {
        $title = $attributes['title'] ?? ucfirst((string) ($attributes['name'] ?? 'field'));

        return $form->fields()->create([
            'type' => FieldType::Text,
            'is_enabled' => true,
            'is_required' => false,
            ...$attributes,
            'title' => is_array($title) ? $title : ['en' => $title],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultFields(): array
    {
        return [
            ['name' => 'name', 'type' => FieldType::Text, 'is_required' => true, 'in_table' => true],
            ['name' => 'email', 'type' => FieldType::Email, 'is_required' => true, 'in_table' => true],
            ['name' => 'message', 'type' => FieldType::Textarea],
        ];
    }

    /** Where a form on the site posts. */
    protected function intake(string $slug = 'contact'): string
    {
        return '/'.trim((string) config('webx-inbox.path'), '/').'/'.$slug;
    }

    /** Where the panel asks (§12). */
    protected function api(string $path): string
    {
        return '/api/cms/inbox/'.ltrim($path, '/');
    }

    /**
     * A submission, made without going through the door.
     *
     * The intake is tested on its own; everything about the panel is about rows that are
     * already there, and posting a form to make one would tie those tests to the antispam.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function submission(Form $form, array $attributes = []): Submission
    {
        return Submission::query()->create([
            'form_id' => $form->getKey(),
            'status_id' => Status::default()?->getKey(),
            'hash' => md5(uniqid('', true)),
            'source' => Submission::SOURCE_WEB,
            'meta' => [],
            ...$attributes,
        ]);
    }

    /**
     * Somebody the panel lets in, with the permissions this test wants them to have.
     *
     * @param  list<string>  $permissions
     */
    protected function editor(array $permissions = ['inbox.view', 'inbox.update', 'inbox.manage']): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => 'Editor',
            'email' => "editor-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "editor-{$count}", 'name' => 'Editor', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }
}
