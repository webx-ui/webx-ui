import { reactive, readonly, type DeepReadonly } from 'vue'
import type { IconName } from '../components/Icon/types'

export type ToastType = 'default' | 'success' | 'warning' | 'danger' | 'info'

export interface ToastAction {
  label: string
  onClick: () => void
}

export interface ToastOptions {
  /** A headline. Without one the toast is a single line — a message rather than a card. */
  title?: string
  /** The message. This is what a one-line toast says. */
  description?: string
  type?: ToastType
  /**
   * How long it stays, in milliseconds. `0` keeps it until it is dismissed, which is
   * what an error the reader has to act on wants.
   */
  duration?: number
  /** Adds a ×. On by default for anything that does not leave on its own. */
  closable?: boolean
  /** Glyph to draw instead of the one the type picks; `false` drops it. */
  icon?: IconName | false
  /** One thing to do about it: Undo, Retry, View. */
  action?: ToastAction
}

export interface ToastRecord extends ToastOptions {
  id: number
  /** Bound to the panel; set to false to start it leaving. */
  open: boolean
}

export interface ToastHandle {
  id: number
  dismiss: () => void
}

/**
 * The queue, outside any component.
 *
 * That is the point of it: a toast is usually raised from somewhere with no view of
 * its own — an HTTP interceptor, a store, a catch block — and asking those places to
 * find a component instance first is how a notification system ends up being passed
 * down through props. One queue per bundle, one `WxToaster` on the page to show it.
 */
const queue = reactive<ToastRecord[]>([])

let nextId = 0

/*
 * How long a toast is given to leave before its record is dropped. It is a timer
 * rather than an `animationend`, because a reader who has asked for reduced motion
 * gets no animation and would otherwise be left with a queue that never empties.
 */
const LEAVING_MS = 220

/** What the `WxToaster` renders. Nothing else should be writing to it. */
export function toastQueue(): DeepReadonly<ToastRecord[]> {
  return readonly(queue) as DeepReadonly<ToastRecord[]>
}

/** Starts a toast leaving. It is taken off the queue once it has gone. */
export function dismissToast(id: number) {
  const found = queue.find((toast) => toast.id === id)
  if (!found || !found.open) return

  found.open = false
  setTimeout(() => removeToast(id), LEAVING_MS)
}

/** Takes a toast off the queue for good — called when its animation has finished. */
export function removeToast(id: number) {
  const index = queue.findIndex((toast) => toast.id === id)
  if (index !== -1) queue.splice(index, 1)
}

/** Clears everything on screen at once. */
export function clearToasts() {
  for (const toast of [...queue]) dismissToast(toast.id)
}

function raise(options: ToastOptions): ToastHandle {
  const id = (nextId += 1)

  queue.push({
    type: 'default',
    duration: 5000,
    closable: true,
    ...options,
    id,
    open: true,
  })

  return { id, dismiss: () => dismissToast(id) }
}

/** A toast is either a message or an options bag; both reach the same queue. */
function normalise(input: string | ToastOptions, extra: ToastOptions = {}): ToastOptions {
  return typeof input === 'string' ? { description: input, ...extra } : { ...input, ...extra }
}

export interface ToastApi {
  (message: string | ToastOptions, options?: ToastOptions): ToastHandle
  success: (message: string | ToastOptions, options?: ToastOptions) => ToastHandle
  warning: (message: string | ToastOptions, options?: ToastOptions) => ToastHandle
  danger: (message: string | ToastOptions, options?: ToastOptions) => ToastHandle
  info: (message: string | ToastOptions, options?: ToastOptions) => ToastHandle
  dismiss: (id: number) => void
  clear: () => void
}

const api = ((message: string | ToastOptions, options: ToastOptions = {}) =>
  raise(normalise(message, options))) as ToastApi

api.success = (message, options = {}) => raise(normalise(message, { type: 'success', ...options }))
api.warning = (message, options = {}) => raise(normalise(message, { type: 'warning', ...options }))
api.danger = (message, options = {}) =>
  /* An error that vanishes on its own is an error nobody read. */
  raise(normalise(message, { type: 'danger', duration: 0, ...options }))
api.info = (message, options = {}) => raise(normalise(message, { type: 'info', ...options }))
api.dismiss = dismissToast
api.clear = clearToasts

/**
 * Raises a toast from anywhere.
 *
 * ```ts
 * const toast = useToast()
 * toast.success('Saved')
 * toast.danger({ title: 'Could not save', description: response.message })
 * ```
 *
 * The same object is importable as `toast` for code that is not in a component at
 * all — an interceptor, say. Put one `<wx-toaster />` on the page to show them.
 */
export function useToast(): ToastApi {
  return api
}

export { api as toast }
