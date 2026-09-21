---
'@webx-ui/module-admin': patch
'@webx-ui/php': minor
---

A person connects their own agent with an address and three clicks

The MCP server used to open only for a token printed from the console, which is fine for whoever
can already run artisan on the server and no use at all for a designer or a client. Now the
address alone is enough — `https://example.com/api/cms/mcp`, nothing secret in it — and the
client finds its own way from there: it reads the 401, discovers the authorization server,
registers itself, sends the person to the panel to sign in and agree, and leaves with a token of
theirs. The agent acts as that administrator, so authorship, roles and `is_active` already mean
what they should.

- **Passport replaces Sanctum.** Two `HasApiTokens` traits cannot share a model, and Passport is
  the one that can register a client it has never met. `webx-ui/module-auth` carries it, because
  `CmsUser` is what an agent acts as and Passport's user provider accepts only a model that
  implements its `OAuthenticatable`. A site switches it on once, with
  `vendor:publish --tag=passport-migrations`, `migrate` and `passport:keys`; without the keys the
  guard cannot be built and a call with no token answers 500 instead of 401.
- **The `api` guard** — Passport's driver over the panel's own people — is registered for you
  unless the application has defined one under that name, and `webx.mcp-auth` asks it.
- **Two doors that ship open are closed.** `config('mcp.redirect_domains')` is `['*']` by default,
  which lets anybody register a client called "Site panel" that takes the code to their own
  server; the list is now Claude, ChatGPT and localhost, and the consent page always shows the
  address a person is about to be sent back to, not only the name the client chose for itself.
  Client registration is rate limited, because nobody has signed in when it happens.
- **A token granted this way carries one scope for the whole server**, `mcp:use`, because that is
  the only one a client is ever offered. Read module scope by module scope it would be refused
  everything, so it passes the scope gate whole; what limits it is the administrator's own
  permissions. A key that names module scopes is still read scope by scope.
- **The panel fetches its CSRF cookie from its own route**, `{api_path}/auth/csrf-cookie`, rather
  than Sanctum's — which left with the package. `createHttp` defaults to it.
- `webx:mcp:token` is gone with Sanctum. Keys for machines, which have no browser to send anybody
  to, come back later as their own thing.
