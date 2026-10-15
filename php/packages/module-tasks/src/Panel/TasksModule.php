<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Panel;

use Illuminate\Contracts\Auth\Authenticatable;
use WebxUi\Admin\AbstractModule;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Mcp\Contracts\ProvidesMcpTools;
use WebxUi\Mcp\McpResource;
use WebxUi\Mcp\ProvidesMcpDefaults;
use WebxUi\Mcp\Tool;
use WebxUi\Tasks\Mcp\TasksResources;
use WebxUi\Tasks\Mcp\TasksTools;
use WebxUi\Tasks\Models\Task;

/**
 * «Tasks»: the queue of work between people and agents (§6 of the spec).
 *
 * Four permissions, the four kinds of work of §5: looking; writing tasks and projects; doing a
 * stage — taking it, reporting it, asking about it; and reviewing a reported stage. An agent that
 * does stages is given the third and not the fourth, so it cannot accept its own work; a stage a
 * person has to check is refused to every agent however it is authorised.
 */
final class TasksModule extends AbstractModule implements ProvidesMcpTools
{
    use ProvidesMcpDefaults;

    public const ID = 'tasks';

    /** The described form of a task. */
    public const FORM = 'tasks.form';

    public function __construct(
        private readonly TasksTools $tools,
        private readonly TasksResources $resources,
    ) {}

    public function id(): string
    {
        return self::ID;
    }

    public function title(): string
    {
        return (string) __('webx-tasks::module.title');
    }

    public function icon(): string
    {
        return 'clipboard';
    }

    public function order(): int
    {
        return 50;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return ['tasks.view', 'tasks.manage', 'tasks.work', 'tasks.review'];
    }

    /**
     * `badge` — how many tasks wait for a person (§6.1, "waiting for me"), shown beside the entry in
     * the menu. Only to whoever can act on them: a person who may only look has nothing to do about
     * a number, and an empty badge is no badge.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        $user = request()->user();

        if (! $user instanceof Authenticatable || ($user instanceof HasPermissions
            && ! $user->hasPermission('tasks.review') && ! $user->hasPermission('tasks.manage'))) {
            return [];
        }

        return ['badge' => Task::query()->waitingForPerson()->whereNotIn('status', ['draft', 'cancelled', 'done'])->count()];
    }

    /**
     * @return list<Tool>
     */
    public function mcpTools(): array
    {
        return $this->tools->all();
    }

    /**
     * @return list<McpResource>
     */
    public function mcpResources(): array
    {
        return $this->resources->all();
    }
}
