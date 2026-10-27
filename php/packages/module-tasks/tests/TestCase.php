<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Closure;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Testing\MigratesOnce;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Tasks\Exceptions\TaskRefused;
use WebxUi\Tasks\Models\Project;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\TasksServiceProvider;
use WebxUi\Tasks\Workflow\Actor;
use WebxUi\Tasks\Workflow\Dispatcher;
use WebxUi\Tasks\Workflow\Tasks;

abstract class TestCase extends Orchestra
{
    use MigratesOnce;

    /** One person per test, made on first use: the database is new for every test. */
    private ?Actor $person = null;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            AdminServiceProvider::class,
            McpServiceProvider::class,
            AuthServiceProvider::class,
            SettingsServiceProvider::class,
            TasksServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.url', 'https://tasks.example.test');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true]]);
        // sqlite ignores foreign keys unless asked, and a cascade that does not exist would pass.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function tasks(): Tasks
    {
        return $this->app->make(Tasks::class);
    }

    protected function dispatcher(): Dispatcher
    {
        return $this->app->make(Dispatcher::class);
    }

    /** The project of this slug, made on first use. */
    protected function project(string $slug = 'shop'): Project
    {
        return Project::query()->where('slug', $slug)->first() ?? $this->tasks()->createProject([
            'slug' => $slug,
            'title' => ucfirst($slug),
            'workdir' => "/work/{$slug}",
            'repo_url' => "https://git.example.test/{$slug}.git",
            'default_branch' => 'main',
            'rules' => 'Run the gate before reporting.',
        ]);
    }

    /**
     * A task with a stage for every title given; the titles in `$human` are checked by a person.
     *
     * @param  list<string>  $stages
     * @param  array<string, mixed>  $values
     * @param  list<string>  $human
     */
    protected function task(?Project $project = null, array $stages = ['One', 'Two'], array $values = [], bool $ready = true, array $human = []): Task
    {
        $task = $this->tasks()->create(
            $project ?? $this->project(),
            ['title' => 'Cart page', 'spec' => 'The **spec**.', 'acceptance' => 'It works.', 'branch' => 'feat/cart', ...$values],
            array_map(static fn (string $title): array => [
                'title' => $title,
                'brief' => "Do {$title}.",
                'acceptance' => "{$title} done.",
                'review_mode' => in_array($title, $human, true) ? 'human' : 'auto',
            ], $stages),
            $this->person(),
        );

        return $ready ? $this->tasks()->ready($task, $this->person()) : $task;
    }

    protected function admin(): CmsUser
    {
        static $count = 0;
        $count++;

        return CmsUser::query()->create([
            'name' => "Person {$count}",
            'email' => "person-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Somebody the panel lets in, with these permissions and nothing else.
     *
     * @param  list<string>  $permissions
     */
    protected function member(array $permissions): CmsUser
    {
        static $count = 0;
        $count++;

        $user = CmsUser::query()->create([
            'name' => "Member {$count}",
            'email' => "member-{$count}@example.test",
            'password' => 'correct-horse-battery',
            'is_super' => false,
            'is_active' => true,
        ]);

        $role = Role::query()->create(['slug' => "member-{$count}", 'name' => 'Member', 'permissions' => $permissions]);
        $user->roles()->attach($role->getKey());

        return $user;
    }

    protected function person(): Actor
    {
        return $this->person ??= Actor::admin($this->admin());
    }

    protected function agent(string $name = 'worker-1'): Actor
    {
        return Actor::agent($name);
    }

    protected function stage(Task $task, int $position): Stage
    {
        return Stage::query()->where('task_id', $task->id)->where('position', $position)->firstOrFail();
    }

    /** The stage an agent was handed, by the id in its context package. */
    protected function claim(string $agent = 'worker-1', ?Project $project = null): ?Stage
    {
        $package = $this->dispatcher()->claimNext($this->agent($agent), $project);

        return $package === null ? null : Stage::query()->findOrFail($package['stage']['id']);
    }

    /**
     * The operation is refused for this reason, in words and not as a bare key.
     */
    protected function assertRefused(string $reason, Closure $operation): void
    {
        try {
            $operation();
        } catch (TaskRefused $refusal) {
            $this->assertSame($reason, $refusal->reason, $refusal->getMessage());
            $this->assertNotSame("webx-tasks::errors.{$reason}", $refusal->getMessage(), 'The refusal has no words.');

            return;
        }

        $this->fail("Expected the refusal «{$reason}», and nothing was refused.");
    }
}
