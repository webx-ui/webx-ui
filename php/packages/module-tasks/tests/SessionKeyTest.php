<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Tests;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Models\Role;
use WebxUi\Mcp\Keys\Key;
use WebxUi\Tasks\Models\SessionKey;
use WebxUi\Tasks\Workflow\StageStatus;

/**
 * The watcher's account and the keys of one stage (§14.1, §14.4), with real tokens over HTTP: a
 * stage's key sees and changes its own stage only, acts under the name it was issued for, and is
 * turned away at the door once the stage has moved on.
 */
final class SessionKeyTest extends PassportTestCase
{
    #[Test]
    public function the_command_makes_the_role_the_account_and_a_key_that_replaces_the_last(): void
    {
        $first = $this->runnerKey();

        $role = Role::query()->where('slug', 'runner')->sole();
        $this->assertEqualsCanonicalizing(['tasks.run', 'tasks.work', 'tasks.review'], $role->permissions);

        $user = CmsUser::query()->where('name', 'taskd')->sole();
        $this->assertTrue($user->roles()->whereKey($role->getKey())->exists());
        $this->assertFalse((bool) $user->is_super);

        $this->assertSame(['tasks:run'], Key::query()->sole()->scopes);
        $this->answer($this->mcp('tasks_runner_config', $first));

        $second = $this->runnerKey();

        $this->assertSame(401, $this->mcp('tasks_runner_config', $first)->status(), 'The key issued before still works.');
        $this->answer($this->mcp('tasks_runner_config', $second));
        $this->assertSame(1, Role::query()->where('slug', 'runner')->count());
        $this->assertSame(1, CmsUser::query()->where('name', 'taskd')->count());
    }

    #[Test]
    public function a_workers_key_does_its_stage_and_nothing_else_and_dies_with_the_report(): void
    {
        $runner = $this->runnerKey();
        $mine = $this->task(values: ['title' => 'Mine']);
        $other = $this->task(values: ['title' => 'Other']);

        $claimed = $this->answer($this->mcp('tasks_claim_next', $runner, ['agent' => 'worker-7']))['claimed'];
        $stage = (int) $claimed['stage']['id'];
        $this->assertSame($mine->id, $claimed['task']['id']);

        // The watcher's own key does no stage.
        $this->assertStringContainsString('tasks_stage_report', $this->refusal($this->mcp('tasks_stage_report', $runner, ['stage' => $stage, 'report' => 'Done.', 'agent' => 'worker-7'])));

        $issued = $this->answer($this->mcp('tasks_session_key', $runner, ['stage' => $stage, 'role' => 'worker']))['key'];
        $this->assertSame('worker-7', $issued['agent']);
        $key = (string) $issued['token'];

        // Its own task, yes; another one, no — not even to look.
        $this->assertSame($mine->id, $this->answer($this->mcp('tasks_get', $key, ['task' => $mine->id]))['task']['id']);
        $this->assertStringContainsString('only', $this->refusal($this->mcp('tasks_get', $key, ['task' => $other->id])));
        $this->assertStringContainsString('nothing else', $this->refusal($this->mcp('tasks_list', $key)));
        $this->assertStringContainsString('nothing else', $this->refusal($this->mcp('tasks_claim_next', $key, ['agent' => 'worker-7'])));
        $this->assertStringContainsString('nothing else', $this->refusal($this->mcp('tasks_stage_release', $key, ['stage' => $stage])));

        // The other task's stage is out of reach, and so is another name.
        $theirs = $this->stage($other, 1)->id;
        $this->assertStringContainsString('only', $this->refusal($this->mcp('tasks_stage_report', $key, ['stage' => $theirs, 'report' => 'Done.'])));
        $this->assertStringContainsString('worker-7', $this->refusal($this->mcp('tasks_stage_report', $key, ['stage' => $stage, 'report' => 'Done.', 'agent' => 'worker-8'])));
        $this->assertSame(StageStatus::Ready, $this->stage($other, 1)->fresh()?->status);

        // A comment on its task names it as the agent of the key.
        $this->answer($this->mcp('tasks_comment', $key, ['task' => $mine->id, 'body' => 'Starting.']));
        $this->assertSame('worker-7', $mine->journal()->where('kind', 'comment')->sole()->actor_name);

        // The report goes through without `agent`: the key says who it is.
        $reported = $this->answer($this->mcp('tasks_stage_report', $key, ['report' => '## Done', 'result_ref' => 'feat/cart']));
        $this->assertSame('review', $reported['stage']['status']);
        $this->assertSame('worker-7', $reported['stage']['assignee']);

        $this->assertSame(401, $this->mcp('tasks_get', $key, ['task' => $mine->id])->status());
        $this->assertNotNull(SessionKey::query()->sole()->revoked_at);
    }

