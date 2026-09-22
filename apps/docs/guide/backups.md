# Database backups

`webx-ui/module-admin` writes a gzipped dump of the database every night and keeps a month of
them. There is one line about it in the panel and nothing else — no screen, no buttons, no
restore.

## What this is not

It is **insurance, not a restore system**. The file lands on the same disk as the database it
came from, so it survives a mistake and it does not survive a dead server. What it is for is
getting yesterday's version of one row, one table or one article back by hand.

- **Not protection against losing the machine.** The dump is next to the database.
- **Not a copy of the site.** Uploaded files are not in it, and a site restored from the dump
  alone would have every record and no pictures.
- **Not a replacement for the host's own backups** where there are any. It exists because for
  a good many hosts there are not.

If the site holds anything whose loss would be serious, it needs a backup somewhere else as
well. This is the floor, not the ceiling.

## The command

```
php artisan webx:db:backup [--keep=30]
```

It writes `<database>-2026-09-21-0310.sql.gz` into `path` under the root of `disk` — with the
defaults, `storage/app/private/backups`, because that is where Laravel 11+ roots the `local`
disk. Gzipped as the dump comes out, so no uncompressed copy of the database ever touches the
disk. Then, and only then,
it deletes the files older than `--keep` days — clearing out last week without having written
tonight is the one thing a backup command must never do, and it is exactly what happens if the
two steps are written the other way round. The newest file is never deleted, whatever its age.

A failure exits non-zero and writes the reason to the log. Nothing that was already there is
touched, and the half-written file goes: a dump that died halfway must not be allowed to become
the newest file in the directory and report itself as last night's.

MySQL and MariaDB are dumped with `mysqldump`, PostgreSQL with `pg_dump`, SQLite by copying the
file. Structure is kept for every table; rows are skipped for the ones the application rebuilds
by itself — `cache`, `sessions`, `jobs` and the rest of `skip_data`, which on most sites are
most of the file.

## The schedule

The package puts the command on the scheduler itself:

```php
Schedule::command('webx:db:backup')->dailyAt(config('webx-admin.backup.at'))->onOneServer();
```

What it cannot do is run the scheduler. The site needs the system cron:

```
* * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1
```

That is a deployment step, and it belongs in the install script next to `passport:keys`. There
is no cron on a development machine — run the command by hand there.

## Configuration

```php
// config/webx-admin.php
'backup' => [
    'enabled' => true,
    'at' => '03:10',
    'keep' => 30,
    'disk' => 'local',
    'path' => 'backups',
    'binary' => null,
    'column_statistics' => null,
    'options' => [],
    'skip_data' => ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'],
],
```

- **`disk`** has to be a local one. A dump is written as a stream to a path, and it is
  deliberately kept off anything the site serves.
- **`binary`** is the path to `mysqldump` or `pg_dump`. Left empty the name is used and `PATH`
  decides, which is fine in a container and usually wrong everywhere else — MariaDB 11 ships
  `mariadb-dump` and no `mysqldump` at all.
- **`column_statistics`** stays `null` unless the dump fails over it. A `mysqldump` 8 client
  asks a MariaDB server for column statistics and dies on the answer; MariaDB's own client does
  not know the flag that turns them off and dies on that. Only the machine knows which pair it
  has: set `false` for the first case, leave it alone for the second.
- **`options`** is passed through to the dump tool, for the flag this package has not heard of.

Switching `enabled` off removes the line from the panel as well: a site that has decided not to
do this is not nagged about not doing it.

## What the panel says

One line at the foot of **Settings**, for whoever has `settings.view`:

> Last database snapshot: today at 03:10 · 4.2 MB

If the newest file is more than two days old, or there is none, the same line is a warning
instead. A backup whose breakage is discovered on the day it was needed is not a backup, and
this sentence is the whole of the guard against that. Nothing is recorded in the database: the
line is the newest file in the directory, and a task that failed is the file that is not there.

## Security

A dump is the whole database in one file: the administrators' password hashes, the telephone
numbers and addresses on every enquiry, every token. So:

- it is written 0600 into `storage/app/private`, which nothing serves;
- Laravel's own `storage/app/.gitignore` already keeps it out of the repository — check that it
  is still there rather than assuming it;
- a dump copied onto a laptop is the same personal data as the live database, and the law that
  applies to one applies to the other.

## Getting one table back

This is what the whole thing is for, so it is worth knowing before the day it is needed. The
tables that keep their rows are dumped structure-and-data together, one after another, which
makes one table a single contiguous range in the file:

```bash
gzip -dc storage/app/private/backups/site-2026-09-21-0310.sql.gz \
  | awk '$0 == "-- Table structure for table `pages`" { keep = 1 }
         keep && /^-- Table structure for table `/ && $0 != "-- Table structure for table `pages`" { exit }
         keep' \
  | grep -v '@OLD_' \
  > pages.sql
```

`grep -v '@OLD_'` drops the save-and-restore lines `mysqldump` wraps the whole file in; without
the header they came from, the restore half fails on `Variable 'time_zone' can't be set to the
value of 'NULL'`. What comes out is a `DROP TABLE`, a `CREATE TABLE` and the `INSERT`s, which
loads into any database:

```bash
mysql -u root scratch < pages.sql
```

Do that into a scratch database, not the live one, and copy across what is actually missing.
The whole file restores the same way, and the tables in `skip_data` come back empty.
