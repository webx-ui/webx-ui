# webx-ui/templates

Mustache for templates kept in a database. An administrator types them, so they can call no code
and print nothing unescaped: lambdas, partials, delimiter changes and raw output are off.

Part of [WebX UI](https://github.com/webx-ui/webx-ui). Used on its own it needs nothing but
`mustache/mustache` and `illuminate/support`. The browser half, for a preview that shows what the
server writes, is in `@webx-ui/core`.

## Requirements

- PHP 8.4+
- Laravel 13 (`illuminate/support`)

## Install

```bash
composer require webx-ui/templates
```

## Render

```php
use WebxUi\Templates\Escapes;
use WebxUi\Templates\Template;

$rendered = Template::render('Order {{number}}: {{note}}', ['number' => '7'], Escapes::for('text'));

$rendered['text'];    // Order 7:
$rendered['unknown']; // ['note']
```

A template that cannot be read, or uses what is refused, throws `TemplateError`: `$error->key`, and
`$error->words()` in the language of the site.

## Many records

`compile()` reads and checks a template once:

```php
$item = Template::compile('<item><id>{{id}}</id></item>', Escapes::for('xml'));

foreach ($products as $product) {
    $stream->write($item->render(['id' => (string) $product->id]));
}
```

## Escaping

| Format | What it does                                                                                |
| ------ | ------------------------------------------------------------------------------------------- |
| `xml`  | `&`, `<`, `>`, `"`, `'` as entities; drops the control characters an XML 1.0 parser refuses |
| `json` | the inside of a JSON string: the quotes are the template's                                  |
| `csv`  | a value with the delimiter, a quote or a line break goes in quotes; `['delimiter' => ';']`  |
| `text` | as it is                                                                                    |

`$escape` can be any closure: it takes the value, and `true` after it for a `Cdata`.

## HTML in XML

There is no raw output. A value that has to go in whole is a `Cdata`:

```php
Template::render('<d>{{html}}</d>', ['html' => Cdata::of('<p>Tea</p>')], Escapes::for('xml'));
// <d><![CDATA[<p>Tea</p>]]></d>
```

`Cdata::of('')` is null, so `{{#html}}` works as for any value. A variable inside a literal
`<![CDATA[ … ]]>` of the template would be escaped twice: `Template::refuseInCdata($template)` refuses
it, for whatever writes XML to call on saving.

## Lists

Every element of a list has `first` and `last`, `'1'` or `''`. An element's own field of that name
wins.

```mustache
[{{#images}}{{^first}},{{/first}}"{{url}}"{{/images}}]
```

## Variables

`Variable` describes what a template may use: name, label, example, the fields of a list, `field.*`
for a family. `Variables` looks a name up the way Mustache does:

```php
$variables = new Variables([
    new Variable('items', 'Items', example: [['name' => 'Tea']], fields: ['name' => 'Name']),
]);

$variables->knows('name', ['items']);  // true: a field, inside its list
$variables->knows('first', ['items']); // true: every list has it
$variables->knows('name');             // false
```

## Words

The refusals are under `webx-templates::errors`, in ten languages. A package that shows them
registers `TemplatesServiceProvider` itself.
