# webx-ui/module-team

The team as a section of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel: people
with a photo, a name, a job title, a short text and their social links — and, when the site has
services, the services each of them provides. Shown on any page as a block (a grid, a slider or a
list) and in the site's own templates through `team()`.

The module has no public route and no page of its own. A person reaches the site **in a block**:
"our doctors" as a grid on the About page, "who does this" as a list on a service's page. The page
brings the address, the SEO and the menu entry; the module brings the people.

## Requirements

- PHP 8.3+, Laravel 13
- `webx-ui/module-admin`, `webx-ui/module-blocks`, `webx-ui/module-media`, `webx-ui/localization`,
  `webx-ui/routing`
- `webx-ui/module-pages` for a page to put the block on — suggested, not required
- `webx-ui/module-services` to link people to services — suggested, not required

## Install

```bash
composer require webx-ui/module-team
php artisan migrate
php artisan webx:blocks:offered --install --module=team
```

The last line puts the offered block type **Team** on the site and publishes it. A type the site
already has under the slug `team` is left alone: the site may have rewritten it. `webx:setup`
runs this line by itself for a new site.

Permissions: `team.view`, `team.manage`. The section is one entry of the panel's menu, **Team**,
at the top level.

## A person

| Field       | Stored as                                                                        |
| ----------- | -------------------------------------------------------------------------------- |
| `photo`     | the value of a `wx-media` field — a library key, never an address                |
| `name`      | translatable; required in the default language — the one required field          |
| `job_title` | translatable                                                                     |
| `text`      | translatable plain text; printed with its line breaks                            |
| `socials`   | `[{ network, url }]` in the editor's order; `http://` or `https://` only         |
| `services`  | a `wx-relations` field on `service`; there only when `module-services` is        |
| `published` | the whole of a person's life: no draft, no history; the bin brings them back     |
| `position`  | the one order there is — the team has no categories, so there is no second order |

**Languages.** Nobody is hidden over a language. A name or a job title that is not written in the
language is taken from the default one; a text that is not written in it is simply not printed —
the person is shown without it. Hiding a doctor from the Russian page over one untranslated
paragraph would be losing them.

**Anything else** — experience, education, certificates, the days they see patients — is the
site's own field, patched onto the screen `team.form` into the card `project-fields`. It is
stored in `extra` and reaches the card under `fields`.

## Social networks

The networks a link may point at are the config `webx-team.networks`, key → name:

```php
// config/webx-team.php
return [
    'networks' => [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'x' => 'X',
        'telegram' => 'Telegram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'vk' => 'VK', // a site's own
    ],
];
```

The select of the form takes its options from here (the provider lays them over the screen with a
patch), and the server checks a link against the same options — so a network the site adds is one
line of config. Names are brands and are not translated. A network taken out of the list drops out
of every card on the site and stays in the database: put it back, and the links come back.

A row of the form with neither a network nor an address is dropped on save; a row with only one of
the two, an address that is not `http(s)://` or a network that is not on the list is refused under
that row's field. A link the person already has to a network taken off the list is kept as it is:
the form sends it back the way it opened it, and a save is not refused over it.

## Two roads into a template

**The block.** The offered type has a `wx-collection` field on the `team` source. The editor of
the page chooses a limit and, when the site has services, "only related to" — some services, or
**the service of this page**. The template gets the people already read:

```blade
@foreach ($team['items'] as $member)
    <article id="{{ $member['anchor'] }}">
        <h3>{{ $member['name'] }}</h3>
        <p>{{ $member['job_title'] }}</p>
    </article>
@endforeach
```

**`team()`.** A template of the site, or a block that wants something the field does not do, asks
for them itself:

```blade
@foreach (team()->relatedTo('service', $service)->take(3) as $member) … @endforeach
```

| Step                  | What it does                                                        |
| --------------------- | ------------------------------------------------------------------- |
| `relatedTo($t, $ids)` | only those related to these records (`'service'`, an id or a model) |
| `only([12, 7])`       | these and no others, in this order                                  |
| `except($member)`     | all but these                                                       |
| `take(6)`             | at most six; null or zero — all                                     |
| `locale('uk')`        | the language of the cards; by default the one the page is drawn in  |
| `get()`, `first()`    | a list of cards, or one; the query itself can be looped and counted |

`relatedTo()` with an empty list is nobody, not everybody. There is no `in()` and no
`categories()`: the team has no categories.

Both roads hand over the same card:

```php
[
    'id' => 12,
    'anchor' => 'member-12',
    'categories' => [],            // the contract of a collection asks for the key
    'name' => 'Anna Petrova',
    'initials' => 'AP',            // what stands in for a missing photo
    'job_title' => 'Orthodontist', // '' when there is none
    'text' => "…\n…",              // '' when it is not written in the language
    'photo' => ['url' => …, 'thumb' => …, 'width' => …, 'height' => …, 'alt' => …], // or null
    'socials' => [['network' => 'instagram', 'label' => 'Instagram', 'url' => 'https://…']],
    'service_links' => [['id' => 3, 'title' => 'Braces', 'url' => '/braces']], // [] without services
    'fields' => ['experience' => '12'], // the project's own fields, by name
]
```

A list of any length is the same few queries. `team()` is declared only if the site has no
function of that name; `php artisan webx:doctor` says whose it is.

## The Team block

The type is a document in `resources/blocks/team.json`, the same format as `webx:blocks:export`:
a heading, the `team` collection, a `layout`, `columns` for a grid and a slider, `autoplay` for a
slider, and `hide_text` — off, the untouched state, shows the text.

- **Grid** — up to `columns` across (4 when not set), fewer when the block is narrow.
  **Slider** — the same card in a ribbon that snaps, with arrows and, if asked, autoplay.
  **List** — the photo on the left, the name, the job title, the text and the services beside it:
  the layout for biographies, and for "who does this" on a service's page.
- A card is an `<article id="member-12">`: the photo or the initials in a circle, the name, the
  job title, the text, links to the services and the social links.
- **Social links are icons** for the seven networks of the default config — inline SVG from
  [Simple Icons](https://simpleicons.org) (CC0), right in the template. A network the site adds is
  printed as its name until the site's copy of the type gets an icon. Every link opens in a new tab
  with `rel="noopener"` and says whose it is to a screen reader.
- **Without JavaScript everything reads:** the slider is a ribbon that scrolls sideways. The
  script adds the arrows and autoplay; `prefers-reduced-motion` turns autoplay off.
- The styles are neutral — `currentColor` and `em` — so the block stands in any site's design.

**No markup.** `Person` needs a page of the person to hang on, and there is none.

## A target for other modules

A person is a relation target under the key `team-member`: a module that wants "the reviews of
this doctor" adds a `wx-relations` field with `"target": "team-member"` to its screen, and the
picker offers the team with their job titles and photos. The module itself points at nobody on the
team.

## The panel's API

Under the panel's API path, behind `team.view` to read and `team.manage` to write:

```
GET    team                  ?trashed=1&search=     → { data: [row] }
POST   team                  { values }             → 201 { data: { member, values } }
GET    team/{id}                                    → { data: { member, values } }
PUT    team/{id}             { values }             → 422 under the name of the field
DELETE team/{id}
POST   team/{id}/restore                            → { data: row }
POST   team/reorder          { ids }
```

A row is `{ id, name, job_title, initials, photo: { thumb } | null, published, position,
updated_at, deleted_at }`; `member` is `{ id, name, published, deleted_at }`.

## License

MIT
