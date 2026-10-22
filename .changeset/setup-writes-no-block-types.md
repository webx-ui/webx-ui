---
'@webx-ui/php': minor
---

`webx:setup` no longer writes block types or seeds the demo: the hosting platform makes staging and production sites with it, and a block type reaches those only by a person's import. It used to run `webx:blocks:import --theme --publish`, `webx:blocks:offered --install` and `webx:demo`; now it prints them at the end, in that order, for a dev copy, with the warning about languages the demo is not written in. `--demo` and `--no-demo` are still accepted and change nothing (`--demo` says so). Scripts that relied on setup for the blocks or the demo run the three commands after it.
