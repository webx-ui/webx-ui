import {
  defineComponent,
  getCurrentInstance,
  h,
  inject,
  onErrorCaptured,
  provide,
  ref,
  render,
  type App,
  type AppContext,
  type Component,
  type InjectionKey,
  type Ref,
} from 'vue'

/** What a component opened from code can do about itself. */
export interface ModalHandle<T = unknown> {
  /** `false` in a component that is being used declaratively. */
  isModal: boolean
  /** Bind it to the panel: `<wx-dialog v-model:open="open">`. */
  open: Ref<boolean>
  /** Settles the promise with a value, and closes. */
  resolve: (value: T) => void
  /** Settles it with nothing, and closes. */
  dismiss: () => void
}

export interface ModalOptions {
  /** Props for the component. */
  props?: Record<string, unknown>
  /** Slots for it, as render functions. */
  slots?: Record<string, (...args: unknown[]) => unknown>
  /** Events that settle the promise with their payload. Default: `resolve`. */
  resolveOn?: string | string[]
  /** Events that settle it with nothing. Default: `cancel` and `close`. */
  dismissOn?: string | string[]
  /** Where to mount. Default: a div at the end of `<body>`. */
  container?: HTMLElement
  /** The app whose plugins and provides the component should see. */
  appContext?: AppContext
  /** How long the closing animation is given before the node is taken away, in ms. */
  duration?: number
}

export interface ModalPromise<T> extends Promise<T | undefined> {
  /** Closes it from outside — a route change, a timeout, a second thought. */
  close: (value?: T) => void
}

export const modalKey: InjectionKey<ModalHandle> = Symbol('wx-modal')

/*
 * A component mounted outside the app's own tree knows nothing of it: no plugins, no
 * provides, no global components, no i18n. The way to hand it all of that is to give its
 * vnode the app's context, which is why the plugin remembers the app it was installed
 * into. Anyone importing components one at a time calls `connectModals` instead.
 */
let installed: AppContext | undefined

export function connectModals(app: App) {
  installed = (app as App & { _context: AppContext })._context
}

function contextFor(explicit?: AppContext) {
  return explicit ?? getCurrentInstance()?.appContext ?? installed
}

/** `select` → `onSelect`, the name Vue gives a listener for that event. */
function handlerFor(event: string) {
  const camel = event.replace(/-(\w)/g, (_, letter: string) => letter.toUpperCase())
  return `on${camel.charAt(0).toUpperCase()}${camel.slice(1)}`
}

function listOf(names: string | string[] | undefined, fallback: string[]) {
  if (names === undefined) return fallback
  return Array.isArray(names) ? names : [names]
}

/** Whether the component asks for a prop, so that we only pass one it knows about. */
function declares(component: Component, name: string) {
  const props = (component as { props?: string[] | Record<string, unknown> }).props
  if (Array.isArray(props)) return props.includes(name)
  return Boolean(props && name in props)
}

/**
 * Mounts a component outside the app and hands back a promise for its answer.
 *
 * ```ts
 * const product = await openModal<Product>(ProductBrowser, {
 *   props: { multiple: false },
 *   resolveOn: 'select',
 * })
 * ```
 *
 * The promise settles the moment the answer is known and the node is taken away once the
 * closing animation has had its time — so the caller is never waiting on an animation, and
 * the panel is never cut off mid-fade.
 */
export function openModal<T = unknown>(
  component: Component,
  options: ModalOptions = {},
): ModalPromise<T> {
  let settle!: (value: T | undefined) => void
  let fail!: (reason: unknown) => void

  const promise = new Promise<T | undefined>((resolve, reject) => {
    settle = resolve
    fail = reject
  })

  const container = document.createElement('div')
  container.className = 'wx-modal-host'
  ;(options.container ?? document.body).append(container)

  const open = ref(true)

  let done = false
  let timer: ReturnType<typeof setTimeout> | undefined
  let gone = false

  /*
   * The plain timer functions rather than `window`'s. The panel is taken away a moment
   * after it closes, and that moment can land after whatever set it up has gone — the end
   * of a test, a page being torn down — where reaching through `window` is a reference
   * error rather than a cleanup.
   */
  function unmount() {
    /*
     * An unmount hook that throws comes back here through `onErrorCaptured`, and a second
     * `render(null)` over a half-unmounted tree throws again — one error becomes a stack
     * overflow. Once is enough.
     */
    if (gone) return
    gone = true

    clearTimeout(timer)
    render(null, container)
    container.remove()
  }

  function finish(value?: T) {
    if (done) return
    done = true
    open.value = false
    settle(value)
    timer = setTimeout(unmount, options.duration ?? 250)
  }

  const handle: ModalHandle<T> = {
    isModal: true,
    open,
    resolve: (value: T) => finish(value),
    dismiss: () => finish(undefined),
  }

  const listeners: Record<string, unknown> = {}
  for (const event of listOf(options.dismissOn, ['cancel', 'close'])) {
    listeners[handlerFor(event)] = () => finish(undefined)
  }
  for (const event of listOf(options.resolveOn, ['resolve'])) {
    listeners[handlerFor(event)] = (value: T) => finish(value)
  }

  /*
   * A host of our own rather than the component itself, for two reasons: it can hold the
   * handle for anything inside to reach, and it re-renders — which is what lets the panel
   * be told to close and play its animation before the whole thing is taken away.
   */
  const host = defineComponent({
    name: 'WxModalHost',
    setup() {
      provide(modalKey, handle as ModalHandle)

      /* A modal that throws would otherwise leave the caller awaiting it for ever. */
      onErrorCaptured((error) => {
        if (!done) {
          done = true
          fail(error)
        }
        unmount()
        return false
      })

      return () =>
        h(
          component,
          {
            ...options.props,
            ...(declares(component, 'open')
              ? {
                  open: open.value,
                  'onUpdate:open': (value: boolean) => {
                    if (!value) finish(undefined)
                  },
                }
              : {}),
            ...listeners,
          },
          options.slots,
        )
    },
  })

  const vnode = h(host)
  vnode.appContext = contextFor(options.appContext) ?? null
  render(vnode, container)

  const result = promise as ModalPromise<T>
  result.close = (value?: T) => finish(value)
  return result
}

/**
 * Wraps a component in a function that opens it — the shape a browser or a picker is
 * reached by everywhere else in an app.
 *
 * ```ts
 * export const productBrowser = createModal<Product, ProductBrowserProps>(ProductBrowser, {
 *   resolveOn: 'select',
 * })
 *
 * const product = await productBrowser({ multiple: false })
 * ```
 *
 * `P` is any object — an `interface` of props included, which a `Record<string, unknown>`
 * would have turned away for want of an index signature.
 */
export function createModal<T = unknown, P extends object = Record<string, never>>(
  component: Component,
  defaults: ModalOptions = {},
) {
  return (props?: P, options: ModalOptions = {}): ModalPromise<T> =>
    openModal<T>(component, {
      ...defaults,
      ...options,
      props: { ...defaults.props, ...props, ...options.props } as Record<string, unknown>,
    })
}

/**
 * The handle of the modal a component is being shown in. Outside one it answers all the
 * same, with `isModal: false` and calls that do nothing — so a component can be written
 * once and used both ways.
 */
export function useModal<T = unknown>(): ModalHandle<T> {
  const handle = inject(modalKey, undefined)
  if (handle) return handle as ModalHandle<T>

  return {
    isModal: false,
    open: ref(true),
    resolve: () => {},
    dismiss: () => {},
  }
}
