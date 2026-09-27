# Team

`@webx-ui/module-team` is the team as a section of the panel, and `webx-ui/module-team` on the
server is what it edits. This page is both, because neither is useful alone.

A person is a photo, a name, a job title, a few lines of text and their social links — and, when
the site has [services](/guide/services), the services they provide. A person has **no page of
their own**, and the module has **no public route**: the team reaches the site inside a block —
«our doctors» as a grid on the About page, «who does it» as a list on a service's page — or through
`team()` in a template of the site. The block is a [collection](/guide/collections), as the
[reviews](/guide/reviews) are.

## Install

```bash
pnpm add @webx-ui/module-team
composer require webx-ui/module-team
php artisan migrate
php artisan webx:blocks:offered --install --module=team
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { team } from '@webx-ui/module-team'
import '@webx-ui/module-team/style.css'

createAdmin({
  modules: [team()],
})
```

`team()` is one module and one entry of the menu, **Team**, at the top level: with no categories
there is nothing to group it with.

The last command installs the block type the module **offers** — **Team**, slug `team` — and
publishes it. A type the site already has under that slug is never touched: once installed, the
type is the site's to rewrite. `webx:setup` runs the command for a new site.
[Offered block types](/guide/collections#the-block-types-a-module-offers) explains the mechanism.

Permissions: `team.view` opens the list, `team.manage` writes — the order too.

## A person

| Field       | What it is                                                                   |
| ----------- | ---------------------------------------------------------------------------- |
| `photo`     | a picture from the library — kept as its key, never as an address            |
| `name`      | translatable; required in the default language — the one required field      |
| `job_title` | translatable                                                                 |
| `text`      | translatable plain text; the block prints it with its line breaks            |
| `socials`   | links `{ network, url }` in the editor's order; `http://` or `https://` only |
| `services`  | the services they provide — there only when `module-services` is installed   |
| `published` | the whole of a person's life: no draft, no history; the bin brings them back |

**Nobody is hidden over a language.** A published person is shown in every language of the site. A
name or a job title that is not written in the language is taken from the default one; a text that
is not written in it is simply not printed. Reviews go the other way — a review without its text is
nothing — but a doctor without a biography is still the doctor, and hiding them from the Russian
page over one untranslated paragraph would be losing them.

There is **one order**, the one the panel drags, and every block shows the team in it.

## Why there is no page and no categories

Both were weighed and left out on purpose; both can come later without breaking anything here.

- **No page of a person.** A page means an address, an SEO card, a place in the sitemap and
  `Person` markup — and a biography long enough to deserve all that. Most teams are a grid of faces
  with a line under each. The day a site needs a doctor's own page, it comes with those four
  things together.
- **No categories.** A team is small, and «the doctors of this service» is already a relation, not
  a category. The block's field hides the choice of categories by itself (the source says it has
  none). The price: «these three on the home page» is today the first three in the order, not three
  chosen by hand.

## The page of the team is a page with a block

The site's page of the team is an **ordinary page** of [`module-pages`](/guide/pages):

1. **Pages** → a new page «Team» with the slug `team`;
2. **Content** → add the block **Team**; leave **People** as it is (that is «everybody»), pick
   **Grid**;
3. publish.

The address, the SEO card, the menu entry and the sitemap line come from the page.

## «Who does it» on a service's page

A person is linked to services in their own form — the **Services** field. Then a service's page
answers «who does it» by itself:

1. open the service → **Content** → add the block **Team**;
2. in **People**, **Only related to** → **Services** → **The record of the page it stands on**;
3. pick **List**: the photo on the left, the text and the links beside it.

The block remembers «the service of this page», not a service, so the same block copied onto
another service shows that service's people. On a page that is not a service it shows nobody.
Choosing services by name instead is «the people of these services» anywhere.

Without `module-services` none of this exists: no field in the form, no choice in the block, no
links on the card. Links written while the module was there are kept, and come back with it.

## Two roads into a template

Both give **the same card**, so a block can move from one to the other without its markup changing.

**The `team` collection** — what the offered block uses. A `wx-collection` field with
`"source": "team"` lets the editor choose a limit and «only related to»; the template gets the
people already read:

```blade
@foreach ($team['items'] as $member)
    <article id="{{ $member['anchor'] }}">
        <h3>{{ $member['name'] }}</h3>
        <p>{{ $member['job_title'] }}</p>
    </article>
@endforeach
```

**`team()`** — for a template of the site, or a block that wants what the field does not do. It
never shows what a reader may not see: unpublished, or in the bin.

