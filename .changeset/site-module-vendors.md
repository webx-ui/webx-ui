---
'@webx-ui/php': minor
---

Site modules are ours: `WebxUi\Admin\Panel\Vendors` is the one place that knows the vendors of our packages (`webx-ui/`, and `site/` for the modules a site keeps in its own `modules/`), and every tool that tells ours from somebody else's asks it. The site's `AGENTS.md` lists the site's modules apart, linked to `modules/<x>/AGENTS.md`; `webx:theme:sync` takes the base look of `site/module-*`; `webx:snapshot` writes the versions of `site/*`; the debug bar names a site module as the source of a query; `webx:modules` has a «Local» column (`local` in `--json`). `Testing\InstalledPackages` adds an `installed.json` fixture's packages to `InstalledVersions` for a test.
