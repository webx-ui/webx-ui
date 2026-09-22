---
'@webx-ui/php': minor
---

A new site is one command: the `webx-ui/site` skeleton and `php artisan webx:setup`

`composer create-project webx-ui/site example.local` now leaves a Laravel application with the
panel on it, the modules that were asked for, a database that did not exist a minute ago, an
administrator and something to look at. The skeleton lives in `php/site` and mirrors to
`webx-ui/site` the way the packages mirror to theirs.

`webx:setup` asks the questions with defaults read off the directory, the `.env` and `git
config`, writes the `.env` by replacing rather than appending, creates the database over PDO,
installs the chosen modules, wires the panel in through `webx:panel --sync`, migrates, seeds the
languages, creates the first administrator, builds the front end and seeds the demo content. It
is the same command on a site that has been running for months: run it again after installing a
module and it adds what is missing and changes nothing else.