```blade
@foreach (team()->relatedTo('service', $service)->take(3) as $member) … @endforeach
```

| Step                  | What it does                                                             |
| --------------------- | ------------------------------------------------------------------------ |
| `relatedTo($t, $ids)` | Only the people related to these records: `'service'`, an id, a model    |
| `only([12, 7])`       | These and no others, in this order                                       |
| `except($member)`     | All but these                                                            |
| `take(6)`             | At most six; null or zero — all                                          |
| `locale('uk')`        | The language of the cards; by default the one being rendered             |
| `get()`, `first()`    | A list of cards, or one; the query itself can be looped over and counted |

`relatedTo('service', [])` is nobody — «the people of no service» is not the whole team. The helper
is a [`RecordQuery`](/guide/collections#a-helper-for-templates-recordquery), like `services()` and
`reviews()`, and is declared only if the site has no `team()` of its own; `php artisan webx:doctor`
says whose it is.

The card:

```php
[
    'id' => 12,
    'anchor' => 'member-12',          // for a link to #member-12
    'categories' => [],               // the collection contract asks for the key; there are none
    'name' => 'Anna Petrova',         // in the language of the page, else the default one
    'initials' => 'AP',               // what stands in for a missing photo
    'job_title' => 'Orthodontist',    // the same; '' when there is none
    'text' => "…\n…",                 // in the language of the page only, else ''
    'photo' => ['url' => …, 'thumb' => …, 'width' => …, 'height' => …, 'alt' => …], // or null
    'socials' => [                    // in the editor's order; a network not in the config drops
        ['network' => 'instagram', 'label' => 'Instagram', 'url' => 'https://…'],
    ],
    'service_links' => [['id' => 3, 'title' => 'Braces', 'url' => '/braces']], // [] without services
    'fields' => ['experience' => '12'], // the project's own fields, by name
]
```

Print the initials from the card rather than cutting the name in the template: a byte cut of a
Cyrillic name is half a letter. A list of any length costs the same few queries.

## The Team block

The offered type is a heading, the `team` collection, a **Layout**, the settings that layout reads,
and **Hide the text**:

| Layout     | What it draws                                                                   | Settings              |
| ---------- | ------------------------------------------------------------------------------- | --------------------- |
| **Grid**   | Cards, the photo on top; up to `columns` across, fewer when narrow; the default | `columns`             |
| **Slider** | The same cards in a ribbon that snaps, with arrows and — if asked — autoplay    | `columns`, `autoplay` |
| **List**   | One under another, the photo on the left, everything else beside it             | —                     |

A setting the editor never touched is `null` in the template, so the template keeps the defaults:
grid, four columns, the text shown.

A card is an `<article id="member-12">`: the photo or the initials in a circle, the name, the job
title, the text, the links to the person's services and the social links as icons.
**Without JavaScript everything reads** — the slider is a ribbon that scrolls sideways; the script
adds the arrows and autoplay, and `prefers-reduced-motion` turns autoplay off. The styles are
neutral, on `currentColor` and `em`: it is the site's design, not the panel's.

## Adding a social network

The networks a link may point at are the config `webx-team.networks`, key → name. Seven come with
the package — Facebook, Instagram, LinkedIn, X, Telegram, YouTube, TikTok. A site adds its own in
its config:

```php
// config/webx-team.php — php artisan vendor:publish --tag=webx-team-config
return [
    'networks' => [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X',
        'telegram' => 'Telegram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'vk' => 'VK',
    ],
];
```

Publish the whole list: the config is merged one level deep, so a published file with only `vk` in
it would be the only network the site has. The select of the form takes its options from here and
the server checks a link against the same list — a network the site adds is one line. Names are
brands and are not translated.

That puts **VK** in the form and on the card. The block draws an icon only for the seven it knows;
any other network is printed as its name. The icon is the site's to add, in **its** copy of the
type — **Blocks** → **Team** → **Template**, next to the others:

```blade
@elseif ($link['network'] === 'vk')
    <svg class="b-team__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="…"/>
    </svg>
@else
```

The icons in the offered type are [Simple Icons](https://simpleicons.org) (CC0); the same set has
most networks a site will want.

A network taken **out** of the list drops out of every card and stays in the database: put it back,
and the links come back. A person who has such a link can still be saved — the form sends it back
as it opened it — but a new link to that network is refused.

## Fields of the project

Experience, education, certificates, the days a doctor sees patients — none is a column of the
package. The site lays a patch over the screen `team.form`, whose empty card has the public id
`project-fields`, and whatever the screen draws that the model has no column for is kept in
`extra`. `resources/screens/team.form.json` in the site:

```json
[
  {
    "op": "add",
    "target": "project-fields",
    "node": {
      "id": "experience",
      "type": "wx-input-number",
      "name": "experience",
      "label": "Years of experience"
    }
  }
]
```

and in a service provider of the site:

```php
use WebxUi\Admin\Facades\Screens;

public function boot(): void
{
    Screens::extend('team.form', resource_path('screens/team.form.json'));
}
```

The field appears in the editor, is checked by its type on every save — the panel's and an
agent's — and reaches the card as `$member['fields']['experience']`. Printing it is a line in the
block's template:

```blade
@if ($member['fields']['experience'] ?? null)
    <p class="b-team__experience">{{ $member['fields']['experience'] }} years</p>
@endif
```

## A target for other modules

A person is a [relation](/guide/relations) target under the key `team-member`: a module that
wants «the reviews of this doctor» adds a `wx-relations` field with `"target": "team-member"` to its
screen, and the picker offers the team with their job titles and photos. The module itself points
at nobody on the team.

## The panel

**Team** is a list and an editor side by side (`WxListDetail`), without pages — the list is where
people are put in order, and a drag cannot cross a page boundary. A row is the photo (or the
initials), the name, the job title and a mark when the person is not published. While a search
narrows the list there are no grips. The open person is in the address (`?member=12`). **New
person** is a row that opens an empty form; the person is created by its first save. On a phone
the editor slides over the list and draws its own «Back».

The editor is the screen `team.form`: the photo, the name, the job title, the text, the social
links, the services, **Published** and the project's card. Save with the button or `Ctrl+S`;
leaving with unsaved changes asks first.

```
GET    /api/cms/team                  trashed, search — no pages
POST   /api/cms/team                  { values } — created from the form
GET    /api/cms/team/{id}             { member, values }
PUT    /api/cms/team/{id}             { values } — 422 under the field's name
DELETE /api/cms/team/{id}             to the bin · POST /restore
POST   /api/cms/team/reorder          { ids }
```

A missing name answers under `name.<default language>`; a bad social link under
`socials.<n>.url` or `socials.<n>.network`, `n` counting the rows as the editor sees them.

## For an agent: MCP

With the panel's MCP server on (see [AI agents](/guide/agents)), the section is tools too:

| Tool           | What it does                                                           |
| -------------- | ---------------------------------------------------------------------- |
| `team_list`    | Everybody in the order of the site, or words — or the bin              |
| `team_get`     | One person in full: every language, the links, the services, the extra |
| `team_create`  | A person at the end of the team; unpublished unless asked              |
| `team_update`  | The values — on the site at once, the team has no draft                |
| `team_delete`  | To the bin                                                             |
| `team_reorder` | The one order — the people named first, the rest where they were       |

A person is named by their id — names repeat, two Annas are two people. A plain string in `name`,
`job_title` or `text` is the default language; `{ "en": "…", "ru": "…" }` is every language at
once. The photo is a library key (`"media/ab/cd/anna.jpg"`), and a key the library does not have
is refused rather than left to draw the initials. `socials` is the whole list of
`{ network, url }`, and a network the site does not have is refused with the list of those it has.
`services` takes ids or addresses (`"/services/braces"`), and exists only on a site with services.
Every tool that changes something takes `dry_run: true`. The values go through the same form as
the panel's, and `team_create` is one transaction: a refusal leaves nobody behind.

Before writing, an agent reads **`team://catalog`**: the networks the site accepts, and everybody
in order, unpublished people included and marked, each with `written_in` — the languages their text
is written in — and the services they are linked to.

Putting a Team block on a page is not a team tool: it is `blocks_edit_content` on the page, with a
`team` block whose `team` value is `{ "limit": 3, "related": { "type": "service", "ids": [],
"current": true } }` and whose `layout` is one of `grid`, `slider`, `list`.

## Demo content

`php artisan webx:demo` seeds six people in the two languages of the demo, as far as the site has
them. One is not published; one has no Russian text and is shown on the Russian page without it;
three have social links, and one of those links is to a network the config does not have — stored,
and left off the card. Nobody has a photo — the initials stand in, and the demo is where that is
seen. The offered block type is installed if the site has not taken it yet, and then:

- with `module-pages` — a page `/team` with everybody in a grid;
- with `module-services` — the people are linked to the demo services, and «Company website» gets
  a list of «who does it», the service of this page.

`--remove` takes all of it back out, the block type too when the demo installed it. A team that
already has anybody in it leaves the demo alone.
