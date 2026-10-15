# webx-ui/module-tasks

A queue of work between people and AI agents for the [WebX UI](https://github.com/webx-ui/webx-ui)
admin panel.

A person — alone or with an assistant — writes a task: the specification, the acceptance
criteria, and the stages it is done in. Agents take the stages one at a time, each in a fresh
session, and report back. A reported stage is reviewed automatically by a dispatching agent, or
by a person when the stage says so. The task's journal keeps who did what, when, and with what
result.

It is a table of tasks with statuses and stages, not a general tracker: no boards, no sprints,
no estimates, no attachments.

## Requirements

- PHP 8.4+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-auth`, `webx-ui/mcp`

## Install

```bash
composer require webx-ui/module-tasks
php artisan migrate
```

Permissions: `tasks.view`, `tasks.manage` (tasks and projects), `tasks.work` (take, report and
ask about stages), `tasks.review` (accept or send back a reported stage).

## The life of a task

```php
use WebxUi\Tasks\Workflow\Actor;
use WebxUi\Tasks\Workflow\Dispatcher;
use WebxUi\Tasks\Workflow\Tasks;

$tasks = app(Tasks::class);
$me = Actor::admin($request->user());

$project = $tasks->createProject(['slug' => 'shop', 'title' => 'Shop', 'workdir' => '/work/shop']);

$task = $tasks->create($project, ['title' => 'Cart page', 'spec' => '…', 'acceptance' => '…'], [
    ['title' => 'Schema', 'brief' => '…'],
    ['title' => 'Screens', 'brief' => '…', 'review_mode' => 'human'],
], $me);

$tasks->ready($task, $me);                    // the first stage can be taken

$agent = Actor::agent('worker-7');
$package = app(Dispatcher::class)->claimNext($agent);   // everything the session needs, or null
```

The agent then reports (`report`), or stops to ask a person (`block`, answered with `unblock`).
A reviewer accepts (`accept`) or sends the stage back with remarks (`rework`). When the last
stage is accepted the task is done.

Stage statuses: `pending → ready → in_progress → review → done`, with `rework`, `blocked` and
`cancelled` on the side. A task is `draft` or `cancelled` when set so; otherwise its status is
what its stages add up to.

## Agents

The queue is worked over MCP (`webx-ui/mcp`): `tasks_claim_next` hands an agent its stage with
everything a fresh session needs, `tasks_stage_report` / `_block` / `_accept` / `_rework` move it,
`tasks_queue` is the dispatcher's view, and `tasks://rules` tells an agent how to behave.

A person connects their assistant over OAuth and acts as themselves. A program gets a key with
scopes and names itself with the `agent` argument on every call:

```bash
php artisan webx:mcp:token --name=worker --scopes=tasks:read,tasks:work
php artisan webx:mcp:token --name=dispatcher --scopes=tasks:read,tasks:work,tasks:review
```

A stage marked `human` is accepted only over a person's connection, never with a key — whatever its
scopes. A key is issued in the panel under _Administrators → Keys_ or by `webx:mcp:token`.

## Notifications

A person learns what waits for them from Telegram: a stage to review, an agent's question, a task
done, a stage hung in progress — each event switched on or off in «Tasks → Settings», with the bot's
token and the chats. The notice is queued after the move, so a move never waits for Telegram;
run a queue worker and the scheduler (`webx:tasks:stale` every 15 minutes). Another channel is a
binding of `WebxUi\Tasks\Notifications\Notifier`.

## Licence

MIT.
