# Tasks for agents

`@webx-ui/module-tasks` is a queue of work between people and AI agents. A person — alone or with
an assistant — writes a task: the specification, the acceptance criteria and the stages it is
done in. Agents take the stages one at a time, each in a fresh session, and report back. A
reported stage is reviewed by a dispatching agent, or by a person when the stage says so. The
task's journal keeps who did what, when and with what result; Telegram tells a person when
something waits for them.

It is a table of tasks with statuses and stages, not a general tracker: no boards, no sprints,
no estimates, no attachments. Its other half, `webx-ui/module-tasks` on the server, holds the
rules, the MCP tools agents work through and the notifications.

The specification is `docs/architecture/WEBX_UI_MODULE_TASKS.md` in the repository.

## The section

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { tasks } from '@webx-ui/module-tasks'
import '@webx-ui/module-tasks/style.css'

createAdmin({
  modules: [tasks()],
})
```

Permissions: `tasks.view` to look, `tasks.manage` to write tasks and projects, answer an agent's
question and change the settings, `tasks.work` to do stages, `tasks.review` to accept a reported
stage or send it back. Beside the menu entry is the number of tasks that wait for a person — a
stage to review, or a question — shown to whoever can act on them.

- **Tasks** — the list, highest priority first, with its views: all, waiting for me, in work,
  drafts, closed; a project filter and a search.
- **A task** — the specification and criteria, the stages with their assignments, reports and
  results, and the buttons the rules allow at that moment: put to work, accept, send back, answer,
  release, cancel. Under them the journal and a comment box.
- **The form** — the described screen `tasks.form`. A draft changes freely. Once the task is in
  work only its priority changes, and of the stages only the ones nobody has taken; new stages go
  at the end.
- **Projects** — where agents work: the folder, the repository, the default branch and the rules
  they are handed with every stage.
- **Settings** — the Telegram bot, the chats and which events to hear about.

## A task's life

```
pending ──► ready ──► in_progress ──► review ──► done
                          │   ▲          │
                          ▼   │          ▼
                       blocked┘       rework ──► in_progress
```

Stages go in order: the next one is handed out when the one before it is accepted. A stage sent
back is taken before any ready one, and its attempt counts up. An agent that cannot go on without
a person asks a question (`blocked`) and ends its session; the answer goes to whoever takes the
stage next. A task is `draft` or `cancelled` when set so; otherwise its status is what its stages
add up to.

## Agents

Agents work the queue over MCP. A person connects their assistant over OAuth and acts as
themselves; a program gets a [key](./agents.md#a-key-for-a-program) — in the panel under
_Administrators → Keys_, or on the server — and names its session with the `agent` argument:

```bash
php artisan webx:mcp:token --name=worker --scopes=tasks:read,tasks:work
php artisan webx:mcp:token --name=dispatcher --scopes=tasks:read,tasks:work,tasks:review
```

- `tasks_claim_next` hands out the next stage — safe to call from several sessions at once — with
  everything a fresh session needs: the task, the project's folder, branch and rules, the stage,
  the remarks it was sent back with, earlier questions and answers, and what the stages before it
  reported.
- `tasks_stage_report`, `tasks_stage_block` — the worker's moves; `tasks_stage_accept`,
  `tasks_stage_rework` — the dispatcher's; `tasks_queue` — what waits for whom, cheap to call
  every minute.
- `tasks://rules` is the process an agent follows: how to take a stage, what to do before
  reporting, the report's sections, what is never done.

A stage marked for a person is accepted only over a person's connection. A key issued for a
program is never taken for a person, whatever its scopes.

## Notifications

In **Tasks → Settings**: the bot's token (from `@BotFather`, stored encrypted and never shown
again), the chats, and the events — a stage waiting for your review, an agent's question, a task
done, a stage hung in progress for longer than the limit, and, when wanted, a stage sent back or
waiting for an agent's review. «Send a test message» checks the token and the chats.

A notice is queued after the move, so a move never waits for Telegram: run a queue worker and the
scheduler (`webx:tasks:stale` every 15 minutes). Three attempts; the last failure is written to
the task's journal. Another channel is a binding of `WebxUi\Tasks\Notifications\Notifier`.

## A site of its own

A task queue usually lives on a site that has nothing else — the panel and no public part:

```bash
composer create-project webx-ui/site tasks --no-scripts
cd tasks && cp .env.example .env && php artisan key:generate
WEBX_ADMIN_PASSWORD=… php artisan webx:setup --no-interaction \
  --name="Tasks" --domain=tasks.example.com --modules=admins,settings,tasks --locales=en \
  --db-connection=mariadb --admin=you@example.com --no-demo
php artisan webx:doctor --strict
```

`webx:setup` sees that none of these modules shows a visitor anything and makes the site private:
`/` leads to the panel, no theme is installed, and `WEBX_PUBLIC=false` keeps it out of search
engines — `robots.txt` disallows everything and every response says `noindex`.
