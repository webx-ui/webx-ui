# @webx-ui/php

## 0.7.0

### Minor Changes

- 760d372: `webx-ui/module-media` gains its folder endpoints: the tree in one answer, create, rename, move,
  and a delete that refuses a folder with anything in it until it is asked again with `force`. The
  refusal carries the counts, because the panel has a real question to put to the person: pictures
  already placed in articles will stop opening.
- a7fd824: `webx-ui/module-media` gains image editing and its MCP tools. An edit is written over the same
  key, so every address already in an article keeps working, and the picture as it arrived is kept
  once so any edit can be undone. The tools cover the library the way an agent would use it —
  except deleting a folder, which is deliberately absent.
- de2b3a4: `webx-ui/module-media` gains its file endpoints and its previews: a paginated, searchable,
  filterable listing, multi-file upload, rename, batch move, batch delete, and a thumbnail
  endpoint that cuts a variant once and then redirects to it so the disk — or the CDN in front of
  it — serves the grid instead of PHP.
- 47cc998: The file manager after an hour with it: icon actions instead of labelled buttons, filters behind
  popovers, folders in a drawer on a phone, case-insensitive search in any alphabet, previews that
  change when a picture is edited, and a copy-the-link that says whether it worked.

  `@webx-ui/admin` gains the toaster the panel never had — until now every `toast()` from every
  module reported into silence.

- 5ccdccc: New package `webx-ui/module-media`: the panel's file manager. This is its first step — the
  section registers itself, carries its configuration and its ten languages, and declares the
  permissions the rest of the module will be built against. Folders, files and the endpoints
  around them follow.
- 377e894: `webx-ui/module-media` gains its schema and its storage: folders as a nested set, files that
  belong to one, and the service that puts bytes on a disk and takes them off it. Keys are built
  from a uuid and say nothing about the folder, so the same picture in two folders is two keys and
  moving a file between folders never touches the bytes.
- 47cc998: A second pass over the file manager, from using it: upload refusals written in extensions rather
  than a paragraph of mime types, a status bar under the grid instead of a toolbar that grows a
  line, folders created through a dialog rather than a `prompt` the browser may refuse, the page in
  a card, and the image editor cropping what the person actually framed.

  `@webx-ui/core`: `WxFileCard` falls back to the old clipboard when the modern one refuses, so the
  green tick appears wherever the copy actually worked.

### Patch Changes

- 47cc998: New package `@webx-ui/module-media`: the file manager as a section of the panel, a picker that
  opens from code, and a form field that keeps `{ path, alt, title }` on the entity rather than on
  the file. Batch deletion moved to `POST files/delete` on the server, because the panel's own HTTP
  client sends no body on `DELETE`.
- 47cc998: Three things the file manager got wrong in Russian: the dialogs' buttons stood shoulder to
  shoulder, the confirm button read `manager.save` because nobody had shipped the key, and an
  upload refusal came back in English — the uploader is a bare `XMLHttpRequest` and was the one
  request in the panel that never said which language it was drawn in.

## 0.6.0

### Minor Changes

- a9240d6: Seven more languages in the panel: German, Polish, French, Spanish, Italian, Portuguese and
  Turkish, alongside the English, Russian and Ukrainian that were already there. A site still
  decides which of them to offer in `config('webx-localization.panel')`.

  A test in each package holds the ten key sets together — a missing line falls back to English
  rather than to a key, which is right and also the reason a gap can sit unnoticed.

## 0.5.0

### Minor Changes

- 78d8ef3: The panel answers in the language of whoever is reading it. `webx-ui/admin` serves the language
  list and the interface dictionary — both public, because the sign-in screen is drawn before
  there is a session to ask — and carries `locale`, `locales` and `panelLocales` in the manifest.
  `webx-ui/module-auth` stores each administrator's choice on the administrator, so it follows
  them to the next machine and so validation messages arrive in the same language as the labels
  above them.

  Both packages ship English, Russian and Ukrainian. A site adds a language they never shipped by
  publishing their `lang` files and translating what is missing; the merge is per line, so an
  untranslated key falls back on its own rather than taking its screen with it.

- 78d8ef3: `webx-ui/localization` — the languages a site is published in, translated Eloquent attributes,
  and the dictionary the admin panel is drawn from.

  It keeps two things apart that are easy to run together. Interface phrases are written by
  whoever wrote the module, change at deploy, and live in the package's `lang` files; content is
  written by whoever runs the site, changes all day, and lives in the database. One store for
  each, and neither knows about the other.

  A model names its translatable columns and goes on being a model — the value is a JSON language
  map, readable by anything that understands `spatie/laravel-translatable`. The panel's own words
  come from the same `lang` files the server reads, so a module is translated once rather than
  once per half.

### Patch Changes

- 9c0a762: The panel's sidebar toggle has a translated label: `webx-admin::nav.collapse`, in English,
  Russian and Ukrainian. Without it that one control fell back to the English the npm package
  carries, which is a small thing that looks exactly like a broken translation.

## 0.4.0

### Minor Changes

- fd98bdd: `webx:panel` wires the panel's front end into the application that hosts it: it writes the
  entry file, adds it to the Vite inputs, points `webx-admin.vite` at it, and names the npm
  packages to install. Where it cannot recognise a Vite configuration it says which line to add
  rather than rewriting a build it does not understand.
- 7fa1539: `webx-ui/admin` can load the panel's own assets. `webx-admin.assets` names the built files, or
  `webx-admin.vite` names entry points for an application that builds the panel with Laravel's
  own Vite. With neither, the shell stays deliberately blank — the frame installed and the panel
  not is a real state, and it should look like one.

### Patch Changes

- 48c6e34: The entry `webx:panel` writes now imports the stylesheets. The packages ship compiled CSS that
  nothing imports on its own, so the panel built from the previous stub ran perfectly and looked
  like an unstyled form.

## 0.3.1

### Patch Changes

- b193bdf: `webx:admin` accepts the password in `WEBX_ADMIN_PASSWORD` when there is nobody to ask, so a
  provisioning script or a container entrypoint can create the first administrator. Still no
  `--password` option: an argument lands in the shell history and in the process list.

## 0.3.0

### Minor Changes

- 18d9a7e: `webx-ui/module-auth`: administrators, roles and sign-in. Installing it is what closes the
  panel — until now `webx-ui/admin` served its API to anyone. Administrators live in their own
  table behind their own guard, roles grant the permissions modules declare in the manifest, and
  every sign-in attempt is written down. Its MCP tools can read who has what and move people
  between roles, and deliberately cannot touch a password or mint a token.

## 0.2.0

### Minor Changes

- cc9d156: Two packages the admin panel is built from. `webx-ui/admin` carries the module contract, the
  registry, the manifest the front end reads before it draws anything, and the catch-all that
  keeps a deep link from 404ing. `webx-ui/mcp` carries the contract by which a module offers
  itself to an AI agent — a mutating tool is given `dry_run` and a write scope whether its author
  remembered them or not.

## 0.1.0

### Minor Changes

- 219fd59: First release of the Composer packages. `webx-ui/nested-set` brings nested set trees to
  Eloquent: subtree reads in one query, placement and moves that keep the bounds consistent,
  `toTree` for handing a whole tree to the front end, and `fixTree` / `checkTreeIntegrity` for
  when something has gone wrong anyway.
