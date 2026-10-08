# Editing together

A drafted record — a page, a layout region, a service, a recipe, an event, an article, a vacancy —
is often edited by more than one hand at once: two people in the panel, or a person in the panel
and an agent over MCP. The panel's rule is that neither loses work to the other without being
asked, and that being asked is the exception.

## What the editor does

Every save carries the `revision` the editor read. When somebody else saved in between, the server
refuses it with a `409` and the record as it now is, plus who changed it last and through which
door (`changed: { author, source, at }`, where `source` is `panel`, `mcp` or `import`).

The editor then holds three versions — what it opened with, what is in the form, and what the
record is now — and merges them:

- by field, and inside a localized field by language: one person writing the English title and
  another the German one do not collide;
- by block key, field and language inside the content: an agent setting the Hero's eyebrow and an
  editor rewriting its heading are two edits, not a conflict;
- adding, removing and moving blocks merge by key; the order of whichever side moved something
  wins.

When nothing overlaps, the merge is saved against the new revision at once and a short notice says
whose changes it took in («Merged with the changes by Anna, through an agent»). Only a place both
sides changed — or a block one side removed while the other edited it — is put to the person: the
banner lists each such place with what it was, what this editor wrote and what the other side
wrote, and the person picks per place. «Save my choices» never drops the other side's changes to
any other place.

## While the editor is open

The editor sends a heartbeat every 20 seconds to `POST /api/cms/editing/{entity}/{id}`. The answer
is the record's current revision, its last change and who else has it open. A revision that moved
under the editor is shown before the editor's next save — «Administrator, through an agent changed
Hero › Eyebrow · EN» with «Pull in» — so the person merges it in rather than finding out on a 409.
Closing the editor sends `DELETE` to the same address.

## Drafts written over are kept

Every save of a draft leaves an autosave, a ring of the last few copies. When a save replaces a
draft written by somebody else — another author, or the same one through another door — the
replaced draft is also kept as an `overwritten` version, in a ring of its own
(`webx-admin.versions.overwritten`, 10 by default) that a publication does not clear. Each editor's
History tab lists both under «Drafts», with who wrote each copy and through which door, and
«Restore» puts one back into the draft.

```
GET  /api/cms/editing/{entity}/{id}/drafts                      autosaves and written-over drafts
POST /api/cms/editing/{entity}/{id}/drafts/{version}/restore    one of them becomes the draft
```

## The agent's side

Over MCP the rule is stricter than in the panel, because an agent writes while people are typing:

- a write to a drafted record names the `revision` its read returned (`pages_get`,
  `blocks_get_content`, `services_get`, …). A write with none is refused with a hint to read
  first, and so is a stale one. `force: true` writes without one and is for a script that means
  to overwrite; a dry run needs neither.
- the reads answer `being_edited_by`: who has the record open in the panel right now, by the same
  heartbeat. An agent tells its user before writing under somebody.
- `pages_versions` lists the draft copies under `drafts`, and `pages_version_restore` takes
  `draft` (an id from that list) instead of a version `number`.

The panel's own save may still leave the revision out — an import, a script — and is let through:
there is no editor on the other side of it to surprise.

## In a module of your own

A drafted record joins in on both halves.

On the server, register it so the heartbeat and the drafts list can find it:

```php
use WebxUi\Admin\Editing\EditedRecords;

$this->app->make(EditedRecords::class)->register(
    'courses',                              // the name the editor uses
    ['courses.view', 'courses.manage'],     // who may read it
    fn (string $id): ?array => ($course = Course::find($id))
        ? ['revision' => Revision::of($course), 'model' => $course]
        : null,
    'courses.manage',                       // who may restore a draft
);
```

Add `'changed' => LastChange::of($course, $request->user())` to the `409` body, and in the MCP
tools use `AgentRevision::properties('courses_get')` for the schema and
`AgentRevision::check($arguments, $current, 'course', 'courses_get')` before writing.

In the editor, `useEditing()` from `@webx-ui/module-admin` does the rest, and `WxEditingAlerts`
draws it:

```ts
const editing = useEditing({
  entity: 'courses',
  id: () => course.value?.id,
  values,
  revision,
  read: async () => {
    const detail = await api.get(id.value)
    return { values: detail.values, revision: detail.revision }
  },
  adopt: (theirs) => (snapshot.value = JSON.stringify(theirs.values)),
  busy: () => saving.value,
})

// on load and after each save:   editing.opened(values)
// on a 409:                       if (editing.refused({ values, revision, changed })) save()
```

```vue
<wx-editing-alerts :editing="editing" @save="save" @theirs="reload" />
<!-- in the History tab -->
<wx-drafts entity="courses" :id="course.id" :can-restore="canManage" @restored="reload" />
```
