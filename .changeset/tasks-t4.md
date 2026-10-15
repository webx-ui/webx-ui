---
'@webx-ui/php': minor
'@webx-ui/module-tasks': minor
---

`webx-ui/module-tasks`, T4 — notifications: a person hears from Telegram that a stage waits for their review, that an agent asked a question, that a task is done or a stage hung in progress (and, when switched on, that a stage was sent back or waits for an agent's review). Queued after the move commits, three attempts, the last failure written to the task's journal; the bot token is stored encrypted and never read back. «Tasks → Settings» has the token, the chats, the events and a test message; another channel is a binding of the `Notifier` contract.
