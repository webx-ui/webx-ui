# Moving content between stands

A site lives on several stands at once — a laptop, a dev server, production — and its content is
made on whichever one is at hand. Two commands in `webx-ui/module-admin` carry it from one to
another, in any direction: the database rows that are the site's content, and the files uploaded
for it.

```
php artisan webx:snapshot [--all] [--with-admins] [--no-media] [--output=path]
php artisan webx:snapshot:restore <archive> [--all] [--with-admins] [--no-media] [--keep-extra] [--force] [--no-backup]
```

::: danger A restore replaces content
`webx:snapshot:restore` deletes the target's pages, blocks, menus, settings, media records — every
content table — and puts the archive's in their place; by default it also makes the public disk
exactly the archive's. It takes a `webx:db:backup` dump first and prints where it is: that dump is
the way back.
:::

## Local → dev, by hand

```bash
# on the laptop
php artisan webx:snapshot
scp storage/app/snapshots/my-site-local-2026-10-08-101500.tar.gz dev:/var/www/my-site/storage/app/snapshots/

# on dev
php artisan webx:snapshot:restore storage/app/snapshots/my-site-local-2026-10-08-101500.tar.gz
```

The other way round — dev or production to the laptop — is the same two commands with the hosts
swapped. Deploy the code before the content: a restore refuses an archive whose migrations the
target's code has never heard of.

## What travels and what stays

Every package says which of its tables are which. The split is the point of the command: the
site's content moves, the stand's own life does not.

| Group       | Tables                                                                                                                                                                                                                                           | Travels                         |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------- |
| `content`   | pages, blocks and their versions and regions, menus, blog, services, recipes, FAQ, reviews, press, events, catalog, SEO rules and redirects, media records, settings, forms, addresses (`routes`), languages, content versions, notes, relations | always                          |
| `admins`    | `cms_users`, `cms_roles`, `cms_role_user`                                                                                                                                                                                                        | with `--with-admins` or `--all` |
| `stand`     | enquiries and their files, statuses and events; the journal; audit results; MCP grants and calls; OAuth clients and tokens; sign-in records; catalog runs and view counts                                                                        | only with `--all`               |
| `derived`   | glued block bundles                                                                                                                                                                                                                              | never; emptied on restore       |
| `transient` | sessions, cache, queues, `migrations`                                                                                                                                                                                                            | never                           |

Enquiries stay put on purpose: what visitors sent is personal data and has no business on a
laptop, and a copy of local must not wipe dev's real ones. A table nobody declared — one the site
created itself — is named in the output of both commands and travels only with `--all`. Declare
it in `config/webx-admin.php`:

```php
'snapshot' => [
    'tables' => [
        'content' => ['shop_banners'],
        'stand' => ['newsletter_*'], // a trailing * is a prefix
    ],
],
```

A package of your own declares from its provider:

```php
$this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
    $tables->content('shop_banners')->stand('shop_orders');
});
```

### Settings

All settings are content. None of the shipped ones holds a key or a password — captcha keys and
mail live in `.env`, which a snapshot never touches. A project that stores an integration's key as
a setting names it in `webx-settings.stand_own`; a restore keeps that key's value on the target and
drops the archive's.

## What a restore does, in order

1. Reads the manifest. An archive from an unknown format version is refused; one from a newer
   patch of the same version is read.
2. Refuses if the archive ran migrations this code does not have. On production it refuses
   without `--force`. It shows the source site, stand and date, how many tables and rows it will
   replace and how many files it will bring and delete, and asks. Without a terminal it needs
   `--force` (`--no-interaction --force` is the hosting tools' way).
3. Runs `webx:db:backup` (unless `--no-backup`) and prints the dump's path.
4. Unpacks and checks every file against its hash — a damaged archive is refused whole.
5. Runs `migrate` if this stand has migrations it has not run yet.
6. In one transaction with foreign keys off: empties the derived tables, replaces the rows of the
   content tables, matching columns by name. A column the archive has and this stand dropped is
   left out and named; one this stand added takes its default — that is what an older archive
   looks like. Rows that now point at nothing (an enquiry of a form that is gone) are counted and
   reported, not deleted.
7. Rewrites the source stand's address (`APP_URL` in the manifest) to this stand's in every
   string: `http://site.local/about`, `//site.local:8000/x` and the JSON-escaped
   `http:\/\/site.local` alike. A bare host in an e-mail address or in prose is left alone.
8. Files: by default a mirror — the public disk becomes exactly the archive's; `--keep-extra`
   adds and replaces but deletes nothing. Previews (`thumbs` folders) are neither carried nor
   kept; they are cut again on the first request. Then `storage:link` if it is missing.
9. Clears the caches: the application's (settings, menus, block types, addresses), views,
   languages, block types; rebuilds the sitemap and, with Manticore, the search index.

Restoring into PostgreSQL is not supported yet; making a snapshot there is.

## The archive

A `.tar.gz` that `tar xzf` opens anywhere:

- `manifest.json` — format and version, site name, `APP_ENV`, `APP_URL`, when, the installed
  `webx-ui/*` versions, the migrations that had run, every table with its group, columns and row
  count, and every file with its SHA-256.
- `database.jsonl` — per table a line `{"table", "columns"}`, then one JSON array per row. Rows,
  not a native dump: it goes into whatever shape the target's tables have, and from MySQL into
  SQLite.
- `media/…` — the public disk, without `thumbs`.

The format is a contract for other tools: fields are added, never renamed, and a reader accepts
any patch of its own version. The archive holds the site's content and, with `--all`, personal
data — keep it off anything that serves files, and delete it once it is restored.
