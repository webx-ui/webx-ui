# webx-ui/localization

Languages for a WebX UI site: the list a site is published in, translated Eloquent attributes,
and the dictionary the admin panel is drawn from.

A service library with no section of its own — like `webx-ui/seo`, it is what the modules that
do have sections are built on.

## Two problems, kept apart

Mixing these is how a project ends up with translation files nobody can find:

|            | Interface phrases        | Content               |
| ---------- | ------------------------ | --------------------- |
| Written by | whoever wrote the module | whoever runs the site |
| Changes    | at deploy                | all day               |
| How many   | a finite list of keys    | as much as there is   |
| Lives in   | `lang/` in the package   | the database          |

One file of phrases per module, shared by both halves of it; one language map per translatable
column. Neither knows about the other.

## Install

```bash
composer require webx-ui/localization
php artisan migrate
php artisan webx:locales:seed
```

## The languages

```php
use WebxUi\Localization\Locales;

$locales = app(Locales::class);

$locales->codes();        // ['uk', 'ru']
$locales->defaultCode();  // 'uk'
$locales->current();      // the language this request is being answered in
$locales->use('ru');      // false if the site does not publish in it
```

They live in the `locales` table so that adding one is a thing somebody does in the panel rather
than a deploy. `config/webx-localization.php` names the set a fresh installation starts with,
and is also the answer while the table does not exist yet — the first `migrate` runs through
code that wants a language long before there is one to read.

## Translated attributes

A model says which of its columns hold a language map:

```php
use WebxUi\Localization\HasTranslations;

class Page extends Model
{
    use HasTranslations;

    public function translatable(): array
    {
        return ['title', 'slug', 'content'];
    }
}
```

```php
Schema::create('pages', function (Blueprint $table) {
    $table->id();
    $table->translatable('title', 'slug', 'content');   // json, nullable
    $table->boolean('is_published')->default(true);
});
```

```php
$page->title;                                  // this request's language, then the fallbacks
$page->title = 'Contacts';                     // writes that language, leaves the others alone
$page->forLocale('uk')->title;
$page->getTranslations('title');               // ['en' => 'Contacts', 'uk' => 'Контакти']
$page->setTranslations('title', [...]);        // what a form editing every language posts

Page::whereTranslation('slug', $slug, 'uk')->first();
Page::orderByTranslation('title', 'asc')->get();
```

Stored as `{"en": "Contacts", "uk": "Контакти"}`, which is the shape
`spatie/laravel-translatable` uses — data written here is readable by anything that understands
that convention.

Serialising gives one language, not all of them: a public page renders in one, and an API for
the site should not hand out every translation of everything. The panel asks for the maps
explicitly with `translationsToArray()`.

A column that still holds a plain string — a site being converted — reads as the default
language's value rather than as nothing, and the next save writes a proper map.

### What a JSON column cannot do

It cannot make a slug unique inside a language, and it cannot be indexed without a generated
column per language. Uniqueness of an address belongs to the routing table in `webx-ui/seo`,
where it holds across every kind of entity at once instead of one table at a time.
`whereTranslation()` is what validation uses in the meantime.

## The panel's own language

The language of the interface belongs to the person, not to the site: somewhere published only
in Ukrainian can still be maintained by somebody who wants English menus.

```
GET  /api/cms/locales              which languages exist — public
GET  /api/cms/translations/{code}  the dictionary for one of them — public
PUT  /api/cms/auth/locale          what this administrator chose
```

The first two are public because the sign-in screen has to be drawn before there is a session
to ask, and because the text of an interface is not a secret.

`webx.panel-locale` middleware sets the application's language for a panel request — from the
signed-in administrator, or failing that from `X-Webx-Locale`, or failing that from the browser.
It matters for more than menus: a 422 carries validation messages, and those are written by the
server.

## Where the phrases come from

Every package ships `lang/<code>/<group>.php` and registers it the ordinary Laravel way:

```php
$this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-pages');
```

`__('webx-pages::fields.title')` then works on the server, and the same file reaches the browser
in the dictionary above — a module translates its strings once and both halves of it speak the
same language.

Resolution, in order:

1. what the site published over the package — `lang/vendor/webx-pages/<code>/`
2. the package's own `lang/<code>/`
3. the fallback language
4. the key itself

Step one is how a site adds a language a module never shipped, or disagrees with a word in one
it did:

```bash
php artisan vendor:publish --tag=webx-pages-lang
```

The merge is per line rather than per file, so one new key in a release cannot blank out a
screen that was translated a year ago.

`config('webx-localization.panel')` lists the languages the interface may be shown in — a
different question from which languages the site publishes, and listed rather than detected,
because a language belongs there only once somebody has read the translation.

## Caching

The language list and each dictionary are built once and kept. After editing a `lang` file:

```bash
php artisan webx:locales:clear
```

## Licence

MIT.
