---
'@webx-ui/php': minor
'@webx-ui/module-tasks': minor
---

module-tasks: the watcher that starts agent sessions gets an account and a screen of its own. `php artisan webx:tasks:runner` makes the role `runner`, the administrator `taskd` and a key with the scope `tasks:run` — it takes and gives back stages but never reports or decides on one. New tools: `tasks_runner_config` (limits, the pause and the runners `claude` and `agy`, set on the settings screen under «Watcher»; `agy` is off by default), `tasks_runner_heartbeat` (the windows, the pause and why it waits, kept in `tasks_runner_beats`), `tasks_runner_status`, and `tasks_session_key` — a key for one stage, `worker` or `reviewer`, that reaches only its own stage under the agent name it was issued for and is revoked in the same transaction as the stage leaves `in_progress` or `review`. A paused queue hands out nothing and `tasks_claim_next` says why. The page **Tasks → Agents** shows the last heartbeat of each watcher; five minutes without one is «silent», and `webx:tasks:runner-watch` (every minute) tells a person once through the queue. New permission `tasks.run`, migration `tasks_runner_beats` / `tasks_session_keys`.
