<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Runner;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use WebxUi\Auth\Keys\KeyIssuer;
use WebxUi\Auth\Keys\KeyRefused;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Keys\Keys;
use WebxUi\Mcp\Tool;
use WebxUi\Tasks\Events\StageMoved;
use WebxUi\Tasks\Exceptions\TaskRefused;
use WebxUi\Tasks\Models\SessionKey;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Models\Task;

/**
 * Keys for one stage (§14.4): the watcher asks for one per session it starts, and the session
 * can do its stage and nothing else.
 *
 * The key is a key of the watcher's own account — its permissions are the ceiling — issued with
 * the scopes of the role and bound here to the stage and the agent's name. Every tool of the
 * module asks {@see guard()} first: a key of this kind reaches the tools of its role only, on its
 * own stage and task only, and acts under the name it was issued for whatever `agent` it is
 * given. When the stage leaves the status the key was issued in — reported, released, cancelled,
 * decided on — the key is revoked inside the same transaction, so the next call is turned away
 * at the door; nothing waits for a queue.
 */
final class SessionKeys
{
    public function __construct(
        // Asked for when a key is issued, not when the module is made: the issuer reads the
        // registry of tools, and the registry is being filled by this module.
        private readonly Container $container,
        private readonly Keys $keys,
        private readonly Config $config,
    ) {}

    /**
     * @return array{token: string, stage: int, role: string, agent: string, scopes: list<string>, expires_at: string|null}
     *
     * @throws TaskRefused when the stage is not where the role works
     * @throws ToolFailure when the account cannot be issued such a key
     */
    public function issue(Stage $stage, SessionRole $role, ?string $agent, CmsUser $owner): array
    {
        $agent = $this->agent($stage, $role, $agent);
        $scopes = $this->scopes($role);

        try {
            $issued = $this->container->make(KeyIssuer::class)->issue(
                $owner,
                "stage-{$stage->id} {$role->value} {$agent}",
                $scopes,
                max(1, (int) $this->config->get('webx-tasks.runner.session_days', 2)),
                $owner,
            );
        } catch (KeyRefused $refused) {
            throw new ToolFailure($refused->getMessage());
        }

        SessionKey::query()->create([
            'key_id' => $issued->key->getKey(),
            'stage_id' => $stage->id,
            'role' => $role,
            'agent' => $agent,
            'created_at' => Carbon::now(),
        ]);

        return [
            'token' => $issued->token,
            'stage' => $stage->id,
            'role' => $role->value,
            'agent' => $agent,
            'scopes' => $scopes,
            'expires_at' => $issued->key->expires_at?->toIso8601String(),
        ];
    }

    /**
     * The name the key would act under, checked the way the issue checks it — what a dry run says.
     */
    public function agent(Stage $stage, SessionRole $role, ?string $agent): string
    {
        $agent = $agent === null ? '' : trim($agent);

        if ($stage->status !== $role->status()) {
            throw TaskRefused::because("session-key-{$role->value}");
        }

        if ($role === SessionRole::Worker) {
            // The stage is held under a name already; the key can only be that name's.
            if ($agent !== '' && $agent !== $stage->assignee) {
                throw TaskRefused::because('not-holder', ['assignee' => (string) $stage->assignee]);
            }

            return (string) $stage->assignee;
        }

        if ($agent === '') {
            throw TaskRefused::because('agent-name');
        }

        if ($agent === $stage->assignee) {
            throw TaskRefused::because('own-work');
        }

        return $agent;
    }

    /**
     * @return list<string>
     */
    public function scopes(SessionRole $role): array
    {
        $extra = $this->config->get("webx-tasks.runner.session_scopes.{$role->value}", []);

        return array_values(array_unique([...$role->scopes(), ...(is_array($extra) ? array_map(strval(...), $extra) : [])]));
    }

    /** The key of one stage this call came in with, if it did. */
    public function forUser(?Authenticatable $user): ?SessionKey
    {
        $key = $this->keys->forUser($user);

        return $key === null ? null : SessionKey::query()->where('key_id', $key->getKey())->first();
    }

