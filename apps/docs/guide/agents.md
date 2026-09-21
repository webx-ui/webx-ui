# AI agents

A person can connect their own AI agent — Claude, ChatGPT, Codex, Cursor, VS Code — to a WebX
UI panel and have it do their work there: read the pages, draft an article, fix the SEO of a
section, go through the inbox. The agent acts **as them**, with their permissions and under
their name, and everything it does is written down.

Two packages make it up: `webx-ui/mcp` has the server and the tools, and `webx-ui/module-auth`
has the door — the consent screen, the connections list, and the page that tells somebody how
to connect.

## How to connect

Open **System → Connect an agent** in the panel. The page has the address of this site for
agents, large, with a button that copies it:

```
https://your-site.example/api/cms/mcp
```

That address is not a secret. It carries no key and no token, it is the same for everybody,
and it can be printed in a letter, read out over a call, or pasted into a chat. This is the
reason the OAuth path exists at all: giving somebody access never means handing them anything
they could pass on.

What happens after you give it to a client:

1. The client opens the site in a browser and asks you to sign in to the panel — your usual
   address and password, on our own sign-in screen. The client never sees the password.
2. The panel shows who is asking, **where the answer goes**, and what the agent will be able
   to do. There is a **read only** switch on that screen.
3. You press Allow, and the client has a token of its own. It refreshes itself for a month;
   after that, connecting again is the same three clicks.

::: tip Start with read only
The switch on the consent screen is one bit and it closes the whole question of a mistaken
write. An agent kept to reading can still answer "which pages have no description", "what came
through the form last week", "where is this block used" — which is most of what an agent is
worth on the first day.
:::

### Per client

- **Claude and ChatGPT** — Settings → Connectors → add a custom connector, paste the address.
- **Claude Code** — one line in a terminal:
  ```
  claude mcp add --transport http your-site https://your-site.example/api/cms/mcp
  ```
- **Codex** — two lines in `~/.codex/config.toml`:
  ```toml
  [mcp_servers.your-site]
  url = "https://your-site.example/api/cms/mcp"
  ```
- **Cursor and VS Code** — the connect page has a button each. They take the whole server from
  a link: the editor opens, asks once, and it is there.

The page in the panel says all of this in the language the panel is in, which is the point of
it: somebody who has never heard of MCP should not have to read this guide.

::: warning It has to be reachable from outside
A connector at `claude.ai` or `chatgpt.com` opens the address from Anthropic's or OpenAI's
servers, not from your machine. `https://webx-cms.local` cannot be connected that way, and
neither can a site behind a VPN. Clients that run on your own computer — Claude Code, Codex,
Cursor — reach a local address fine.

The address must also be **https**. Some clients refuse plain http outright, and the ones that
do not should.
:::

## What an agent may do

**Its permissions are yours.** There is no separate account, no role for robots, no second
list of rights to keep in step with the first. The token belongs to a person, and every call
is checked twice:

- against the **permission** the tool declares — an agent connected by somebody who may not
  touch the settings is not shown the settings tools at all, and is refused if it asks;
- against the **terms of the connection** — "read only" refuses every tool that changes
  anything, whatever the person's own rights are.

The second is stronger than the first on purpose. Somebody who may edit pages and connected an
agent to look gets an agent that looks.

Everything else follows from the agent being a person: an article it writes is signed by them,
switching their account off ends the agent's access in the same instant, and changing their
password does too.

### Do not connect a super administrator

`is_super` answers yes to every permission there is, including ones added by a module
installed next month. An agent on such an account is not limited by anything except the tools
that exist.

Make an account with a **role** for this, even if it is your own account you are thinking of.
A role is a list you can read; `is_super` is a list nobody can.

This is the one piece of advice in this guide that is worth following before the first
connection rather than after the first accident.

## Seeing what happened

**System → Administrators → Agent calls** is every tool call any agent made: who it acted as,
which client, which tool, with what arguments, how it went and how long it took. Refusals are
in there too — a read-only connection asked to write, a tool somebody had no right to — and so
are dry runs. Kept for ninety days by default, pruned nightly.

No secret ever reaches it. Arguments are stored with anything named like a password, token,
key, cookie or authorization blanked, and cut to a length; the token the call arrived with is
never written at all.

**System → Administrators → Connections** is the list of live connections — everybody's, for
whoever holds `admins.manage`. Your own are at the bottom of the connect page, where anybody
signed in reaches them without a permission of any kind.

**Disconnect** ends one at once. It revokes the access token and the refresh token behind it,
which is the half that matters: without the second, a "revoked" connection would go on
refreshing itself for a month.

A disconnected connection stays in the list, greyed. The call log points at it, and a line
saying the connection ended on the 21st is worth more than a gap where it was.

## Before you switch this on

Two things, both from the security section of the specification, both cheap and both a
nuisance to wish for later.

**Nightly dumps of the database.** An agent with the right to write can empty a section faster
than anybody notices, and the warning on the consent screen answers "who is responsible", not
"how do we get it back". See [Database backups](./backups.md) — the first site that switches
MCP on switches that on too.

**The list of return addresses.** Registration is open by necessity: a client has to introduce
itself before anybody has signed in. What keeps that from being a way in is
`webx-mcp.oauth.redirect_domains` — where a client may be sent back with the code. The package
ships a list, not a wildcard:

```php
'redirect_domains' => [
    'https://claude.ai', 'https://claude.com',
    'https://chatgpt.com', 'https://chat.openai.com',
    'http://localhost',
],
'custom_schemes'   => ['claude', 'cursor', 'vscode'],
```

Each vendor is there twice because each answers at two domains and only one of them is the one
you meet: Claude connects from `claude.ai` today and Anthropic is moving to `claude.com`, and
ChatGPT's own address is `chatgpt.com` while `chat.openai.com` is still the callback of
anything set up before the rename.

These are the **clients'** addresses, not yours; a list containing only your own domain lets
nobody in at all. A client that is not on the list cannot connect until somebody adds it,
which is the price and the point. Without this, anybody may register a client called "Site
panel" that returns the code to their own server and send an administrator a link to our own
consent screen — and PKCE does not help, because it protects the code, not the person reading
the screen. The consent screen always shows the return address for the same reason: the name
is the client's own choice.

## Installing

`webx-ui/module-auth` brings Passport with it. Two deployment steps, neither optional:

```
php artisan vendor:publish --tag=passport-migrations && php artisan migrate
php artisan passport:keys
```

Without the keys the guard cannot be built, and a call with no token answers 500 where it
should answer 401. The connect page does not appear in the menu on a panel with no door to
connect to — `webx-mcp.path` set to `false`, or Passport missing — so an installation that has
not done this simply has no such section.

## What is deliberately not here

- **A chat with the agent inside the panel.** Whoever wants to work through an agent pays for
  their own tokens; whoever does not opens the panel and edits by hand.
- **A queue of an agent's changes for approval.** Tempting, and the size of a module. The role
  stands in for it: do not give write access to somebody whose edits you want to read first.
- **A grid of scopes on the consent screen.** One switch, because a matrix means explaining
  the words `pages:write` to somebody who came here to connect a chatbot.
- **Keys for machines.** They will come with the first headless integration — CI, our own
  scripts — as a table of their own. For a person, OAuth is both easier and safer.
