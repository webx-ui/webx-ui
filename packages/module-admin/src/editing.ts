import { onBeforeUnmount, onMounted, ref, shallowRef, watch, type Ref, type ShallowRef } from 'vue'
import { toast, useLocales } from '@webx-ui/core'
import { useAdmin } from './admin'
import { useTranslate } from './i18n'
import {
  changedPaths,
  mergeThreeWay,
  sameValue,
  type MergeChoice,
  type MergeConflict,
  type MergeSegment,
} from './merge'

/** Who wrote a record last, through which door, and when — the server's `changed`. */
export interface EditingChange {
  author: string | null
  author_id: number | null
  /** `panel`, `mcp` (an agent) or `import`. */
  source: string
  at: string | null
}

/** Somebody else who has the record open in the panel right now. */
export interface EditingEditor {
  id: number
  name: string
  since: string
  seen_at: string
}

/** One copy of the draft, as `editing/{entity}/{id}/drafts` lists it. */
export interface DraftCopy {
  id: number
  kind: 'autosave' | 'overwritten'
  created_at: string | null
  author: string | null
  source: string
  fields: string[]
}

/** The record as the server has it now. */
export interface EditingVersion<T> {
  values: T
  revision: string
  changed?: EditingChange | null
}

export interface EditingConflict<T> {
  /** The places both sides changed, each with its three values. */
  conflicts: MergeConflict[]
  /** The side each one is settled to; this editor's until somebody picks. */
  choices: Record<string, MergeChoice>
  theirs: EditingVersion<T>
}

/** Somebody else saved while this editor is open, and it has not been pulled in yet. */
export interface EditingIncoming<T> {
  theirs: EditingVersion<T>
  paths: MergeSegment[][]
}

export interface EditingOptions<T> {
  /** The record's name on the server's `editing/{entity}/{id}`: `pages`, `services`, `regions`. */
  entity: string
  id: () => string | number | null | undefined
  /** The form: what this editor has now. */
  values: Ref<T>
  /** The revision the form was read at; a merge moves it to the one it merged with. */
  revision: Ref<string>
  /** Read the record again, for the notice's «Pull in». */
  read: () => Promise<EditingVersion<T>>
  /**
   * The server's version was taken in as the new base — after a merge, a pull or «Take theirs».
   * The editor moves its own idea of "saved" there: whatever differs from it now is unsaved.
   */
  adopt?: (theirs: EditingVersion<T>) => void
  /** Whether this editor writes at all; a reader is not asked about anybody's changes. */
  canWrite?: () => boolean
  /** Whether a save is on its way: a heartbeat answered meanwhile may be reporting it. */
  busy?: () => boolean
  /** What a top-level field is called on screen, for the conflict list. */
  field?: (name: string) => string | undefined
  /** What a type of block is called. */
  block?: (type: string) => string | undefined
  /** Seconds between heartbeats; `false` for none (a test, a screen that is not an editor). */
  heartbeat?: number | false
}

export interface Editing<T> {
  /** What the form was opened with, or last saved — the common ancestor of a merge. */
  base: ShallowRef<T>
  conflict: Ref<EditingConflict<T> | null>
  incoming: Ref<EditingIncoming<T> | null>
  editors: Ref<EditingEditor[]>
  /** Call with what the form now matches on the server: on opening, and after each save. */
  opened(values: T): void
  /**
   * A save was refused because the record moved. Merges it with what is in the form: `true`
   * when nothing overlapped — the form holds both edits and the editor saves it again — and
   * `false` when a conflict is waiting for the person.
   */
  refused(theirs: EditingVersion<T>): boolean
  /** Pull the other side's save in before saving. Same answer as `refused`. */
  pull(): Promise<boolean>
  choose(id: string, choice: MergeChoice): void
  /** Settle the conflict by the choices; the editor saves next. */
  resolve(): void
  /** Settle every conflict their way at once. */
  resolveTheirs(): void
  /** A place in the record as the person reads it: «Hero › Heading · EN». */
  label(path: MergeSegment[]): string
  /** A value, short, as text. */
  preview(value: unknown): string
  /** Who changed it: «Anna», «Anna, through an agent». */
  who(changed: EditingChange | null | undefined): string
}

