# @webx-ui/module-tasks

The «Tasks» section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: a queue of
work between people and AI agents. The list of tasks with its views («Waiting for me», «In work»,
drafts, closed), one task with its specification, its stages and their reports, the buttons a
reviewer and a planner press (accept, send back, answer a question, release, cancel) and the
journal; the form of a task — the described screen `tasks.form` — and the projects.

The server half is the Composer package `webx-ui/module-tasks`; agents work the queue over its MCP
tools.

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { tasks } from '@webx-ui/module-tasks'
import '@webx-ui/module-tasks/style.css'

createAdmin({ modules: [tasks()] })
```

Beside the menu entry the panel shows how many tasks wait for a person — a stage to review, or an
agent's question — when the signed-in administrator can act on them.

MIT.
