---
'@webx-ui/module-auth': minor
---

Administrators → Keys: issue a key for a program — a queue watcher, a nightly import — that acts as the administrator you choose, with everything they may do or only the scopes you pick, for 30 days up to ten years. The key is shown once with a copy button and the server address; the list shows whom each key acts as, what it may reach, when it runs out and when it was last used, and Revoke stops it at the next call. Behind `admins.manage`. New exports: `WxKeyList`, `createKeysApi` and the `McpKey*` types.