/** Heartbeat answer. */
interface Ping {
  revision: string
  changed: EditingChange | null
  editors: EditingEditor[]
  heartbeat: number
}

/**
 * What every drafted editor does about other people editing the same record (§ «Two editors»):
 * merges a refused save instead of asking, asks only about a field both sides changed, says who
 * the other side was and through which door, and keeps an ear on the server while open — who
 * else has the record, and whether somebody saved in the meantime.
 *
 * The editor keeps its own save loop; this is the part that is the same in all of them.
 */
export function useEditing<T>(options: EditingOptions<T>): Editing<T> {
  const admin = useAdmin()
  const t = useTranslate('webx-admin')
  const locales = useLocales()

  const base = shallowRef<T>(clone(options.values.value))
  const conflict = ref<EditingConflict<T> | null>(null) as Ref<EditingConflict<T> | null>
  const incoming = ref<EditingIncoming<T> | null>(null) as Ref<EditingIncoming<T> | null>
  const editors = ref<EditingEditor[]>([])

  const canWrite = (): boolean => options.canWrite?.() ?? true

  function opened(values: T): void {
    base.value = clone(values)
    incoming.value = null
  }

  /** Take the server's version in as the base, with the form holding `merged`. */
  function take(theirs: EditingVersion<T>, merged: T): void {
    options.revision.value = theirs.revision
    base.value = clone(theirs.values)
    options.adopt?.(theirs)
    options.values.value = clone(merged)
    incoming.value = null
  }

  function settleWith(theirs: EditingVersion<T>): boolean {
    const { value, conflicts } = mergeThreeWay(base.value, options.values.value, theirs.values)

    if (conflicts.length === 0) {
      take(theirs, value)
      conflict.value = null

      return true
    }

    conflict.value = { conflicts, choices: {}, theirs }
    incoming.value = null

    return false
  }

  function refused(theirs: EditingVersion<T>): boolean {
    const clean = settleWith(theirs)

    if (clean) toast.info(t('editing.merged', { who: who(theirs.changed) }))

    return clean
  }

  async function pull(): Promise<boolean> {
    const theirs = incoming.value?.theirs ?? (await options.read())

    return settleWith(theirs)
  }

  function choose(id: string, choice: MergeChoice): void {
    if (conflict.value)
      conflict.value = { ...conflict.value, choices: { ...conflict.value.choices, [id]: choice } }
  }

  function resolve(): void {
    const open = conflict.value

    if (!open) return

    const { value } = mergeThreeWay(
      base.value,
      options.values.value,
      open.theirs.values,
      open.choices,
    )

    take(open.theirs, value)
    conflict.value = null
  }

  function resolveTheirs(): void {
    const open = conflict.value

    if (!open) return

    open.conflicts.forEach((one) => choose(one.id, 'theirs'))
    resolve()
  }

  function label(path: MergeSegment[]): string {
    const codes = new Set(locales.list.value.map((locale) => locale.code))
    const parts: string[] = []
    let language = ''

    path.forEach((step, index) => {
      if ('block' in step) {
        parts.push(options.block?.(step.type) ?? humanize(step.type || t('editing.block')))

        return
      }

      // A block's fields sit under `values`, which is the shape of a node and not a word anybody
      // would recognise.
      if (step.field === 'values' && index > 0 && 'block' in path[index - 1]!) return

      // Nor is the field that holds a list of blocks: «Hero» says where it is, «Blocks › Hero»
      // only says it twice.
      const next = path[index + 1]

      if (next !== undefined && 'block' in next) return

      if (index === path.length - 1 && index > 0 && codes.has(step.field)) {
        language = step.field.toUpperCase()

        return
      }

      parts.push((index === 0 ? options.field?.(step.field) : undefined) ?? humanize(step.field))
    })

    const named = parts.length === 0 ? t('editing.order') : parts.join(' › ')

    return language === '' ? named : `${named} · ${language}`
  }

  function who(changed: EditingChange | null | undefined): string {
    const name = changed?.author ?? t('editing.somebody')

    if (changed?.source === 'mcp') return t('editing.via-agent', { name })
    if (changed?.source === 'import') return t('editing.via-import', { name })

    return name
  }

  /* The heartbeat. */
  let timer: ReturnType<typeof setInterval> | undefined
  let reading = false
  let stopWatching: (() => void) | undefined

  const address = (): string | null => {
    const id = options.id()

    return id === null || id === undefined || id === ''
      ? null
      : `${admin.apiPath}/editing/${options.entity}/${encodeURIComponent(String(id))}`
  }

  async function beat(): Promise<void> {
    const url = address()

    if (url === null) return

    const held = options.revision.value
    let ping: Ping

    try {
      ping = (await admin.http.post<{ data: Ping }>(url)).data
    } catch {
      // A record nobody has saved yet, a panel without the endpoint, a network that blinked:
      // the heartbeat is a courtesy, and its absence is not worth a word on screen.
      return
    }

    if (typeof ping?.revision !== 'string') return

    editors.value = Array.isArray(ping.editors) ? ping.editors : []

    // Our own save may have landed while the heartbeat was out, and a stale answer would then
    // report it as somebody else's.
    if (held !== options.revision.value || options.busy?.()) return
    if (ping.revision === held || !canWrite() || conflict.value || reading) return
    if (incoming.value?.theirs.revision === ping.revision) return

    reading = true

    try {
      const theirs = await options.read()

      if (held !== options.revision.value || options.busy?.()) return

      theirs.changed ??= ping.changed

      if (sameValue(theirs.values, options.values.value)) {
        // The server already holds what the form holds — nothing to pull, only a revision.
        take(theirs, options.values.value)

        return
      }

      incoming.value = { theirs, paths: changedPaths(base.value, theirs.values) }
    } catch {
      // As above: the notice is offered when it can be, never insisted on.
    } finally {
      reading = false
    }
  }

  function leave(): void {
    const url = address()

    if (url === null) return

    try {
      void admin.http.delete(url).catch(() => undefined)
    } catch {
      // A hand-made client without `delete`: the entry runs out on its own within a minute.
    }
  }

  onMounted(() => {
    if (options.heartbeat === false) return

    const seconds = options.heartbeat ?? 20

    timer = setInterval(() => void beat(), seconds * 1000)
    // The first one as soon as the record is known: an agent asking a second after the page
    // opened should hear about it, and so should the next record the same screen opens.
    stopWatching = watch(options.id, () => void beat(), { immediate: true })
  })

  onBeforeUnmount(() => {
    clearInterval(timer)
    stopWatching?.()
    if (options.heartbeat !== false) leave()
  })

  return {
    base,
    conflict,
    incoming,
    editors,
    opened,
    refused,
    pull,
    choose,
    resolve,
    resolveTheirs,
    label,
    preview,
    who,
  }
}

/** A value, short, as text: the words of a rich text without its tags, a block by its type. */
export function preview(value: unknown): string {
  if (value === undefined || value === null || value === '') return ''
  if (typeof value === 'string')
    return clip(
      value
        .replace(/<[^>]*>/g, '')
        .replace(/\s+/g, ' ')
        .trim(),
    )
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)

  if (
    typeof value === 'object' &&
    !Array.isArray(value) &&
    typeof (value as { type?: unknown }).type === 'string'
  ) {
    return humanize((value as { type: string }).type)
  }

  return clip(JSON.stringify(value))
}

function clip(text: string): string {
  return text.length > 140 ? `${text.slice(0, 139)}…` : text
}

function humanize(name: string): string {
  const words = name.replace(/[_-]+/g, ' ').trim()

  return words.charAt(0).toUpperCase() + words.slice(1)
}

function clone<T>(value: T): T {
  return value === undefined ? value : (JSON.parse(JSON.stringify(value)) as T)
}
