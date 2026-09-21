---
'@webx-ui/php': minor
'@webx-ui/module-auth': minor
---

An address is all a person needs to connect their own agent, and a list is all they need to end it

The dance, the consent screen, the permissions and the log were done; what was missing was the
part a person actually looks at. Two screens and a guide.

- **Connect an agent** — a new section in the system group, behind no permission at all:
  whoever got into the panel may connect an agent, and the agent cannot do anything they
  cannot. It has the address of this panel for agents, large, with a button that copies it;
  three steps for Claude and ChatGPT; a line for a terminal for Claude Code and two lines of
  TOML for Codex; and one-click install links for Cursor and VS Code. The address carries no
  secret — that is the whole point of the OAuth path — so it can be printed, read aloud, or
  left on a page. The server prints it absolute, because it is pasted into a program on
  another machine, and the name the server takes in the client's own list comes from its host,
  so somebody with three sites connected can tell them apart. The section is registered only
  where there is a door to connect to: Passport installed and `webx-mcp.path` not `false`.
- **Connections** — the agents that have been let in, with what each may do, when it was
  connected and when it was last heard from. Everybody's, as a third view of the
  administrators section, for whoever holds `admins.manage`; their own, at the foot of the
  connect page, for anybody signed in. **Disconnect** revokes the refresh token as well as the
  access token — without the second, a connection that the panel says has ended goes on
  refreshing itself for the month it was given. The row is kept, greyed: the call log points
  at it, and a line saying the connection ended on the 21st is worth more than a gap.
- `GET /api/cms/auth/connections` (`?all=1` for everybody's) and
  `DELETE /api/cms/auth/connections/{id}` answer both, and `WebxUi\Mcp\Grants\Grants::revoke()`
  is where a connection ends.
- A guide, `apps/docs/guide/agents.md`: how to connect, what an agent may do and why that is
  exactly what you may do, why not to connect a super administrator, and the two things —
  nightly dumps and the list of return addresses — to have in place before switching it on.
- The playground panel has both screens, on `localhost:5174/panel/connect` and
  `localhost:5174/panel/admins/connections`.
