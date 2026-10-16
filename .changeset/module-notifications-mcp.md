---
'@webx-ui/php': minor
---

module-notifications for agents: eleven MCP tools `notifications_*` — channels listed, read, created, changed, put in the bin and copied with their secrets carried over on the server, a template previewed on the event's example values, a test sent, Telegram chats found, the delivery log read and a failed row retried — behind `notifications:read` / `notifications:write` and the section's two permissions. Secrets are write-only: no answer, a dry run's or a refusal's, carries them. A template Mustache cannot read is refused with the words that say what to fix. Resources `notifications://providers`, `notifications://events` (variables, lists, addressees, template syntax) and `notifications://channels`. `webx:demo --module=notifications` seeds one e-mail channel, switched off, to a made-up address; the package has its `AGENTS.md`.
