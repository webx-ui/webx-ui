import type { LocalizedValue } from '@webx-ui/core'
import type { LinkValue } from '@webx-ui/module-admin'

/**
 * The layers of a banner on the panel's side (the layers spec, §3.2): their shape, the ones a
 * banner starts with, and the custom properties `layers.css` reads — the same numbers the server
 * prints (`Rendering\Layers`), so the canvas is the site.
 */

export type LayerType = 'text' | 'image' | 'button' | 'buttons'

export type LayerOrigin = 'tl' | 't' | 'tr' | 'l' | 'c' | 'r' | 'bl' | 'b' | 'br'

export type LayerView = 'desktop' | 'mobile'

/** One layout's geometry, every length in percent of the picture's width (decision 1). */
export interface LayerFrame {
  x: number
  y: number
  w: number
  size: number
  pad: number
  radius: number
  /** In em: letter spacing follows the type, not the picture. */
  tracking: number
  rotate: number
  gap: number
  origin: LayerOrigin
  hidden: boolean
}

export interface LayerStyle {
  font: 'heading' | 'body' | null
  weight: number
  italic: boolean
  uppercase: boolean
  align: 'start' | 'center' | 'end'
  line_height: number
  /** `#rrggbb`, `token:<name of the palette>` or `null` — the site's. */
  color: string | null
  background: string | null
  opacity: number
  shadow: boolean
}

export interface LayerMotion {
  in: 'fade' | 'rise' | 'slide-start' | 'slide-end' | 'zoom' | null
  loop: 'spin' | 'float' | null
  delay: number
  duration: number
}

export interface MediaRef {
  path: string
  url?: string
  alt?: LocalizedValue | string
}

export interface BannerLayer {
  id: string
  type: LayerType
  name?: string
  text?: LocalizedValue
  tag?: string
  image?: MediaRef | null
  link?: LinkValue | null
  variant?: string | null
  style: LayerStyle
  frames: { desktop: LayerFrame; mobile: LayerFrame | null }
  motion: LayerMotion
}

/** Range, step and default of each number of a layout — as `LayerRules::FRAME` has them. */
export const FRAME_LIMITS: Record<
  Exclude<keyof LayerFrame, 'origin' | 'hidden'>,
  [number, number, number]
> = {
  x: [-50, 150, 5],
  y: [-50, 150, 5],
  w: [1, 150, 40],
  size: [0.3, 20, 3],
  pad: [0, 10, 0],
  radius: [0, 50, 0],
  tracking: [-0.2, 1, 0],
  rotate: [-180, 180, 0],
  gap: [0, 10, 1],
}

export const ORIGINS: LayerOrigin[] = ['tl', 't', 'tr', 'l', 'c', 'r', 'bl', 'b', 'br']

const ORIGIN_SHIFT: Record<LayerOrigin, [number, number]> = {
  tl: [0, 0],
  t: [-50, 0],
  tr: [-100, 0],
  l: [0, -50],
  c: [-50, -50],
  r: [-100, -50],
  bl: [0, -100],
  b: [-50, -100],
  br: [-100, -100],
}

export const ENTRANCES = ['fade', 'rise', 'slide-start', 'slide-end', 'zoom'] as const

export const LOOPS = ['spin', 'float'] as const

/** The two fields of a banner a layer's words may put in. */
export const FIELDS = ['title', 'text'] as const

export function frame(over: Partial<LayerFrame> = {}): LayerFrame {
  return {
    x: 5,
    y: 5,
    w: 40,
    size: 3,
    pad: 0,
    radius: 0,
    tracking: 0,
    rotate: 0,
    gap: 1,
    origin: 'tl',
    hidden: false,
    ...over,
  }
}

export function style(over: Partial<LayerStyle> = {}): LayerStyle {
  return {
    font: null,
    weight: 400,
    italic: false,
    uppercase: false,
    align: 'start',
    line_height: 1.2,
    color: null,
    background: null,
    opacity: 1,
    shadow: false,
    ...over,
  }
}

export function motion(over: Partial<LayerMotion> = {}): LayerMotion {
  return { in: null, loop: null, delay: 0, duration: 600, ...over }
}