    /**
     * The keys of the stage that were good only while it stood where it stood. Run inside the
     * transaction of the move: a move that is rolled back — a dry run included — revokes nothing.
     */
    public function stageMoved(StageMoved $moved): void
    {
        $roles = array_values(array_filter(SessionRole::cases(), static fn (SessionRole $role): bool => $role->status() === $moved->from));

        if ($roles === []) {
            return;
        }

        $sessions = SessionKey::query()
            ->with('key')
            ->where('stage_id', $moved->stageId)
            ->whereIn('role', array_map(static fn (SessionRole $role): string => $role->value, $roles))
            ->whereNull('revoked_at')
            ->get();

        foreach ($sessions as $session) {
            if ($session->key !== null) {
                $this->keys->revoke($session->key);
            }

            $session->forceFill(['revoked_at' => Carbon::now()])->save();
        }
    }

    /**
     * The module's tools, each with this door in front: a stage's key goes through only to its
     * role's tools, its own stage and task, and its own name.
     *
     * @param  list<Tool>  $tools  as the module names them, without the `tasks_` in front
     * @return list<Tool>
     */
    public function guard(array $tools): array
    {
        return array_map(function (Tool $tool): Tool {
            $handler = $tool->handler;
            $name = 'tasks_'.$tool->name;
            $properties = $tool->inputSchema['properties'] ?? [];
            $takes = is_array($properties) ? array_keys($properties) : [];

            $guarded = function (array $arguments, ?Authenticatable $user = null) use ($handler, $name, $takes): mixed {
                $session = $this->forUser($user);

                return $handler($session === null ? $arguments : $this->bind($session, $name, $takes, $arguments), $user);
            };

            return $tool->mutating
                ? Tool::mutating($tool->name, $tool->description, $guarded, $tool->inputSchema, $tool->scope, $tool->permissions)
                : Tool::read($tool->name, $tool->description, $guarded, $tool->inputSchema, $tool->scope, $tool->permissions);
        }, $tools);
    }

    /**
     * The arguments of a call made with a stage's key, held to that stage.
     *
     * @param  list<array-key>  $takes  the arguments the tool's schema names
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function bind(SessionKey $session, string $tool, array $takes, array $arguments): array
    {
        if ($session->revoked_at !== null) {
            throw new ToolFailure('This key was for one stage, and that stage has moved on. The watcher issues a new key for the next session.');
        }

        if (! in_array($tool, $session->role->tools(), true)) {
            throw new ToolFailure("This key is for one {$session->role->value} session of stage {$session->stage_id}: it reaches "
                .implode(', ', $session->role->tools()).' and nothing else of the queue.');
        }

        $stage = Stage::query()->find($session->stage_id);

        if (! $stage instanceof Stage) {
            throw new ToolFailure("Stage {$session->stage_id} of this key is gone.");
        }

        if (in_array('stage', $takes, true)) {
            if (isset($arguments['stage']) && (int) $arguments['stage'] !== $stage->id) {
                throw new ToolFailure("This key is for stage {$stage->id} only.");
            }

            // The stage is what the tool acts on; a comment on the task as a whole names none.
            if ($tool !== 'tasks_comment') {
                $arguments['stage'] = $stage->id;
            }
        }

        if (in_array('task', $takes, true)) {
            if (isset($arguments['task']) && $this->taskId($arguments['task']) !== $stage->task_id) {
                throw new ToolFailure("This key is for the task of stage {$stage->id} only.");
            }

            $arguments['task'] = $stage->task_id;
        }

        if (in_array('agent', $takes, true)) {
            $named = isset($arguments['agent']) && is_string($arguments['agent']) ? trim($arguments['agent']) : '';

            if ($named !== '' && $named !== $session->agent) {
                throw new ToolFailure("This key acts as «{$session->agent}», the name it was issued for.");
            }

            $arguments['agent'] = $session->agent;
        }

        return $arguments;
    }

    private function taskId(mixed $reference): ?int
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            return (int) $reference;
        }

        if (is_string($reference) && preg_match('/^([a-z0-9][a-z0-9-]*)#(\d+)$/', $reference, $match) === 1) {
            $id = Task::query()
                ->whereHas('project', static fn ($query) => $query->where('slug', $match[1]))
                ->where('number', (int) $match[2])
                ->value('id');

            return is_numeric($id) ? (int) $id : null;
        }

        return null;
    }
}
