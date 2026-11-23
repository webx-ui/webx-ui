<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;
use WebxUi\Tasks\Exceptions\TaskRefused;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Runner\Heartbeats;
use WebxUi\Tasks\Runner\RunnerSettings;
use WebxUi\Tasks\Runner\SessionKeys;
use WebxUi\Tasks\Runner\SessionRole;

/**
 * The watcher's side of the queue (§14): its settings, its heartbeat, and the keys it hands the
 * sessions it starts. Behind `tasks:run` and `tasks.run` — a scope no session key carries — and
 * the status beside them, which anybody who reads the queue may look at.
 */
final class RunnerTools
{
    public const string RUN = 'tasks:run';

    public function __construct(
        private readonly RunnerSettings $settings,
        private readonly Heartbeats $heartbeats,
        private readonly SessionKeys $sessions,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        return [
            Tool::read(
                'runner_config',
                'What the watcher is told to do, as set in the panel: how many sessions at once, per project and a '
                .'day, how many attempts a stage gets, whether it is paused, and the runners it may start with their '
                .'arguments. Read it every poll — a change in the panel is the next poll\'s.',
                fn (): array => ['config' => $this->settings->all()],
                ['properties' => []],
                scope: self::RUN,
                permission: 'tasks.run',
            ),

            Tool::mutating(
                'runner_heartbeat',
                'Say, once a poll, what the watcher runs: the open windows (agent, stage, runner, since when), '
                .'whether it is paused, why it waits (the memory guard\'s admission, say), the sessions started today, '
                .'its version, and the limit of sessions it applied next to its own hard one. A watcher silent for '
                .'five minutes is shown as silent and a person is told once.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->write($arguments, fn (): array => [
                    'beat' => $this->heartbeats->present($this->heartbeats->record(
                        $this->runner($arguments, $user),
                        array_diff_key($arguments, ['runner' => true, Tool::DRY_RUN => true]),
                    )),
                    'config' => $this->settings->all(),
                ]),
                ['properties' => [
                    'runner' => ['type' => 'string', 'description' => 'The watcher\'s name; the name of the account its key is for when omitted.'],
                    'windows' => [
                        'type' => 'array',
                        'description' => 'The sessions running now.',
                        'items' => ['type' => 'object', 'properties' => [
                            'agent' => ['type' => 'string'],
                            'stage' => ['type' => 'integer'],
                            'runner' => ['type' => 'string', 'description' => 'claude, agy, …'],
                            'since' => ['type' => 'string', 'description' => 'When the session started, ISO 8601.'],
                        ]],
                    ],
                    'paused' => ['type' => 'boolean', 'description' => 'Whether the watcher takes no new stages now.'],
                    'waiting' => ['type' => 'string', 'description' => 'Why it takes nothing now, when it does not: no admission from the memory guard, the daily limit, …'],
                    'daily_sessions' => ['type' => 'integer', 'description' => 'Sessions started today.'],
                    'version' => ['type' => 'string'],
                    'hard_max_workers' => ['type' => 'integer', 'description' => 'The watcher\'s own ceiling, whatever the panel says.'],
                    'max_workers' => ['type' => 'integer', 'description' => 'The limit applied: the panel\'s, held to the ceiling.'],
                ]],
                scope: self::RUN,
                permission: 'tasks.run',
            ),

            Tool::read(
                'runner_status',
                'The watchers and what they last said: when, the windows open, the pause and why they wait — and '
                .'whether one has gone silent. With the settings they are told.',
                fn (): array => [
                    'config' => $this->settings->all(),
                    'silent_after_minutes' => $this->settings->silentAfter(),
                    'runners' => $this->heartbeats->status(),
                ],
                ['properties' => []],
                scope: ['tasks:read', self::RUN],
                permission: ['tasks.view', 'tasks.manage', 'tasks.work', 'tasks.review', 'tasks.run'],
            ),

            Tool::mutating(
                'session_key',
                'A key for one session and one stage. worker: the stage you just took for an agent — the key acts '
                .'under the name it was taken with and reaches tasks_get, tasks_stage_report, tasks_stage_block and '
                .'tasks_comment on that stage only. reviewer: a reported stage — tasks_get, tasks_stage_accept, '
                .'tasks_stage_rework and tasks_comment on it, under the agent name given. The key is revoked when the '
                .'stage moves on: reported, released or cancelled for a worker, decided on for a reviewer. It is shown '
                .'once.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->sessionKey($arguments, $user),
                ['properties' => [
                    'stage' => ['type' => 'integer', 'description' => 'The stage, by the id tasks_claim_next and tasks_queue give.'],
                    'role' => ['type' => 'string', 'enum' => ['worker', 'reviewer']],
                    'agent' => ['type' => 'string', 'description' => 'The session\'s name. For a worker the stage already holds it; for a reviewer it is required and is not the worker\'s.'],
                ], 'required' => ['stage', 'role']],
                scope: self::RUN,
                permission: 'tasks.run',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function sessionKey(array $arguments, ?Authenticatable $user): array
    {
        if (! $user instanceof CmsUser) {
            throw new ToolFailure('A key is issued for an account: call with the watcher\'s key.');
        }

        $stage = isset($arguments['stage']) && is_numeric($arguments['stage'])
            ? Stage::query()->whereHas('task')->find((int) $arguments['stage'])
            : null;

        if (! $stage instanceof Stage) {
            throw new ToolFailure('No such stage. tasks_queue gives the ids of stages.');
        }

        $role = SessionRole::tryFrom((string) ($arguments['role'] ?? ''));

        if ($role === null) {
            throw new ToolFailure('`role` is worker or reviewer.');
        }

        $agent = isset($arguments['agent']) ? (string) $arguments['agent'] : null;

        try {
            // A dry run says what would be issued and issues nothing: a key signed in a rolled-back
            // transaction would be a working secret with no row behind it.
            if (($arguments[Tool::DRY_RUN] ?? false) === true) {
                return [
                    'dry_run' => true,
                    'stage' => $stage->id,
                    'role' => $role->value,
                    'agent' => $this->sessions->agent($stage, $role, $agent),
                    'scopes' => $this->sessions->scopes($role),
                ];
            }

            return ['key' => $this->sessions->issue($stage, $role, $agent, $user)];
        } catch (TaskRefused $refusal) {
            throw new ToolFailure($refusal->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function runner(array $arguments, ?Authenticatable $user): string
    {
        $named = isset($arguments['runner']) && is_string($arguments['runner']) ? trim($arguments['runner']) : '';

        if ($named !== '') {
            return $named;
        }

        return $user instanceof CmsUser ? (string) $user->name : 'runner';
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  \Closure(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function write(array $arguments, \Closure $work): array
    {
        if (($arguments[Tool::DRY_RUN] ?? false) !== true) {
            return $work();
        }

        return ['dry_run' => true] + Rehearsal::run($work);
    }
}
