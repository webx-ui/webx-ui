---
'@webx-ui/php': minor
---

Site modules in the skeleton and in `webx:doctor`: `php/site` builds single-file components with `@vitejs/plugin-vue`, copies `modules/` into the front-end stage of its `Dockerfile` before `npm ci` (`**/node_modules` in `.dockerignore`) and runs `modules/*/tests` as the `Modules` suite. The new `SiteModules` check fails a site module with `.vue` files when the site's Vite has no Vue plugin, warns when the `Dockerfile` installs npm packages without `modules/` (sites set up earlier mend it by hand) and fails a site module whose id belongs to a module of the catalogue or an installed package. `PackageRegistry::directory()` gives the folder a package was installed into.
