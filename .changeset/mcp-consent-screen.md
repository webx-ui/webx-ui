---
'@webx-ui/module-auth': minor
'@webx-ui/php': minor
---

The consent screen is the panel's own, and "read only" is a box on it

When an agent asks to be let in, the person now sees a page of the panel rather than the plain
one: the site's logo, who is asking and where the answer will be sent, and what the agent will be
able to do — in the words of the panel's modules ("Pages — view and edit", "Files — view"), not in
scopes. Under it, the warning that the agent acts in their name and that they are responsible for
what it does. In the panel's language, all ten.

- **Read only.** One box instead of a matrix of scopes: tick it and the agent may look and may
  not change anything, whatever the person's own permissions say. A read-only connection is not
  shown the tools that write, and is refused if it calls one it remembers from before.
- **The consent is written down.** No "I understand" box — the fact of pressing Allow goes into
  `mcp_grants` in `webx-ui/mcp`: who, which client, the address the code went to, whether they
  said read only, which version of the text they were shown, and when. The same row is updated
  when the same person lets the same client in again. `last_used_at` is kept to the minute, so
  a list of connections can say when each was last seen.
- **A guest is sent to the panel to sign in and brought back.** Passport sends a stranger to a
  route named `login`, which no site with this panel has; now they are sent to the panel's own
  sign-in screen with the consent page as `next`, and `@webx-ui/module-auth` follows a whole
  address on the same site as a page rather than as a route. "Sign in as somebody else" on the
  consent screen ends the session and goes the same way. The sign-in path is
  `webx-auth.login_path`, `login` under the panel's path.
- The consent screen posts to `{oauth prefix}/consent` rather than to Passport's approve route;
  `scripts/php-smoke.sh` walks the dance both ways, read-only and not, in a real application.
