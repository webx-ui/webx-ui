---
'@webx-ui/php': minor
'@webx-ui/module-auth': minor
---

Every call an agent makes is written down, and the panel shows who did what

An agent acts in an administrator's name, and until now nothing said afterwards what it had
done. Now every tool call lands in `mcp_calls`, the way every sign-in lands in
`cms_login_records`, and the administrators section shows the trail:

- **One row per call, whichever way it went.** `webx-ui/mcp` writes it in one place, around the
  whole of the call — so a refusal at the door for a scope, a read-only connection or a missing
  permission is a row with its reason, and so is what the handler threw. A handler that answers
  `ok: false` is written down as refused too. Each row carries who the agent acted as, on which
  connection, the tool, its arguments, whether it was a dry run, and how long it took. No secret
  reaches it: the token and the headers are never looked at, and an argument named like one is
  blanked. There is deliberately no link to what the call was about — tools are about
  different things.
- **Kept by days.** `webx-mcp.calls.days` (90) is the retention; `webx:mcp:prune-calls` runs
  nightly on the scheduler. `calls.enabled` switches the log off, `calls.arguments_length`
  cuts long arguments.
- **A view next to the administrators.** `@webx-ui/module-auth` draws **Agent calls** as a
  second view of the section, at `/admins/calls`, for whoever holds `admins.audit` — the
  permission the sign-in trail is behind. It narrows by administrator, by tool and by outcome,
  and the choices on offer are the ones that actually appear in the log. Arguments and the
  refusal's words open under a row. `GET /api/cms/auth/mcp-calls` answers it.
- The playground panel now has the administrators section, so the view can be looked at on
  `localhost:5174/panel/admins/calls`.