/**
 * A deep copy of a layer. Not `structuredClone`: what the form holds is a reactive proxy, and the
 * browser refuses to clone one.
 */
export function clone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T
}

/** A short id, unique among the layers it joins. */
export function layerId(taken: Iterable<string> = []): string {
  const used = new Set(taken)
  let id = ''

  do {
    id = Math.random().toString(36).slice(2, 8).padEnd(6, '0')
  } while (used.has(id) || !/^[a-z0-9]{4,12}$/.test(id))

  return id
}

/** A new layer of a kind, where a new layer of that kind usually goes. */
export function newLayer(type: LayerType, taken: Iterable<string>, locale: string): BannerLayer {
  const base = { id: layerId(taken), type, style: style(), motion: motion() }

  switch (type) {
    case 'text':
      return {
        ...base,
        text: { [locale]: 'Text' },
        tag: 'p',
        frames: { desktop: frame({ x: 10, y: 10, size: 2.4 }), mobile: null },
      }
    case 'image':
      return {
        ...base,
        image: null,
        frames: { desktop: frame({ x: 60, y: 10, w: 20 }), mobile: null },
      }
    case 'button':
      return {
        ...base,
        text: { [locale]: 'Button' },
        link: null,
        variant: null,
        frames: { desktop: frame({ x: 10, y: 25, w: 30, size: 1.2 }), mobile: null },
      }
    case 'buttons':
      return {
        ...base,
        frames: { desktop: frame({ x: 6, y: 27, w: 50, size: 1.2 }), mobile: null },
      }
  }
}

/**
 * What a banner of a place with layers starts with (decision 5): its title, its text and its
 * buttons, in a column on the left. The words are written once, in the default language — the
 * others take them from there (decision 6) — and the fields are put in in the page's language.
 */
export function defaultLayers(defaultLocale: string): BannerLayer[] {
  const taken: string[] = []
  const next = (layer: Omit<BannerLayer, 'id'>): BannerLayer => {
    const made = { ...layer, id: layerId(taken) } as BannerLayer

    taken.push(made.id)

    return made
  }

  return [
    next({
      type: 'text',
      text: { [defaultLocale]: '{title}' },
      tag: 'h2',
      style: style({ weight: 700, line_height: 1.05, color: '#ffffff', shadow: true }),
      frames: { desktop: frame({ x: 6, y: 6, w: 45, size: 4.2 }), mobile: null },
      motion: motion({ in: 'rise' }),
    }),
    next({
      type: 'text',
      text: { [defaultLocale]: '{text}' },
      tag: 'p',
      style: style({ line_height: 1.4, color: '#ffffff', shadow: true }),
      frames: { desktop: frame({ x: 6, y: 18, w: 40, size: 1.4 }), mobile: null },
      motion: motion({ in: 'rise', delay: 150 }),
    }),
    next({
      type: 'buttons',
      style: style(),
      frames: { desktop: frame({ x: 6, y: 28, w: 50, size: 1.2 }), mobile: null },
      motion: motion({ in: 'rise', delay: 300 }),
    }),
  ]
}

/**
 * A layer's words in this language: the page's, else the site's default, else the first one
 * written in the order of the site's languages (decision 6) — then the banner's fields put in,
 * in the page's language. The same as `Rendering\Layers::words()`.
 */
export function layerWords(
  text: LocalizedValue | undefined,
  locale: string,
  order: string[],
  fields: Partial<Record<(typeof FIELDS)[number], string>>,
): string {
  const map = text ?? {}
  const codes = [...new Set([locale, ...order, ...Object.keys(map)])]
  const words = codes.map((code) => map[code]).find((one) => (one ?? '').trim() !== '') ?? ''

  return words
    .replaceAll('{title}', fields.title ?? '')
    .replaceAll('{text}', fields.text ?? '')
    .trim()
}

/** `{title}` and `{text}` are fields; any other `{…}` in a layer's words is very likely a typo. */
export function unknownFields(text: string): string[] {
  return [...text.matchAll(/\{([a-z_.]+)\}/g)]
    .map((match) => match[1] ?? '')
    .filter((name) => !(FIELDS as readonly string[]).includes(name))
}

