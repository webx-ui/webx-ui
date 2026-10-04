# webx-ui/module-team

The team of the site: people with a photo, a name, a job title, a short text, their social links
and, when the site has services, the services each of them provides. A person has no page and no
address — the team reaches the site in a block (a grid, a slider, a list) or through `team()` in a
template; the page brings the address, the SEO and the menu entry. The section «Team» of the
panel and the MCP tools `team_*` edit it. The blocks are `webx-ui/module-blocks`, the photo
`webx-ui/module-media`, the links to services `webx-ui/module-services` — read their guides for
questions about those.

## What it owns

- **Table** `team_members` (`WebxUi\Team\Models\Member`). `name`, `job_title` and `text` are
  translatable; nobody is hidden over a language — a missing name falls back to the default
  language, a missing text is simply not printed. `position` is the one order: no categories.
- **No public route.** The collection source `team` and the offered block type `team`
  (`resources/blocks/team.json`) are how people reach a page.
- **Helper** `team()` — declared only when the site has no function of that name.
- **Relation target** `team-member`: another module's screen points at the team with a
  `wx-relations` field `"target": "team-member"`.
- **Panel screen** `team.form` (nodes `person`, `photo`, `name`, `job-title`, `about`, `text`,
  `links`, `socials`, `social-row`, `social-network`, `social-url`, `services`, `settings`,
  `published`, `project-fields`); `services` is there only with `webx-ui/module-services`.
- **API** under `/api/cms/team`; permissions `team.view`, `team.manage`.
- **MCP** tools `team_list`, `team_get`, `team_create`, `team_update`, `team_delete`,
  `team_reorder`; resource `team://catalog`. Scopes `team:read`, `team:write`.
- Also registered: one top-level entry of the panel's menu, demo content (`resources/demo`).

## Change it without forking

| You want                               | Do this                                                                                    |
| -------------------------------------- | ------------------------------------------------------------------------------------------ |
| The Team block on the site             | `php artisan webx:blocks:offered --install --module=team` (a type already there is kept)   |
| Different markup of the block          | edit the site's copy of the block type `team` in the panel — not the JSON in the package   |
| Another social network, or one fewer   | `networks` in `config/webx-team.php` (`vendor:publish --tag=webx-team-config`), key → name |
| People in a template of the site       | `team()->relatedTo('service', $service)->take(3)`; `only()`, `except()`, `locale()`        |
| Experience, education, ... of a person | a patch: `Screens::extend('team.form', [...])` adding into `project-fields`; read `extra`  |
| Other words in the panel               | `php artisan vendor:publish --tag=webx-team-lang`                                          |

A network taken out of `webx-team.networks` drops out of every card but stays in the database; put
it back and the links return. The options of the `social-network` select come from that config,
not from the screen JSON. A project field reaches the card under `fields`.

## Do not

- Do not edit anything in `vendor/webx-ui/module-team`, and do not copy the package into the
  site. Every row above is the supported way; if none fits, the package is missing a seam — say
  so instead of working around it.
- Do not add options to the `social-network` select with a screen patch: the server checks a link
  against `webx-team.networks`, so the save is refused. Add the network to the config.
- Do not add a migration for a project field: the patch and the `extra` column are the place; a
  column of your own is never read by the form, the card or MCP.
- Do not add a route or a page for one person: there is no person page by design (and so no
  `Person` markup). Put a team block on a page of `webx-ui/module-pages` instead.
- Do not expect `relatedTo()` with an empty list to mean everybody: it is nobody. Leave the call
  out to get the whole team.
- Do not delete rows with SQL: deleting a person sends them to the bin, and
  `POST team/{id}/restore` brings them back. A raw delete skips the bin for good.

## Check your work

- `php artisan webx:doctor` — among other things, whose `team()` the site calls.
- Open the page with the block on the site; an unpublished person is not on it.
- With MCP: read `team://catalog` first (the networks the site accepts, everybody in order), then
  `team_get`; every tool that changes something takes `dry_run: true` first.

## Read more

- [README.md](README.md) in this directory — the card, `team()`, the block, the panel API.
- Guide: https://webx-ui.github.io/webx-ui/guide/team
- Specification: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_TEAM.md
- Extending views and services: https://webx-ui.github.io/webx-ui/guide/extending
