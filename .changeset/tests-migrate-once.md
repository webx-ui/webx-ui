---
'@webx-ui/php': patch
---

`module-admin`: `Testing\MigratesOnce` — a Testbench case on sqlite `:memory:` runs `artisan migrate`
once per schema (its migration files and the config) and gets it replayed into every next test: the
same fresh database per test, a few milliseconds instead of 20–50.

`Testing\TemporaryFiles` — `temporaryFile($prefix, $extension)` in a Testbench case: a file in the
system's temporary directory that goes when the test does.
