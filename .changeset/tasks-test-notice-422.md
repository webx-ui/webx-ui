---
'@webx-ui/php': patch
---

`webx-ui/module-tasks`: the panel's refusals are 422 instead of 409 — a move the rules of §3 do not allow (with `message` and `reason`, as before) and «Send a test message» when nothing is set up or Telegram does not take the message. The panel reads every 409 as an edit somebody made meanwhile and shows «Somebody changed this while you were working on it» in place of the answer, so a wrong bot token looked like a conflict on the settings screen, and a refused move on a task never said why.
