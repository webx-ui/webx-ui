import { inject, ref, type InjectionKey, type Ref } from 'vue'
import type { AdminContext } from './admin'

/** One field of a page of help: the words of the form, and what it does in the module's own. */
export interface HelpField {
  id: string
  /** Some fields carry their label on the card around them, and then there is none here. */
  label: string | null
  hint: string | null
  required: boolean
  /** Markdown. */
  body: string | null
}

/** A tab of the form, or the whole of a form with no tabs — and then `label` is `null`. */
export interface HelpGroup {
  id: string
  label: string | null
  fields: HelpField[]
}

export interface HelpHowto {
  title: string
  /** Markdown. */
  body: string
}

/** What `GET /api/cms/help/{key}` answers with. Mirrors `WebxUi\Admin\Help\HelpBuilder`. */
export interface HelpPage {
  key: string
  title: string
  /** The language the text is really in: English, when the panel's has no text. */
  locale: string
  /** Markdown. */
  about: string
  howto: HelpHowto[]
  groups: HelpGroup[]
  /** The address of the guide, on a site that has switched the link on. */
  guide: string | null
}

/** What ties a «?» to the panel it opens, for `aria-controls`. */
export const HELP_PANEL_ID = 'wx-help-panel'

/** The width `WxDrawer` goes full-screen at. Below it there is no form to keep in view. */
const PHONE = '(max-width: 640px)'

/**
 * The one help panel of the shell, as the buttons see it.
 *
 * The state lives here rather than in the panel so that a «?» anywhere in the panel can reach it,
 * and so that the shell can read how much room to give up.
 */
export interface Help {
  readonly open: Ref<boolean>
  /** The page on show, or the last one shown. */
  readonly key: Ref<string | null>
  /** Modal rather than beside the form: see `toggle`. */
  readonly blocking: Ref<boolean>
  /** Pixels the panel takes up, kept up to date by the panel while it is open. */
  readonly width: Ref<number>
  /** Whether the server has a page under this key. A screen without one has no button. */
  available(key: string): boolean
  /** What a «?» does: opens this page, or closes it when it is the one on show. */
  toggle(key: string, trigger: HTMLElement | null): void
  /** Another page in the open panel, which is what going to another screen does. */
  show(key: string): void
  close(): void
  /** Hands the focus back to the «?», unless the reader has already moved on to the form. */
  returnFocus(): void
}

export const helpKey: InjectionKey<Help> = Symbol('webx-help')

/** `null` outside a shell: a head mounted on its own simply has no button. */
export function useHelp(): Help | null {
  return inject(helpKey, null)
}

/**
 * A modal dialog sets `pointer-events: none` on the body, so a panel beside it would not take a
 * click; a phone has no room beside anything. In both the panel is modal itself.
 */
function needsBlocking(trigger: HTMLElement | null): boolean {
  if (trigger?.closest('[role="dialog"]')) return true

  return typeof window.matchMedia === 'function' && window.matchMedia(PHONE).matches
}

export function createHelp(admin: Pick<AdminContext, 'state'>): Help {
  const open = ref(false)
  const key = ref<string | null>(null)
  const blocking = ref(false)
  const width = ref(0)

  let trigger: HTMLElement | null = null

  function close() {
    open.value = false
  }

  return {
    open,
    key,
    blocking,
    width,
    available(name) {
      return admin.state.manifest?.help?.includes(name) ?? false
    },
    toggle(name, from) {
      if (open.value && key.value === name) {
        close()

        return
      }

      trigger = from

      const modal = needsBlocking(from)

      if (open.value && modal === blocking.value) {
        key.value = name

        return
      }

      /*
       * A panel opened by a click inside another one is closed by that same click: Reka takes
       * the end of it for a click outside the layer it has just made. So is one that has to
       * change its mode, which is a different layer. Both open on the next turn of the loop.
       */
      const later = modal || open.value

      open.value = false
      blocking.value = modal
      key.value = name

      if (later) {
        setTimeout(() => {
          open.value = true
        })
      } else {
        open.value = true
      }
    },
    show(name) {
      key.value = name
    },
    close,
    returnFocus() {
      const active = document.activeElement

      /* Esc pressed in a field of the form closes the panel and leaves the typing where it is. */
      if (active !== null && active !== document.body && !active.closest(`#${HELP_PANEL_ID}`)) {
        return
      }

      /* The screen may have changed under the panel, and then its own «?» takes the focus. */
      const target = trigger?.isConnected
        ? trigger
        : document.querySelector<HTMLElement>(`[aria-controls="${HELP_PANEL_ID}"]`)

      target?.focus()
    },
  }
}
