# webx-ui/module-tasks

A queue of work between people and AI agents: projects, tasks with a specification and acceptance
criteria, and ordered stages that agents take one at a time, report on, and have reviewed — by
another agent or, where a stage says so, only by a person. Everything that happens is written to
an append-only journal. The panel shell, permissions and the module registry are
`webx-ui/module-admin`, the administrators `webx-ui/module-auth` — read their guides for those.

## What it owns

- **Tables** `tasks_projects` (`WebxUi\Tasks\Models\Project`), `tasks_tasks` (`Task`, soft
  deletes, `number` counted inside the project), `tasks_stages` (`Stage`), `tasks_journal`
  (`JournalEntry`, append-only: an update or a delete throws).
- **Statuses** — enums in `WebxUi\Tasks\Workflow`: `StageStatus` (`pending`, `ready`,
  `in_progress`, `review`, `rework`, `blocked`, `done`, `cancelled`; `canBecome()` is the table of
  allowed moves) and `TaskStatus` (`draft` and `cancelled` are set; the rest is
  `TaskStatus::of()` over the stages).
- **Operations** — `WebxUi\Tasks\Workflow\Tasks`: `createProject`, `updateProject`, `create`
  (a draft with its stages), `update`, `ready`, `report`, `accept`, `rework`, `release`, `block`,
  `unblock`, `comment`, `cancel`, `cancelStage`. Each is one transaction that writes the journal
  and re-derives the task's status.
- **Handing out work** — `WebxUi\Tasks\Workflow\Dispatcher::claimNext($agent, $project)`: rework
  before ready, then priority, then the task's age; a conditional update decides a race. It answers
  the context package (`ContextPackage`): project, task, stage, last rework remarks, questions and
  answers, reports of accepted stages — or null when there is nothing.
- **Who** — `WebxUi\Tasks\Workflow\Actor`: `admin()` (a person), `agent($session)`, `system()`.
- **MCP** tools `tasks_projects_list`, `tasks_projects_create`, `tasks_projects_update`,
  `tasks_list`, `tasks_get`, `tasks_queue`, `tasks_create`, `tasks_update`, `tasks_ready`,
  `tasks_claim_next`, `tasks_stage_report`, `tasks_stage_accept`, `tasks_stage_rework`,
  `tasks_stage_release`, `tasks_stage_block`, `tasks_stage_unblock`, `tasks_comment`, `tasks_cancel`
  (`WebxUi\Tasks\Mcp\TasksTools`). Scopes `tasks:read`, `tasks:write`, `tasks:work`, `tasks:review`.
  Resources `tasks://rules` (`resources/mcp/rules.md`) and `tasks://projects/{slug}`. The `agent`
  argument names the acting session; without it the caller must be a person (panel session or
  OAuth token) — a key with named scopes never is.
- **Panel** — API under `<api_path>/tasks` (`projects`, `tasks` with `view=all|waiting|active|draft|closed`,
  `tasks/{id}/ready|cancel|comments`, `stages/{id}/accept|rework|unblock|release|cancel`); a refusal
  of the rules is 422 with `message` and `reason`. The form is the described screen `tasks.form`
  (`resources/screens/form.json`). The manifest carries `meta.badge` — tasks waiting for a person.
  The screens are `@webx-ui/module-tasks`.
- **Notifications** (`WebxUi\Tasks\Notifications`) — contract `Notifier`, `TelegramNotifier` bound by
  default; events `StageMoved` and `TaskStatusChanged` (`WebxUi\Tasks\Events`) queue `SendNotice`
  after commit (3 attempts, one on a `sync` queue; the last failure goes to the journal as the
  system's comment). Settings screen `tasks.settings`, keys `tasks.notify.*` and
  `tasks.telegram.chats`; the bot token is encrypted under `tasks.telegram-token` and never read
  back. Command `webx:tasks:stale` (scheduled every 15 minutes). Config `config/webx-tasks.php`.
- **Section** `tasks`.
- **Refusals** — `WebxUi\Tasks\Exceptions\TaskRefused` with a `reason` key; bad values are a
  `ValidationException`.

### Permissions

| Permission     | Opens                                                                                                                                                                                                                                                                                                                                         |
| -------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `tasks.view`   | projects and tasks, read-only (`GET projects`, `tasks`, `tasks/{id}`); `tasks_projects_list`, `tasks_list`, `tasks_get`, `tasks_queue`                                                                                                                                                                                                        |
| `tasks.manage` | the reads, plus projects, tasks and their stages: create, edit, `ready`, cancel, unblock and cancel a stage, release, comment, the settings screen and its test send; `tasks_projects_create`, `tasks_projects_update`, `tasks_create`, `tasks_update`, `tasks_ready`, `tasks_stage_unblock`, `tasks_cancel`, `tasks_comment`; the menu badge |
| `tasks.work`   | the reads, plus an agent's side: `tasks_claim_next`, `tasks_stage_report`, `tasks_stage_release`, `tasks_stage_block`, `tasks_comment`; release and comment in the panel                                                                                                                                                                      |
| `tasks.review` | the reads, plus accept or send back a stage (`stages/{id}/accept`, `rework`), comment; `tasks_stage_accept`, `tasks_stage_rework`, `tasks_comment`; the menu badge                                                                                                                                                                            |

In no ready-made role. A route or screen node the site adds next to the module asks the same:
`cms.can:tasks.view,tasks.manage,tasks.work,tasks.review` to read, `cms.can:tasks.manage` to write.

## Change it without forking

| You want                     | Do this                                                                                                      |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------ |
| Move or relabel a form field | a patch: `Screens::extend('tasks.form', [...])` — a field you add is not stored                              |
| Other words in the panel     | `php artisan vendor:publish --tag=webx-tasks-lang`                                                           |
| Rules agents get every time  | the project's `rules` field — it goes into every context package                                             |
| An agent kept to a project   | `claimNext($agent, $project)` with the project                                                               |
| A key for a worker agent     | `php artisan webx:mcp:token --name=worker --scopes=tasks:read,tasks:work` (+`tasks:review` for a dispatcher) |
| A reaction to a change       | read `tasks_journal`; every move is a line with `meta.from`/`meta.to`                                        |

## Do not

- Do not write `status` on a task or a stage directly, or save a `Stage` outside `Tasks`: the
  journal would miss it and the task's status would stop matching its stages.
- Do not hand out a stage any other way than `Dispatcher::claimNext()` — the conditional update is
  what keeps two agents off the same stage.
- Do not build an `Actor::admin()` for an agent to get past a rule: a stage marked `human` and an
  answer to a blocked stage are a person's decision.
- Do not edit or delete journal lines; write a comment instead.
- Do not put the bot token into `.env`, a setting a screen lists, or a log line: it is stored
  encrypted by `NotificationSettings::setToken()` and read nowhere else.
- Do not put names of real projects, people or clients into this package; they are data on a site.

## Check your work

- With MCP: `tasks_create` with `dry_run: true` shows the task it would write; `tasks_queue` shows
  what waits for whom.
- `vendor/bin/phpunit` in the package: the tables of §3, the race for a stage, the queue order,
  the editing rules and the journal are all tested.
- A stage moved by your code leaves a `tasks_journal` line with `meta.from`, `meta.to` and its
  author; the task's `status` equals `TaskStatus::of()` of its stages.

## Read more

- [README.md](README.md) in this directory — install and the life of a task.
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_TASKS.md
