# webx-ui/templates

Mustache for templates kept in the database: a channel's message in `webx-ui/module-notifications`,
a product feed. It renders, checks and escapes, and knows nothing about who the template is for. It
is a library with no section, no config and no tables; the words of a refusal are its only
resource. Its browser half is in `@webx-ui/core` (`renderTemplate`, `escapeForFormat`, `knows`,
`refuseInCdata`, `snippetOf`).

## What it owns

- **`Template`**: `usages()` (the names a template uses, with their sections), `render()` (text and
  unknown names), `compile()` (read and checked once, for a stream), `refuseInCdata()`.
- **`Compiled`**: `render($values)`, for what renders one template many times.
- **`Escapes::for($format, $options)`**: `xml`, `json`, `csv` (`delimiter`), `text`.
- **`Cdata::of($text)`**: a value `xml` writes whole, as `<![CDATA[…]]>`; empty is null.
- **`Html`**: `escape()`, `plain()`, `problems()` — HTML in a message template.
- **`Variable`, `Variables`**: what a template may use, `knows()`, `examples()`. `Variable` is open
  for extending: the notifications' adds `address` and `internal`.
- **`TemplateError`**: `key`, `replace`, `words()`, from `webx-templates::errors.template-*`.
- **`first` and `last`** on every element of a list, `'1'` or `''`. A field of that name of its own
  wins.
- **Refused:** partials, delimiter changes, pragmas, blocks, `{{.}}`, names in capitals or with two
  dots. `{{{name}}}` and `{{&name}}` read as `{{name}}`.

## Change it without forking

| You want                               | Do this                                                                       |
| -------------------------------------- | ----------------------------------------------------------------------------- |
| Render one template for many records   | `Template::compile($template, Escapes::for('xml'))`, then `->render($values)` |
| Your own escaping                      | pass a closure: it takes the value, and `true` after it for a `Cdata`         |
| HTML into XML                          | give the value as `Cdata::of($html)`, print it with `{{name}}`                |
| A comma between the elements of a list | `{{#images}}{{^first}},{{/first}}"{{url}}"{{/images}}`                        |
| More on a variable                     | extend `Variable` as a `readonly class`, keep `Variables` for the lookup      |
| Other words for a refusal              | `php artisan vendor:publish --tag=webx-templates-lang`                        |
| Show the words from your own package   | `$this->app->register(TemplatesServiceProvider::class)` in your provider      |

## Do not

- Do not add a way to print a value raw: every value passes the caller's escaping. HTML for XML is
  what `Cdata` is for.
- Do not put a template through Blade or `eval`: it is typed by an administrator. Use `Template`.
- Do not change a rule of the walk on one side only: the server and `@webx-ui/core` read the same
  cases. Add the case to `tests/fixtures/render.json` and change both.
- Do not call `refuseInCdata()` for a format that is not XML: a message may contain those words.
  Call it where a template writes XML.

## Check your work

- `vendor/bin/phpunit packages/templates/tests` from `php/` of the monorepo: `ParityTest` reads the
  shared cases, `packages/core/src/components/CodeEditor/template.test.ts` reads the same file.
- A template that should be refused throws `TemplateError`; `$error->words()` is what to show.

## Read more

- `README.md` next to this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_CATALOG_FEEDS.md (§2)
