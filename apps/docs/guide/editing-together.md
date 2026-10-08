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

The revision is a hash of the content, and some changes leave the content as it was. The heartbeat
says those too:

| Field    | What it carries                                                                                                                                                       |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `state`  | `status`, `has_draft`, `published_at`, `trashed`, `deleted_at`: the badge follows when somebody else publishes or unpublishes                                         |
| `place`  | where the record sits, for one that can be moved: a page's `parent_id`, `position` and `paths` per language                                                           |
| `events` | the last few things done to the record: `published`, `unpublished`, `discarded`, `restored_version`, `moved`, `trashed`, `restored`, each with who, the door and when |

When `state` or `place` changes, or a new event comes in, the editor reads what surrounds the form
again — the status, the trail, «Inside», the address in Settings — and leaves the form alone. The
events are said in one line, the same person's run in one sentence: «Owner published · 16:40»,
«Administrator, through an agent restored version 21 and published». An old version put back is
said that way in the notice of incoming changes too, rather than as a list of every field it
touched. What this editor did itself is not news to it; the same person through an agent is.

**Publishing.** Before publishing, the editor reads the record once more. When the draft on the
server holds somebody else's change this editor never pulled in, it asks — «Publish changes you
have not seen?», with who changed which places and when — and offers «Publish with them» or
«Review first», which pulls them into the form. The publication carries the revision the person
agreed to: `POST …/publish` with `{ revision }` answers 409 with `changed` when the draft moved on
in the meantime, and publishes nothing.

**The bin.** A record put in the bin stays readable to the heartbeat. The editor says who put it
there and when, keeps the form as it is so nothing typed is lost, and offers «Restore» — «Restore
and save my changes» when the form holds unsaved ones — to whoever may restore it. A save or a
publication that finds it gone hands over to that notice rather than a vague error.

**Where the record sits is never a draft value.** A move is applied at once and on its own; a form
left open on a page somebody moved meanwhile cannot move it back by saving.

Fields in notices and in the list of drafts are named the way the form names them: a record's own
fields by its screen, a block and its fields by the block type («Hero › Text below the button ·
EN», not `below_cta`).

## Drafts written over are kept

Every save of a draft leaves an autosave, a ring of the last few copies. When a save replaces a
draft written by somebody else — another author, or the same one through another door — the
replaced draft is also kept as an `overwritten` version, in a ring of its own
(`webx-admin.versions.overwritten`, 10 by default) that a publication does not clear. Each editor's
History tab lists both under «Drafts», with who wrote each copy and through which door, what it
changed against the copy before it («Hero › Eyebrow · EN») so the lost edit can be found, and
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
- a record has one revision, whichever tool reads it: `pages_get` and `blocks_get_content` answer
  the same one for a page, and either may be sent to `pages_update` or `blocks_edit_content`.
- what changes a record's state rather than its content — `*_publish`, `*_unpublish`,
  `*_discard`, `*_version_restore`, `*_move`, `*_delete`, a vacancy's close and reopen — takes the
  `revision` too. A stale one is refused; while somebody has the record open in the panel, one is
  required, and the refusal names who it is. `force: true` goes ahead regardless. With nobody in
  the panel the call goes through without one, as before.
- `pages_versions` lists the draft copies under `drafts`, each with `paths` — what it changed —
  and `pages_version_restore` takes `draft` (an id from that list) instead of a version `number`.

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

Find the record with `withTrashed()` when the model has a bin, so the editor hears that it went
there; a record that can move passes a fifth argument, `fn (Model $course): array` with where it
sits. Publishing, the bin and a version put back are noted by `HasDraft` and `HasVersions`
themselves; a move by `nested-set`.

Add `'changed' => LastChange::of($course, $request->user())` to the `409` body, and in the MCP
tools use `AgentRevision::properties('courses_get')` for the schema and
`AgentRevision::check($arguments, $current, 'course', 'courses_get')` before writing. A tool that
changes the state takes `AgentRevision::stateProperties('courses_get')` and calls
`AgentRevision::guard($arguments, $current, $course, 'course', 'courses_get')`; the panel's publish
action starts with `HeldRevision::conflict($request, 'courses', (string) $course->getKey())`.

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
  refresh: () => reloadRow(), // the status, the trail — not the form
  restore: () => api.restore(course.value!.id), // out of the bin
  busy: () => saving.value,
  screen: 'courses.form',
})

// on load and after each save:   editing.opened(values)
// on a 409:                       if (editing.refused({ values, revision, changed })) save()
// a failed save or publication:   if (!(await editing.failed(error))) toast.danger(...)
// publishing:                     const held = await editing.beforePublish()
//                                 if (held) await api.publish(id, held.revision)  // ask first unless held.asked
```

```vue
<wx-editing-alerts :editing="editing" @save="save" @theirs="reload" />
<!-- in the History tab -->
<wx-drafts
  entity="courses"
  screen="courses.form"
  :id="course.id"
  :can-restore="canManage"
  @restored="reload"
/>
```
