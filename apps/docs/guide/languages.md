# Languages

Two different questions, and the panel keeps them apart on purpose:

- **What language is the panel drawn in?** A property of the person reading it. An editor in
  Warsaw reads the interface in Polish whether or not the site publishes a word of it.
- **What languages does the site publish in?** A property of the site. A form field marked
  `localized` holds one value per publishing language, and the panel gives it a language
  switcher.

A site published only in Ukrainian can still be run by somebody who reads the panel in English,
and that is the normal case rather than an edge one.

## Where the words live

Once, in the Composer package — `lang/<locale>/<group>.php`. Both halves read the same files:
the server for the messages it writes itself (a 403, a 422 under a field), and the browser
through `GET /api/cms/translations/{locale}`, which hands the whole dictionary over as data.

```
php/packages/module-pages/lang/
├── en/
│   ├── module.php
│   ├── page.php
│   └── pages.php
├── ru/
└── …
```

A second dictionary shipped inside the npm package would be how a project ends up with two
stores that drift apart and only one of them translated. So the npm package carries only a
floor — the built-in English in `messages.ts` — for a panel assembled with no server behind it,
and anything the server sends wins.

Every WebX UI package ships ten languages: `en`, `ru`, `uk`, `de`, `pl`, `fr`, `es`, `it`,
`pt`, `tr`. A test in each package checks that every one of them has exactly the keys English
has, because a missing line degrades quietly and a gap can sit unnoticed for months.

### Reading a line

```ts
import { useTranslate } from '@webx-ui/module-admin'

const t = useTranslate('webx-pages')

t('page.save') // the `save` key of the `page` group
t('webx-admin::editor.back') // a line from another namespace, borrowed rather than copied
```

Words a screen is described with are translated on the server instead, with
`trans::webx-pages::page.save` in the JSON — see [Screens](/guide/screens).

## Which language wins

For one line, in order: the language asked for, then the site's `fallback`, then the built-in
English in the npm package, then the key itself. The fallback is merged **underneath each
group** rather than beside it, so a group somebody has half-translated is complete — the lines
that exist are in the reader's language and the rest are in the fallback. Nothing ever renders
as `page.save` unless no package anywhere has that key.

## Pages of prose

One deliberate exception to the parity rule: a `help` group is a page read whole, not a line in
a sentence somebody is reading. Missing, it hands over the fallback language's page entire and
reads correctly; a missing label leaves a hole in a sentence. So a `help` group is translated
when somebody writes it rather than before the rest can ship, and the parity test skips it.

That is also why help is Markdown in a `lang` file rather than markup in a component: the same
text is what an agent is handed over MCP — `blocks://schema` is the page the `?` beside the
schema editor shows — and two explanations of one thing is how they start to disagree.

## Choosing the panel's language

Listed rather than detected, because a language belongs in the menu only once somebody has read
the translation and found it good:

```php
// config/webx-localization.php
'panel' => ['en', 'ru', 'uk'],
```

The choice is stored on the administrator, not in the browser, so it follows them to the next
machine — and so the server knows which language to write a 422 in. The picker is in the corner
menu rather than in a settings screen: somebody who has landed in a language they cannot read
needs it within reach, not three clicks into a section they cannot navigate.

Until they choose, there is nobody to ask but the browser. The panel says which language it is
currently drawn in with `X-Webx-Locale` on every request, which is what keeps a Russian sign-in
screen from landing in an English panel.

::: tip Waiting for words
The panel waits for the dictionary before it mounts, and does not wait for the manifest.
Drawing the sign-in form in English and rewriting every label a moment later reads as a bug
rather than as a translation arriving — and the manifest needs a session, so for a visitor it
is bound to be a 401.
:::

## Adding a language

Publish the packages' files, translate them, and name the code:

```bash
php artisan vendor:publish --tag=webx-admin-lang
```

Overrides land in `lang/vendor/<namespace>/<locale>`, which Laravel's loader merges over what
the package ships. That is also how a site changes a word it disagrees with in a language that
is already there — `Delete` to `Remove`, say — without forking anything.

## The languages the site publishes in

These live in the `locales` table, so one can be added from the panel without a deploy. The
config holds only the seed a fresh installation gets:

```php
'locales' => [
    ['code' => 'en', 'default' => true],
],
```

They arrive with the manifest, and every `localized` field offers them:

```vue
<wx-input v-model="title" localized />
```

The value of such a field is a record — `{ en: 'Shoes', uk: 'Взуття' }` — and reading one for a
particular language is `localizedValue(value, code, fallback)`. A field written before the site
had a second language holds a plain string, and both halves read that as the default language's
value rather than as a mistake.

::: warning A field's languages are not the panel's
`localized` switches between _content_ languages. The panel being in English has no bearing on
which languages a page is published in, and an editor routinely writes Ukrainian copy in an
English interface.
:::
