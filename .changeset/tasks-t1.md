---
'@webx-ui/php': minor
---

New package `webx-ui/module-tasks`, T1 — the core of the queue of work between people and AI agents (§11 of its spec): projects, tasks with a specification and ordered stages, the stage state machine and the task status derived from the stages, an append-only journal, and the operations — create with stages, edit within the rules, ready, hand out the next stage atomically with everything a fresh session needs, report, accept, send back, release, ask and answer, comment, cancel. The section `tasks` with permissions `tasks.view`, `tasks.manage`, `tasks.work`, `tasks.review`. No MCP tools and no screens yet — those are T2 and T3.
