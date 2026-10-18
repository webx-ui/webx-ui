<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Keys\Key;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Scopes;
use WebxUi\Mcp\Server\RegistryResource;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Tasks\Models\JournalEntry;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;
use WebxUi\Tasks\Tests\Fixtures\Keyed;
use WebxUi\Tasks\Tests\Fixtures\Scoped;
use WebxUi\Tasks\Workflow\StageStatus;
use WebxUi\Tasks\Workflow\TaskStatus;

/**
 * The queue by the agent's door (§5, §10): the tools, the four scopes, and the line between a
 * person and a program.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_module_offers_the_tools_and_resources_of_the_spec(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame([
            'tasks_projects_list', 'tasks_projects_create', 'tasks_projects_update', 'tasks_list', 'tasks_get',
            'tasks_queue', 'tasks_create', 'tasks_update', 'tasks_ready', 'tasks_claim_next', 'tasks_stage_report',
            'tasks_stage_accept', 'tasks_stage_rework', 'tasks_stage_release', 'tasks_stage_block',
            'tasks_stage_unblock', 'tasks_comment', 'tasks_cancel',
        ], array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('tasks')));

        $scopes = [];

        foreach ($registry->toolsOf('tasks') as $tool) {
            $scopes[$tool->fullName()] = $tool->scopes();
        }

        $this->assertSame(['tasks:read'], $scopes['tasks_queue']);
        $this->assertSame(['tasks:write'], $scopes['tasks_create']);
        $this->assertSame(['tasks:work'], $scopes['tasks_claim_next']);
        $this->assertSame(['tasks:work'], $scopes['tasks_stage_block']);
        $this->assertSame(['tasks:review'], $scopes['tasks_stage_accept']);
        $this->assertSame(['tasks:review'], $scopes['tasks_stage_rework']);
        $this->assertSame(['tasks:write', 'tasks:work', 'tasks:review'], $scopes['tasks_comment']);
        $this->assertSame(['tasks:write', 'tasks:work'], $scopes['tasks_stage_release']);
        $this->assertSame(['tasks.manage', 'tasks.work'], $registry->tool('tasks_stage_release')->permissions());
        $this->assertSame(['tasks.review'], $registry->tool('tasks_stage_accept')->permissions());

        $uris = array_map(static fn ($resource): string => $resource->uri, $registry->resources());
        $this->assertContains('tasks://rules', $uris);
        $this->assertContains('tasks://projects/{slug}', $uris);
    }

    #[Test]
    public function a_person_plans_agents_work_and_review_and_the_person_decides_the_human_stage(): void
    {
        $person = $this->holder(['tasks.view', 'tasks.manage', 'tasks.review'], [Scopes::OAUTH]);
        $worker = $this->holder(['tasks.view', 'tasks.work'], ['tasks:read', 'tasks:work']);
        $dispatcher = $this->holder(['tasks.view', 'tasks.work', 'tasks.review'], ['tasks:read', 'tasks:work', 'tasks:review']);

        $this->content($this->tool('tasks_projects_create', ['slug' => 'shop', 'title' => 'Shop', 'workdir' => '/work/shop'], $person));
        $created = $this->content($this->tool('tasks_create', [
            'project' => 'shop',
            'title' => 'Cart page',
            'spec' => 'The spec.',
            'stages' => [
                ['title' => 'Schema', 'brief' => 'Tables.'],
                ['title' => 'Design', 'brief' => 'Screens.', 'review_mode' => 'human'],
            ],
        ], $person));

        $this->assertSame('shop#1', $created['task']['ref']);
        $this->assertSame('draft', $created['task']['status']);
        $this->tool('tasks_ready', ['task' => 'shop#1'], $person)->assertOk();

        // The worker takes, reports — and may not accept its own work: it has no review scope.
        $claimed = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-1'], $worker))['claimed'];
        $this->assertSame('Schema', $claimed['stage']['title']);
        $this->assertSame('/work/shop', $claimed['project']['workdir']);

        $stage = $claimed['stage']['id'];
        $this->tool('tasks_stage_report', ['stage' => $stage, 'report' => '## Done', 'result_ref' => 'abc', 'agent' => 'worker-1'], $worker)->assertOk();
        $this->tool('tasks_stage_accept', ['stage' => $stage, 'agent' => 'worker-1'], $worker)->assertHasErrors(['[tasks:review]']);

        $this->tool('tasks_stage_accept', ['stage' => $stage, 'agent' => 'dispatcher'], $dispatcher)->assertOk();

        // The human stage: done by an agent, refused to the dispatcher, decided by the person.
        $stage = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-2'], $worker))['claimed']['stage']['id'];
        $this->tool('tasks_stage_report', ['stage' => $stage, 'report' => 'Screens are in.', 'agent' => 'worker-2'], $worker)->assertOk();

        $this->tool('tasks_stage_accept', ['stage' => $stage, 'agent' => 'dispatcher'], $dispatcher)
            ->assertHasErrors(['checked by a person']);

        // Without a name the dispatcher's key is still a program's, never a person's.
        $this->tool('tasks_stage_accept', ['stage' => $stage], $dispatcher)
            ->assertHasErrors(['issued for a program']);

        $waiting = $this->content($this->tool('tasks_list', ['waiting_for_person' => true], $person));
        $this->assertSame(['shop#1'], array_column($waiting['tasks'], 'ref'));

        $this->tool('tasks_stage_accept', ['stage' => $stage, 'note' => 'Looks right.'], $person)->assertOk();

        $task = Task::query()->sole();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame(
            $person->name,
            JournalEntry::query()->where('stage_id', $stage)->where('meta->to', 'done')->sole()->actor_name,
        );
    }

    #[Test]
    public function a_key_issued_without_scopes_is_still_a_program(): void
    {
        $this->task(stages: ['Design'], human: ['Design']);
        $worker = $this->holder(['tasks.view', 'tasks.work'], ['tasks:read', 'tasks:work']);

        $stage = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-1'], $worker))['claimed']['stage']['id'];
        $this->tool('tasks_stage_report', ['stage' => $stage, 'report' => 'Screens are in.', 'agent' => 'worker-1'], $worker)->assertOk();

        // `mcp:use`, the scope an OAuth connection carries — and a key issued for everything the
        // administrator may do. What tells the two apart is the key's row, not the scope.
        $watcher = $this->member(['tasks.view', 'tasks.manage', 'tasks.review']);
        Key::query()->create([
            'cms_user_id' => $watcher->getKey(),
            'token_id' => 'watcher-key',
            'name' => 'queue watcher',
            'scopes' => [Scopes::OAUTH],
            'created_at' => now(),
        ]);
        $watcher->withAccessToken(new Keyed('watcher-key', [Scopes::OAUTH]));

        $this->tool('tasks_stage_accept', ['stage' => $stage], $watcher)->assertHasErrors(['issued for a program']);
        $this->tool('tasks_stage_accept', ['stage' => $stage, 'agent' => 'watcher'], $watcher)->assertHasErrors(['checked by a person']);
        $this->assertSame(StageStatus::Review, Stage::query()->findOrFail($stage)->status);
    }

    #[Test]
    public function only_a_person_answers_a_question(): void
    {
        $this->task(stages: ['One']);
        $worker = $this->holder(['tasks.view', 'tasks.work', 'tasks.manage'], ['tasks:read', 'tasks:work', 'tasks:write']);

        $stage = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-1'], $worker))['claimed']['stage']['id'];
        $this->tool('tasks_stage_block', ['stage' => $stage, 'question' => 'Which provider?', 'agent' => 'worker-1'], $worker)->assertOk();

        $this->tool('tasks_stage_unblock', ['stage' => $stage, 'answer' => 'Any.'], $worker)->assertHasErrors(['issued for a program']);
        $this->assertSame(StageStatus::Blocked, Stage::query()->findOrFail($stage)->status);

        $person = $this->holder(['tasks.view', 'tasks.manage'], [Scopes::OAUTH]);
        $this->tool('tasks_stage_unblock', ['stage' => $stage, 'answer' => 'The configured one.'], $person)->assertOk();

        $again = $this->content($this->tool('tasks_claim_next', ['agent' => 'worker-2'], $worker))['claimed'];
        $this->assertSame('The configured one.', $again['stage']['questions'][0]['answer']);
    }

    #[Test]
    public function a_dry_run_writes_nothing_and_answers_what_the_write_would(): void
    {
        $this->project('shop');
        $person = $this->holder(['tasks.view', 'tasks.manage'], [Scopes::OAUTH]);

        $would = $this->content($this->tool('tasks_create', [
            'project' => 'shop',
            'title' => 'Rehearsed',
            'stages' => [['title' => 'One'], ['title' => 'Two']],
            'dry_run' => true,
        ], $person));

        $this->assertTrue($would['dry_run']);
        $this->assertSame(['One', 'Two'], array_column($would['task']['stages'], 'title'));
        $this->assertSame(0, Task::query()->count());
        $this->assertSame(0, Stage::query()->count());
        $this->assertSame(0, JournalEntry::query()->count());

        // A rehearsed claim leaves the stage where it was.
        $task = $this->task(stages: ['One']);
        $worker = $this->holder(['tasks.view', 'tasks.work'], ['tasks:read', 'tasks:work']);
        $this->assertSame('One', $this->content($this->tool('tasks_claim_next', ['agent' => 'w', 'dry_run' => true], $worker))['claimed']['stage']['title']);
        $this->assertSame(StageStatus::Ready, $this->stage($task, 1)->status);
    }

    #[Test]
    public function nothing_to_take_is_an_empty_answer_and_a_claim_needs_a_name(): void
    {
        $worker = $this->holder(['tasks.view', 'tasks.work'], ['tasks:read', 'tasks:work']);

        $this->assertNull($this->content($this->tool('tasks_claim_next', ['agent' => 'worker-1'], $worker))['claimed']);
        $this->tool('tasks_claim_next', [], $worker)->assertHasErrors(['agent']);
    }

    #[Test]
    public function a_comment_is_open_to_the_planner_the_worker_and_the_reviewer_and_closed_to_a_reader(): void
    {
        $this->task(stages: ['One']);

        foreach ([['tasks:write'], ['tasks:work']] as $scopes) {
            $this->tool('tasks_comment', ['task' => 'shop#1', 'body' => 'Noted.', 'agent' => 'a'], $this->holder(['tasks.manage', 'tasks.work'], $scopes))->assertOk();
        }

        // A reviewer's key, as the panel lets a reviewer comment.
        $this->tool('tasks_comment', ['task' => 'shop#1', 'body' => 'Noted.', 'agent' => 'a'], $this->holder(['tasks.review'], ['tasks:review']))->assertOk();

        $this->tool('tasks_comment', ['task' => 'shop#1', 'body' => 'Noted.', 'agent' => 'a'], $this->holder(['tasks.view'], ['tasks:read']))
            ->assertHasErrors(['[tasks:write] or [tasks:work] or [tasks:review]']);
    }

    #[Test]
    public function the_queue_shows_what_waits_for_somebody(): void
    {
        $project = $this->project();
        $this->task($project, ['A']);
        $this->task($project, ['B'], ready: false);
        $this->claim('worker-1');
        $this->task($project, ['C']);

        $queue = $this->content($this->tool('tasks_queue', [], $this->holder(['tasks.view'], ['tasks:read'])));

        $this->assertSame(['rework' => 0, 'ready' => 1, 'in_progress' => 1, 'review' => 0, 'blocked' => 0], $queue['counts']);
        $this->assertSame(['shop#1', 'shop#3'], array_column($queue['stages'], 'task'));
        $this->assertSame('worker-1', $queue['stages'][0]['assignee']);
    }

    #[Test]
    public function a_task_is_read_by_its_reference_with_stages_and_journal(): void
    {
        $this->task(stages: ['One', 'Two']);

        $task = $this->content($this->tool('tasks_get', ['task' => 'shop#1'], $this->holder(['tasks.view'], ['tasks:read'])))['task'];

        $this->assertSame(['One', 'Two'], array_column($task['stages'], 'title'));
        $this->assertSame('The **spec**.', $task['spec']);
        $this->assertSame(['draft', 'ready', 'ready'], array_map(static fn (array $entry): string => $entry['meta']['to'], $task['journal']));

        $this->tool('tasks_get', ['task' => 'shop#9'], $this->holder(['tasks.view'], ['tasks:read']))->assertHasErrors(['No such task']);
    }

    #[Test]
    public function a_refusal_of_the_rules_reaches_the_agent_in_words(): void
    {
        $task = $this->task(stages: ['One']);
        $person = $this->holder(['tasks.view', 'tasks.manage'], [Scopes::OAUTH]);

        $this->tool('tasks_update', ['task' => $task->id, 'spec' => 'Changed.'], $person)
            ->assertHasErrors(['only its priority']);
        $this->tool('tasks_update', ['task' => $task->id, 'stages' => [['id' => $this->stage($task, 1)->id, 'titel' => 'x']]], $person)
            ->assertHasErrors(['[titel]']);
    }

    #[Test]
    public function the_rules_and_a_project_are_read_by_address(): void
    {
        $this->project('shop');
        $resources = $this->app->make(ToolRegistry::class)->resources();
        $byUri = array_combine(array_map(static fn ($resource): string => $resource->uri, $resources), $resources);

        WebxServer::resource(RegistryResource::for($byUri['tasks://rules']))
            ->assertOk()
            ->assertSee('tasks_stage_block');

        WebxServer::resource(RegistryResource::for($byUri['tasks://projects/{slug}']), ['slug' => 'shop'])
            ->assertOk()
            ->assertSee('Run the gate before reporting.');
    }

    /**
     * An administrator with these permissions, calling with a token that carries these scopes.
     *
     * @param  list<string>  $permissions
     * @param  list<string>  $scopes
     */
    private function holder(array $permissions, array $scopes): CmsUser
    {
        $user = $this->member($permissions);
        $user->withAccessToken(new Scoped($scopes));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function tool(string $tool, array $arguments, CmsUser $as): TestResponse
    {
        $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));

        return WebxServer::actingAs($as, 'cms')->tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $response->assertOk();
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
