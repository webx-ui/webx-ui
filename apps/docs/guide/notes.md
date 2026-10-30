# Notes on a record

A note is what one administrator writes on a record for whoever picks it up next: «rang back, no
answer», «deliver after six». The panel draws notes the same way wherever they are — a submission
of the inbox, an order — and that look lives in `@webx-ui/module-admin`, so a section does not grow
its own copy.

There are two components, because there are two ways to keep notes:

| Component     | Keeps the notes                                                | Use it when                                                      |
| ------------- | -------------------------------------------------------------- | ---------------------------------------------------------------- |
| `WxNotes`     | in the panel's table `cms_notes`, through its own address      | the record takes `HasNotes` — notes its author edits and removes |
| `WxNotesList` | nowhere: the notes come in a prop, a new one goes out as `add` | the section keeps them itself — an order, in its history         |

Both draw the same thing: each note with its author's photograph or initials, the name and the time
(`WxDate`), the text as typed with its line breaks and no markup; «No notes yet.» when there are
none; the box «A note for whoever picks this up next…» and «Add a note» under the feed. On a narrow
card the button takes the width — the decision is the feed's own width, not the window's.

To see both working, open the playground's panel (`/panel/`): a submission of the inbox has
`WxNotes` on its tab «Notes», the order WX-1001 has `WxNotesList` under its tabs.

## `WxNotes` — the panel keeps them

The model takes the trait, declares the interface and names the permission its notes are behind;
the component only says which record it is about.

```vue
<wx-notes :id="submission.id" type="inbox_submission" :can="canUpdate" @change="reload" />
```

| Prop    | Type               | Default | What                                                   |
| ------- | ------------------ | ------- | ------------------------------------------------------ |
| `type`  | `string`           | —       | the alias the server registered the model under        |
| `id`    | `number \| string` | —       | the record                                             |
| `can`   | `boolean`          | `true`  | whether the reader may write, and edit their own       |
| `title` | `string`           | «Notes» | the heading; `''` for none, when a tab already says it |

`change` fires after a note is written, changed or removed — a log beside the feed redraws.

## `WxNotesList` — the section keeps them

The component never asks a server anything. The section hands it the notes, listens for `add`, keeps
the note its own way and empties the box once it is kept — so a note the server refused is still in
the box to try again.

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { WxNotesList, type NoteEntry } from '@webx-ui/module-admin'

const notes = ref<NoteEntry[]>([])
const draft = ref('')
const sending = ref(false)

async function add(body: string) {
  sending.value = true
  try {
    notes.value = await api.note(order.id, body)
    draft.value = ''
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <wx-notes-list
    v-model:draft="draft"
    :notes="notes"
    :can="canManage"
    :sending="sending"
    :maxlength="1000"
    @add="add"
  />
</template>
```

| Prop        | Type          | Default | What                                                                       |
| ----------- | ------------- | ------- | -------------------------------------------------------------------------- |
| `notes`     | `NoteEntry[]` | —       | `{ id, body, author: { id, name, avatar } \| null, is_mine?, created_at }` |
| `can`       | `boolean`     | `true`  | whether the box is drawn                                                   |
| `editable`  | `boolean`     | `false` | whether a note changes once written: the author's own get a menu           |
| `loading`   | `boolean`     | `false` | a skeleton instead of the feed                                             |
| `sending`   | `boolean`     | `false` | the button spins, and a second note is not sent                            |
| `title`     | `string`      | «Notes» | the heading; `''` for none                                                 |
| `maxlength` | `number`      | —       | the longest note the store takes                                           |

| Model     | What                                                               |
| --------- | ------------------------------------------------------------------ |
| `draft`   | what is being typed; the section empties it once the note is kept  |
| `editing` | the id of the note open for editing; the section closes it on save |

| Event    | Payload        | When                                                           |
| -------- | -------------- | -------------------------------------------------------------- |
| `add`    | `body`         | «Add a note», the text trimmed; never blank                    |
| `save`   | `note`, `body` | «Save» on a note open for editing (`editable`)                 |
| `remove` | `note`         | «Delete» in its menu (`editable`); confirming is the section's |

The author's name is the section's to give: an order names a note an agent wrote «Agent: Ada», and
one a module wrote by the module's name, and passes that as `author.name`.
