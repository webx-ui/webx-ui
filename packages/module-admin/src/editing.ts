import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  shallowRef,
  watch,
  type ComputedRef,
  type Ref,
  type ShallowRef,
} from 'vue'
import { confirm, toast } from '@webx-ui/core'
import { useAdmin } from './admin'
import { useDates } from './dates'
import { humanize, useEditingLabels } from './editingLabels'
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

/** Where the record stands, as the heartbeat reports it. */
export interface EditingState {
  status: string
  has_draft: boolean
  published_at: string | null
  trashed: boolean
  deleted_at: string | null
}

/**
 * Something that happened to the record besides its content: published, taken off the site, its
 * draft thrown away, an old version put back, moved, put in the bin or taken out of it.
 */
export interface EditingEvent {
  id: number
  kind:
    | 'published'
    | 'unpublished'
    | 'discarded'
    | 'restored_version'
    | 'moved'
    | 'trashed'
    | 'restored'
    | 'purged'
    | string
  author: string | null
  author_id: number | null
  source: string
  at: string
  detail: Record<string, unknown> | null
}

/** One copy of the draft, as `editing/{entity}/{id}/drafts` lists it. */
export interface DraftCopy {
  id: number
  kind: 'autosave' | 'overwritten'
  created_at: string | null
  author: string | null
  source: string
  fields: string[]
  /** What this copy changed against the one before it. */
  paths?: MergeSegment[][]
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
  /**
   * Somebody published, unpublished, moved or restored the record: read what the editor shows
   * around the form again — the status, the trail, the address — without touching the form.
   */
  refresh?: () => void | Promise<void>
  /** Take the record out of the bin. Without it, the notice of the bin offers no way back. */
  restore?: () => Promise<void>
  /** Whether this editor writes at all; a reader is not asked about anybody's changes. */
  canWrite?: () => boolean
  /** Whether a save is on its way: a heartbeat answered meanwhile may be reporting it. */
  busy?: () => boolean
  /** The screen the record is edited on, so its fields are named the way the form names them. */
  screen?: string
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
  /** Where the record stands, by the last heartbeat. */
  state: Ref<EditingState | null>
  /** What others did to the record since this editor opened it, not yet acknowledged. */
  events: Ref<EditingEvent[]>
  /** The record is in the bin. */
  trashed: ComputedRef<boolean>
  /** Who put it there and when, when the server knows. */
  trashedBy: ComputedRef<EditingEvent | null>
  /**
   * The record was deleted for good: who did it and when. Nothing can be saved or restored —
   * the form keeps what it holds, to be copied out with `copyText`.
   */
  gone: Ref<EditingEvent | null>
  /** In the bin or gone: autosave waits, and a save would only fail. */
  stopped: ComputedRef<boolean>
  /** Whether the form holds something the server does not. */
  unsaved: ComputedRef<boolean>
  /** Whether this editor can take the record out of the bin. */
  canRestore: () => boolean
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
  /**
   * Before publishing: whether the draft on the server is still the one this editor holds. When
   * somebody else changed it, the person is told whose changes, where and when, and chooses to
   * publish with them or to review them first — which pulls them into the form. Answers the
   * revision to publish with, and whether the person was already asked; `null` to stop.
   */
  beforePublish(): Promise<{ revision: string; asked: boolean } | null>
  /**
   * A save or a publication failed. `true` when this was about other people — the record is in
   * the bin, or a publication found a draft it had not seen — and a notice says so; the editor
   * then shows nothing of its own.
   */
  failed(error: unknown): Promise<boolean>
  /** Out of the bin. The form keeps what it holds; saving it is the caller's next step. */
  restoreFromBin(): Promise<boolean>
  /** What the form holds, as text a person can paste somewhere else: each field under its name. */
  text(): string
  /** That text onto the clipboard, said with a toast either way. */
  copyText(): Promise<boolean>
  /** The events have been read. */
  dismissEvents(): void
  /** Ask the server now rather than at the next heartbeat. */
  check(): Promise<void>
  /** A place in the record as the person reads it: «Hero › Heading · EN». */
  label(path: MergeSegment[]): string
  /** What a list of changed places says: «Hero › Heading, Title · EN and more». */
  places(paths: MergeSegment[][]): string
  /** What happened, in a sentence: «Owner restored version 21 and published · 16:40». */
  describe(events: EditingEvent[]): string
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
  state?: EditingState | null
  place?: Record<string, unknown> | null
  events?: EditingEvent[]
  heartbeat: number
}