    #[Test]
    public function a_reviewers_key_decides_its_stage_only_and_not_as_the_worker(): void
    {
        $runner = $this->runnerKey();
        $task = $this->task(stages: ['One', 'Two']);

        $stage = (int) $this->answer($this->mcp('tasks_claim_next', $runner, ['agent' => 'worker-7']))['claimed']['stage']['id'];

        // Not yet: a reviewer's key is for a reported stage.
        $this->assertStringContainsString('review', $this->refusal($this->mcp('tasks_session_key', $runner, ['stage' => $stage, 'role' => 'reviewer', 'agent' => 'reviewer-1'])));

        $worker = (string) $this->answer($this->mcp('tasks_session_key', $runner, ['stage' => $stage, 'role' => 'worker']))['key']['token'];
        $this->answer($this->mcp('tasks_stage_report', $worker, ['report' => 'Done.']));

        $this->assertStringContainsString('own work', $this->refusal($this->mcp('tasks_session_key', $runner, ['stage' => $stage, 'role' => 'reviewer', 'agent' => 'worker-7'])));
        $dry = $this->answer($this->mcp('tasks_session_key', $runner, ['stage' => $stage, 'role' => 'reviewer', 'agent' => 'reviewer-1', 'dry_run' => true]));
        $this->assertArrayNotHasKey('key', $dry);
        $this->assertSame(1, SessionKey::query()->count(), 'A dry run issued a key.');

        $reviewer = (string) $this->answer($this->mcp('tasks_session_key', $runner, ['stage' => $stage, 'role' => 'reviewer', 'agent' => 'reviewer-1']))['key']['token'];

        // Out of its scopes, so not even in its list.
        $this->assertStringContainsString('not found', $this->refusal($this->mcp('tasks_stage_report', $reviewer, ['stage' => $stage, 'report' => 'Mine now.'])));
        $this->assertStringContainsString('nothing else', $this->refusal($this->mcp('tasks_queue', $reviewer)));
        $this->assertStringContainsString('only', $this->refusal($this->mcp('tasks_stage_accept', $reviewer, ['stage' => $this->stage($task, 2)->id])));

        $accepted = $this->answer($this->mcp('tasks_stage_accept', $reviewer, ['note' => 'Matches the criteria.']));
        $this->assertSame('done', $accepted['stage']['status']);
        $this->assertSame('reviewer-1', $task->journal()->where('kind', 'status')->where('meta->to', 'done')->sole()->actor_name);

        $this->assertSame(401, $this->mcp('tasks_get', $reviewer, ['task' => $task->id])->status());
    }

    #[Test]
    public function a_stage_given_back_takes_its_workers_key_with_it(): void
    {
        $runner = $this->runnerKey();
        $task = $this->task();

        $stage = (int) $this->answer($this->mcp('tasks_claim_next', $runner, ['agent' => 'worker-7']))['claimed']['stage']['id'];
        $key = (string) $this->answer($this->mcp('tasks_session_key', $runner, ['stage' => $stage, 'role' => 'worker']))['key']['token'];

        $this->answer($this->mcp('tasks_stage_release', $runner, ['stage' => $stage, 'reason' => 'The session ended.', 'agent' => 'taskd']));

        $this->assertSame(401, $this->mcp('tasks_get', $key, ['task' => $task->id])->status());
    }

    private function runnerKey(): string
    {
        $status = Artisan::call('webx:tasks:runner');
        // Read once: the buffer is emptied by reading it.
        $output = trim(Artisan::output());

        $this->assertSame(0, $status, $output);

        return $output;
    }
}
