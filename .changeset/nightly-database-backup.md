---
'@webx-ui/module-admin': minor
'@webx-ui/module-settings': patch
'@webx-ui/php': minor
---

A gzipped dump of the database every night, and one line in the panel saying so

Insurance, not a restore system. The file lands on the same disk as the database it came from,
so it survives a mistake and not a dead server, and there is no restore button anywhere — what
it is for is getting yesterday's version of one row, one table or one article back by hand. It
exists because backups are an extra on a good many hosts and absent on the rest, and having
something is better than having nothing.

- `webx:db:backup` writes `storage/app/private/backups/<database>-2026-09-21-0310.sql.gz`,
  gzipped as the dump comes out, so no uncompressed copy of the database ever touches the disk.
  `mysqldump` for MySQL and MariaDB, `pg_dump` for PostgreSQL, a copy of the file for SQLite.
- Rotation runs **after** a dump has succeeded and never touches the newest file. Clearing out
  last week without having written tonight is the one thing a backup command must not do, and
  it is exactly what happens if the two steps are written the other way round. A failure exits
  non-zero, logs why, deletes its own half-written file and leaves everything else alone.
- Structure for every table, rows for the ones worth keeping: `cache`, `sessions`, `jobs` and
  the rest of `skip_data` are dumped with `--no-data`, which on most sites is most of the file.
  The tables that keep their rows are dumped structure-and-data together, so pulling one table
  out of the finished file is a single contiguous range — the guide has the one-liner.
- The password never appears in an argument, where `ps` would show it to anybody with a shell:
  MySQL gets a 0600 defaults file and PostgreSQL a 0600 `.pgpass`, both removed in a `finally`.
  `--single-transaction --quick` so the nightly dump does not lock the site, `--no-tablespaces`
  so it runs as a shared-hosting user, `utf8mb4` so the translated JSON columns survive.
- `module-admin` puts the task on the scheduler itself, at `webx-admin.backup.at`. What it
  cannot do is run the scheduler: the site still needs a system cron on `schedule:run`, and the
  line in the panel is what notices when there is not one.
- That line is at the foot of the settings screen, for whoever has `settings.view`: "Last
  database snapshot: today at 03:10 · 4.2 MB", and the same line as a warning when the newest
  file is more than two days old or there is none. Nothing is recorded in the database — the
  line is the newest file in the directory, and a task that failed is the file that is not
  there. `WxBackupNote`, fed from a new `backup` key in the manifest.