/** Events that change the content: a notice of incoming changes says these instead of fields. */
const EXPLAINING = new Set(['restored_version', 'discarded'])

/**
 * What every drafted editor does about other people editing the same record (§ «Two editors»):
 * merges a refused save instead of asking, asks only about a field both sides changed, says who
 * the other side was and through which door, and keeps an ear on the server while open — who
 * else has the record, whether somebody saved in the meantime, and what they did to it besides
 * saving: published it, moved it, put it in the bin.
 *
 * The editor keeps its own save loop; this is the part that is the same in all of them.
 */
export function useEditing<T>(options: EditingOptions<T>): Editing<T> {
  const admin = useAdmin()
  const t = useTranslate('webx-admin')
  const dates = useDates()
  const labels = useEditingLabels({
    screen: options.screen,
    field: options.field,
    block: options.block,
  })

  const base = shallowRef<T>(clone(options.values.value))
  const conflict = ref<EditingConflict<T> | null>(null) as Ref<EditingConflict<T> | null>
  const incoming = ref<EditingIncoming<T> | null>(null) as Ref<EditingIncoming<T> | null>
  const editors = ref<EditingEditor[]>([])
  const state = ref<EditingState | null>(null)
  const place = ref<Record<string, unknown> | null>(null)
  const events = ref<EditingEvent[]>([])
  /** Every event the server still keeps, this editor's own included: who put it in the bin. */
  const recent = ref<EditingEvent[]>([])
  let changedLast: EditingChange | null = null

  const canWrite = (): boolean => options.canWrite?.() ?? true

  const trashed = computed(() => state.value?.trashed === true)
  const gone = ref<EditingEvent | null>(null)
  const stopped = computed(() => trashed.value || gone.value !== null)
  const trashedBy = computed(
    () => [...recent.value].reverse().find((event) => event.kind === 'trashed') ?? null,
  )
  const unsaved = computed(() => !sameValue(options.values.value, base.value))

  /*
   * Whether the editor has said what it opened. Until it has, its revision and its form are
   * whatever it started with — empty — and a heartbeat answered in that moment would read the
   * record and offer all of it as somebody else's change, or, worse, take the record's revision
   * for one the editor held. A heartbeat before then only says the editor is here.
   */
  let ready = false
  let mounted = false

  function opened(values: T): void {
    base.value = clone(values)
    incoming.value = null

    if (!ready) {
      ready = true

      // The heartbeat that went out before the record was in the form could not compare
      // anything: ask again now, rather than twenty seconds from now.
      if (mounted && options.heartbeat !== false) void beat()
    }
  }

  /** Take the server's version in as the base, with the form holding `merged`. */
  function take(theirs: EditingVersion<T>, merged: T): void {
    options.revision.value = theirs.revision
    base.value = clone(theirs.values)
    options.adopt?.(theirs)
    options.values.value = clone(merged)
    incoming.value = null
    // What explained the change is in the form now — and the notice that explained it said every
    // event it had, so none of them is news any more.
    if (events.value.some((event) => EXPLAINING.has(event.kind))) events.value = []
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

  function who(changed: EditingChange | null | undefined): string {
    const name = changed?.author ?? t('editing.somebody')

    if (changed?.source === 'mcp') return t('editing.via-agent', { name })
    if (changed?.source === 'import') return t('editing.via-import', { name })

    return name
  }

  function when(at: string | null | undefined): string {
    return at ? dates.short(at) : ''
  }

  function places(paths: MergeSegment[][]): string {
    const named = [...new Set(paths.map((path) => labels.label(path)))]
    const what = named.slice(0, 3).join(', ') || t('editing.order')

    return named.length > 3 ? t('editing.and-more', { what }) : what
  }

  /** One thing that happened, as a verb phrase: «restored version 21», «moved it to /contact/». */
  function deed(event: EditingEvent): string {
    const detail = event.detail ?? {}

    switch (event.kind) {
      case 'restored_version':
        return typeof detail.number === 'number'
          ? t('editing.event-restored-version', { number: detail.number })
          : t('editing.event-restored-draft')
      case 'moved': {
        const path = mainPath()

        return path === null ? t('editing.event-moved') : t('editing.event-moved-to', { path })
      }
      default:
        return t(`editing.event-${event.kind.replace(/_/g, '-')}`)
    }
  }

  function describe(list: EditingEvent[]): string {
    const lines: string[] = []
    let i = 0

    // One sentence per person in a row: «Owner restored version 21 and published», not two.
    while (i < list.length) {
      const first = list[i]!
      const deeds: string[] = []
      let last = first

      while (
        i < list.length &&
        list[i]!.author_id === first.author_id &&
        list[i]!.source === first.source
      ) {
        last = list[i]!
        const phrase = deed(last)

        if (deeds.at(-1) !== phrase) deeds.push(phrase)
        i++
      }

      const done =
        deeds.length === 1
          ? deeds[0]!
          : `${deeds.slice(0, -1).join(', ')} ${t('editing.and')} ${deeds.at(-1)}`

      lines.push(timeless(t('editing.event', { who: who(last), what: done, when: when(last.at) })))
    }

    return lines.join(' · ')
  }

  /** The address the record answers at now, in the panel's language or the first it has. */
  function mainPath(): string | null {
    const paths = place.value?.paths

    if (!paths || typeof paths !== 'object') return null

    const map = paths as Record<string, unknown>
    const own = map[admin.i18n?.state?.locale ?? ''] ?? Object.values(map)[0]

    return typeof own === 'string' ? own : null
  }

  async function beforePublish(): Promise<{ revision: string; asked: boolean } | null> {
    const held = options.revision.value
    let theirs: EditingVersion<T>

    try {
      theirs = await options.read()
    } catch {
      // Not readable just now: publish what the editor holds, and the server checks the revision.
      return { revision: held, asked: false }
    }

    if (theirs.revision === held) return { revision: held, asked: false }

    if (sameValue(theirs.values, options.values.value)) {
      take(theirs, options.values.value)

      return { revision: theirs.revision, asked: false }
    }

    theirs.changed ??= changedLast

    const explained = events.value.filter((event) => EXPLAINING.has(event.kind))
    const what =
      explained.length > 0
        ? describe(explained)
        : timeless(
            t('editing.incoming', {
              who: who(theirs.changed),
              what: places(changedPaths(base.value, theirs.values)),
              when: when(theirs.changed?.at),
            }),
          )

    const agreed = await confirm({
      title: t('editing.publish-unseen-title'),
      message: `${what}. ${t('editing.publish-unseen-text')}`,
      confirmText: t('editing.publish-with'),
      cancelText: t('editing.review-first'),
    })

    if (agreed) return { revision: theirs.revision, asked: true }

    // Review first: their changes into the form, beside whatever is there, to be looked at and
    // published with the next press of the button.
    if (settleWith(theirs)) toast.info(t('editing.merged', { who: who(theirs.changed) }))

    return null
  }

  async function failed(error: unknown): Promise<boolean> {
    const failure = error as {
      status?: number
      body?: { message?: string; revision?: unknown; gone?: unknown }
    }

    if (failure?.status === 410 && heardGone(failure.body?.gone)) return true

    if (failure?.status === 404 || failure?.status === 410) {
      await beat()

      return stopped.value
    }

    // A publication that carried a revision the draft is no longer at: somebody wrote between
    // the question and the click. The notice of their change follows from the heartbeat.
    if (failure?.status === 409 && typeof failure.body?.revision === 'string') {
      toast.warning(failure.body.message || t('editing.publish-moved'))
      await beat()

      return true
    }

    return false
  }

  const canRestore = (): boolean => options.restore !== undefined && canWrite()

  async function restoreFromBin(): Promise<boolean> {
    if (!options.restore) return false

    try {
      await options.restore()
    } catch (error) {
      toast.danger(
        (error as { body?: { message?: string } })?.body?.message || t('editing.restore-failed'),
      )

      return false
    }

    // Who put it in the bin was the notice's to say; out of it, that is no longer news.
    events.value = events.value.filter((event) => event.kind !== 'trashed')
    await beat()
    await options.refresh?.()
    toast.success(t('editing.out-of-bin'))

    return true
  }

  function dismissEvents(): void {
    events.value = []
  }

  /**
   * A 410 with the purge in it. Said once and kept: the record will not come back, and every
   * heartbeat after this one would only hear the same.
   */
  function heardGone(event: unknown): boolean {
    if (gone.value !== null) return true
    if (event === undefined) return false

    const purge = (event ?? {}) as Partial<EditingEvent>

    gone.value = {
      id: typeof purge.id === 'number' ? purge.id : 0,
      kind: 'purged',
      author: typeof purge.author === 'string' ? purge.author : null,
      author_id: typeof purge.author_id === 'number' ? purge.author_id : null,
      source: typeof purge.source === 'string' ? purge.source : 'panel',
      at: typeof purge.at === 'string' ? purge.at : '',
      detail: null,
    }
    incoming.value = null
    conflict.value = null
    editors.value = []

    return true
  }

  function text(): string {
    return texts(options.values.value)
      .map(([path, words]) => `${labels.label(path)}\n${words}`)
      .join('\n\n')
  }

  async function copyText(): Promise<boolean> {
    try {
      await navigator.clipboard.writeText(text())
    } catch {
      toast.danger(t('editing.copy-failed'))

      return false
    }

    toast.success(t('editing.copied'))

    return true
  }

  /* The heartbeat. */
  let timer: ReturnType<typeof setInterval> | undefined
  let reading = false
  let stopWatching: (() => void) | undefined
  /** The newest event this editor has heard of; `null` until the first answer sets the line. */
  let seen: number | null = null
  let stateKey: string | null = null

  const address = (): string | null => {
    const id = options.id()

    return id === null || id === undefined || id === ''
      ? null
      : `${admin.apiPath}/editing/${options.entity}/${encodeURIComponent(String(id))}`
  }

  const me = (): number | null => {
    const id = admin.state?.user?.id

    return id === undefined || id === null ? null : Number(id)
  }

  /** What the heartbeat says besides the revision: the state, the place, what happened. */
  function absorb(ping: Ping): void {
    const list = Array.isArray(ping.events) ? ping.events : []
    const newest = list.reduce((top, event) => Math.max(top, event.id), seen ?? 0)
    let fresh: EditingEvent[] = []

    recent.value = list

    if (seen === null) {
      // Whatever happened before this editor opened is not news to it.
      seen = newest
    } else {
      const line = seen

      // This editor's own doing is not news either — but the same person through an agent is.
      fresh = list.filter(
        (event) =>
          event.id > line &&
          !(event.author_id === me() && event.source === 'panel' && me() !== null),
      )
      seen = newest
    }

    if (fresh.length > 0) events.value = [...events.value, ...fresh]

    state.value = ping.state ?? null
    place.value = ping.place ?? null

    const key = JSON.stringify([ping.state ?? null, ping.place ?? null])
    const moved = stateKey !== null && key !== stateKey

    stateKey = key

    // The badge, the trail and the address are the editor's: it reads them again.
    if ((moved || fresh.length > 0) && !trashed.value) void options.refresh?.()
  }

  async function beat(): Promise<void> {
    const url = address()

    if (url === null) return

    const held = options.revision.value
    let ping: Ping

    // Gone is gone: the next record the screen opens starts over (see the watch on the id).
    if (gone.value !== null) return

    try {
      ping = (await admin.http.post<{ data: Ping }>(url)).data
    } catch (error) {
      const failure = error as { status?: number; body?: { gone?: unknown } }

      // Deleted for good is the one silence that is not a courtesy: what is typed here has
      // nowhere to go, and the person has to hear it before they type more.
      if (failure?.status === 410) {
        heardGone(failure.body?.gone ?? null)

        return
      }

      // A record nobody has saved yet, a panel without the endpoint, a network that blinked:
      // the heartbeat is a courtesy, and its absence is not worth a word on screen.
      return
    }

    if (typeof ping?.revision !== 'string') return

    editors.value = Array.isArray(ping.editors) ? ping.editors : []
    changedLast = ping.changed ?? null
    absorb(ping)

    // Nothing to compare with until the editor has its record: see `ready`.
    if (!ready) return

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
    mounted = true

    if (options.heartbeat === false) return

    const seconds = options.heartbeat ?? 20

    timer = setInterval(() => void beat(), seconds * 1000)
    // The first one as soon as the record is known: an agent asking a second after the page
    // opened should hear about it, and so should the next record the same screen opens.
    stopWatching = watch(
      options.id,
      () => {
        // Another record: what was heard about the last one is not about this one.
        seen = null
        stateKey = null
        events.value = []
        gone.value = null
        void beat()
      },
      { immediate: true },
    )
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
    state,
    events,
    trashed,
    trashedBy,
    gone,
    stopped,
    unsaved,
    canRestore,
    opened,
    refused,
    pull,
    choose,
    resolve,
    resolveTheirs,
    beforePublish,
    failed,
    restoreFromBin,
    text,
    copyText,
    dismissEvents,
    check: beat,
    label: labels.label,
    places,
    describe,
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

/**
 * Every piece of text in a value with where it sits — what a person typed, to be carried out of
 * a form whose record is gone. Rich text comes out as its words, a paragraph to a line; a block's
 * own `key` and `type` are its shape, not anything anybody wrote.
 */
export function texts(value: unknown, path: MergeSegment[] = []): [MergeSegment[], string][] {
  if (typeof value === 'string') {
    const words = plain(value)

    return words === '' ? [] : [[path, words]]
  }

  if (Array.isArray(value)) {
    return value.flatMap((item) => {
      const node = item as { key?: unknown; type?: unknown } | null

      return node !== null && typeof node === 'object' && typeof node.key === 'string'
        ? texts(item, [
            ...path,
            { block: node.key, type: typeof node.type === 'string' ? node.type : '' },
          ])
        : texts(item, path)
    })
  }

  if (value === null || typeof value !== 'object') return []

  const inBlock = path.length > 0 && 'block' in path[path.length - 1]!

  return Object.entries(value).flatMap(([key, item]) =>
    inBlock && (key === 'key' || key === 'type') ? [] : texts(item, [...path, { field: key }]),
  )
}

function plain(html: string): string {
  return html
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/(p|h[1-6]|li|blockquote|div)>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/&nbsp;/g, ' ')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

/** A sentence whose time was not known ends where the time would have been, not on a «·». */
export function timeless(text: string): string {
  return text.replace(/\s*·\s*$/, '')
}

function clip(text: string): string {
  return text.length > 140 ? `${text.slice(0, 139)}…` : text
}

function clone<T>(value: T): T {
  return value === undefined ? value : (JSON.parse(JSON.stringify(value)) as T)
}
