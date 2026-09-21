---
'@webx-ui/php': minor
---

An agent can do what the administrator who connected it can do, and is shown exactly that

Until now the MCP door checked the token's scope and the terms of the connection, and never the
administrator's own permissions — and a token granted through consent carries one scope for the
whole server, so an editor's agent could do anything any module offered. Now every tool is behind
a panel permission, checked in the same place as the scope, before any handler runs:

- **A permission per tool, derived the way the scope is.** A tool that writes needs
  `<module>.manage`; one that reads needs `<module>.view` — or `<module>.manage`, because the
  panel's own routes let an editor at the list without a separate `view`. A module whose
  permissions are not named after it says so on the tool: `Tool::read(..., permission: …)`, one
  name or several that mean "any of these". `webx-ui/module-blog` (`blog.articles.*` and
  `blog.taxonomy.manage` for three module ids), `webx-ui/module-inbox` (`inbox.update` for
  moving a submission along, `inbox.manage` for the forms) and `webx-ui/module-media`
  (`media.upload` for `upload_from_url`) say so; the sign-in audit tools of
  `webx-ui/module-auth` are behind `admins.audit`.
- **`tools/list` is what the caller may use.** A narrower role sees a shorter list, and a tool
  that is not listed is not there to call by name either. On the stdio server there is nobody to
  ask, so everything is listed. A call that gets past the list — a client remembering a tool
  from before a role was taken away — is refused with the permission it lacks.
- `webx:mcp-tools` shows the permission next to the scope.