/** A colour of the theme's palette, or one of its own; anything else is no colour at all. */
export function cssColor(color: string | null | undefined): string | null {
  if (typeof color !== 'string') return null
  if (/^#[0-9a-f]{6}([0-9a-f]{2})?$/.test(color)) return color

  const token = /^token:([a-z][a-z0-9-]{0,40})$/.exec(color)

  return token ? `var(--site-color-${token[1]})` : null
}

const round = (value: number): number => Math.round(value * 100) / 100

/** The custom properties of a layer, as the server prints them, for both layouts. */
export function layerVars(layer: BannerLayer, z: number): Record<string, string> {
  const vars: Record<string, string> = {}
  const desktop = layer.frames.desktop
  const mobile = layer.frames.mobile ?? desktop

  for (const [prefix, one] of [
    ['', desktop],
    ['m-', mobile],
  ] as const) {
    for (const key of Object.keys(FRAME_LIMITS) as (keyof typeof FRAME_LIMITS)[]) {
      vars[`--${prefix}${key}`] = String(round(one[key]))
    }

    const [ox, oy] = ORIGIN_SHIFT[one.origin] ?? [0, 0]

    vars[`--${prefix}ox`] = `${ox}%`
    vars[`--${prefix}oy`] = `${oy}%`
    vars[`--${prefix}display`] = one.hidden ? 'none' : 'block'
  }

  vars['--weight'] = String(layer.style.weight)
  vars['--leading'] = String(layer.style.line_height)
  vars['--opacity'] = String(layer.style.opacity)
  vars['--align'] = layer.style.align

  const color = cssColor(layer.style.color)
  const background = cssColor(layer.style.background)

  if (color) vars['--color'] = color
  if (background) vars['--background'] = background
  if (layer.style.font) vars['--font'] = `var(--site-font-${layer.style.font})`

  vars['z-index'] = String(z)

  return vars
}

/** The classes of a layer's element, as the server prints them (motion is the site's, not the canvas's). */
export function layerClasses(layer: BannerLayer): string[] {
  const classes = ['webx-banner__layer', `webx-banner__layer--${layer.type}`]

  if (layer.style.italic) classes.push('is-italic')
  if (layer.style.uppercase) classes.push('is-uppercase')
  if (layer.style.shadow) classes.push('is-shadow')
  if (layer.style.background) classes.push('has-background')

  return classes
}

/** `16/6`, `16 / 6`, `2.4` → width over height; anything else is `fallback`. */
export function ratioOf(value: string | number | null | undefined, fallback: number): number {
  if (typeof value === 'number') return value > 0 ? value : fallback

  const match = /^\s*(\d+(?:\.\d+)?)\s*(?:\/\s*(\d+(?:\.\d+)?))?\s*$/.exec(value ?? '')

  if (!match) return fallback

  const ratio = Number(match[1]) / Number(match[2] ?? 1)

  return Number.isFinite(ratio) && ratio > 0 ? ratio : fallback
}

/**
 * Where the frame of the place falls on the picture — what the site shows of it — as percent of
 * the picture's width and height. `cover` crops the longer side, centred.
 */
export function cropBox(
  image: number,
  frameRatio: number,
): { left: number; top: number; width: number; height: number } {
  if (image > frameRatio) {
    const width = (frameRatio / image) * 100

    return { left: (100 - width) / 2, top: 0, width, height: 100 }
  }

  const height = (image / frameRatio) * 100

  return { left: 0, top: (100 - height) / 2, width: 100, height }
}

/** A layer as it is stored: the phone's layout, when it differs, or `null`. */
export function clampFrame(one: LayerFrame): LayerFrame {
  const next = { ...one }

  for (const key of Object.keys(FRAME_LIMITS) as (keyof typeof FRAME_LIMITS)[]) {
    const [min, max] = FRAME_LIMITS[key]

    next[key] = round(Math.min(max, Math.max(min, Number(next[key]) || 0)))
  }

  return next
}
