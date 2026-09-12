---
'@webx-ui/php': minor
---

`webx-ui/module-auth`: administrators, roles and sign-in. Installing it is what closes the
panel — until now `webx-ui/admin` served its API to anyone. Administrators live in their own
table behind their own guard, roles grant the permissions modules declare in the manifest, and
every sign-in attempt is written down. Its MCP tools can read who has what and move people
between roles, and deliberately cannot touch a password or mint a token.
