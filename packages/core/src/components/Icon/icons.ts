/*
 * The file family is a page with a mark on it. The page is drawn once here and the marks
 * sit in the room it leaves: roughly x 8.5–15.5, y 12–18, the lower half under the folded
 * corner. They are declared before the set below because that is where they are used.
 */
const PAGE = '<path d="M13.5 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9zm0 0V9H19"/>'

/** Lines of prose. */
const TEXT = PAGE + '<path d="M8.5 12.8h7M8.5 15.4h7M8.5 18h4"/>'
/** A little grid. */
const SHEET = PAGE + '<path d="M8.5 12.6h7v5.4h-7z"/><path d="M8.5 15.3h7M12 12.6V18"/>'
/** Bars, which is what a deck is full of. */
const SLIDES = PAGE + '<path d="M9.3 18v-2.6M12 18v-5.2M14.7 18v-3.6"/>'
/*
 * A P, because a red page with three letters on it is not available here. One letter and
 * not two: a P beside a bare stroke for the D read as "PI".
 */
const PDF = PAGE + '<path d="M10.2 18v-5.4h2.1a1.7 1.7 0 0 1 0 3.4h-2.1"/>'
/** The slider of a zip. */
const ARCHIVE =
  PAGE + '<rect x="10.4" y="12.4" width="3.2" height="5.6" rx="1.4"/><path d="M12 14.4v1.6"/>'
/** A note with its stem. */
const AUDIO =
  PAGE +
  '<path d="M10.4 17.3v-4.4l4.6-1v4.4"/><circle cx="9.3" cy="17.4" r="1.15"/><circle cx="13.9" cy="16.4" r="1.15"/>'
/** A play triangle. */
const VIDEO = PAGE + '<path d="M10.4 12.9v4.8l4.4-2.4z"/>'
/** Hills and a sun — the same picture the `image` icon draws, at a sixth of the room. */
const PICTURE =
  PAGE + '<path d="M8.6 17.8 11 14.9l1.7 1.9 1.3-1.5 2.1 2.5"/><circle cx="10" cy="13.4" r="1.05"/>'
/** A curve between two nodes. */
const VECTOR =
  PAGE +
  '<path d="M9.4 17.4c0-3.2 5.2-3.2 5.2 0"/><circle cx="9.4" cy="17.7" r="1"/><circle cx="14.6" cy="17.7" r="1"/>'
/** Sheets stacked on sheets. */
const LAYERED =
  PAGE + '<path d="m12 12.4-3.5 1.8 3.5 1.8 3.5-1.8z"/><path d="m8.5 16.6 3.5 1.8 3.5-1.8"/>'
/** Angle brackets. */
const CODE = PAGE + '<path d="m10.5 13.3-2 2 2 2M13.5 13.3l2 2-2 2"/>'
/** A serif A, as close as a stroke gets to a specimen. */
const FONT = PAGE + '<path d="M9.6 18l2.4-5.4 2.4 5.4M10.5 16.2h3"/>'

/**
 * The built-in icon set.
 *
 * Every entry is the *inside* of a 24×24 `<svg>` drawn in `currentColor` with a
 * stroke — the wrapper in `Icon.vue` supplies the viewBox, the stroke width and the
 * colour, so an icon inherits the text it sits next to. Shapes that read better
 * filled (dots, pips) set `fill="currentColor" stroke="none"` themselves.
 */
export const builtinIcons = {
  // --- actions -------------------------------------------------------------
  check: '<path d="m5 13 4.5 4.5L19 7"/>',
  'check-circle': '<circle cx="12" cy="12" r="8.5"/><path d="m8.2 12.2 2.6 2.6 5-5.2"/>',
  close: '<path d="M18 6 6 18M6 6l12 12"/>',
  'close-circle': '<circle cx="12" cy="12" r="8.5"/><path d="m9 9 6 6m0-6-6 6"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  minus: '<path d="M5 12h14"/>',
  edit: '<path d="M4 20h4L19.5 8.5a2.83 2.83 0 0 0-4-4L4 16v4Z"/><path d="m14.5 5.5 4 4"/>',
  trash:
    '<path d="M4 7h16M10 7V5.5A1.5 1.5 0 0 1 11.5 4h1A1.5 1.5 0 0 1 14 5.5V7M6.5 7l.8 11.6a2 2 0 0 0 2 1.9h5.4a2 2 0 0 0 2-1.9L17.5 7"/><path d="M10 11v5.5M14 11v5.5"/>',
  copy: '<rect x="8.5" y="8.5" width="11" height="11" rx="2.2"/><path d="M15.5 8.5v-2a2 2 0 0 0-2-2h-7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h2"/>',
  search: '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
  filter: '<path d="M4 5.5h16l-6.2 7.2v5.1l-3.6 2v-7.1z"/>',
  crop: '<path d="M6.5 2.5v13a2 2 0 0 0 2 2h13"/><path d="M2.5 6.5h13a2 2 0 0 1 2 2v13"/>',
  refresh:
    '<path d="M4 12a8 8 0 0 1 13.7-5.6L20 8.5"/><path d="M20 4.5v4h-4"/><path d="M20 12a8 8 0 0 1-13.7 5.6L4 15.5"/><path d="M4 19.5v-4h4"/>',
  upload:
    '<path d="M12 16V4m0 0-4.5 4.5M12 4l4.5 4.5"/><path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/>',
  download:
    '<path d="M12 4v12m0 0-4.5-4.5M12 16l4.5-4.5"/><path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/>',
  logout:
    '<path d="M14 6V5a1.5 1.5 0 0 0-1.5-1.5h-6A1.5 1.5 0 0 0 5 5v14a1.5 1.5 0 0 0 1.5 1.5h6A1.5 1.5 0 0 0 14 19v-1"/><path d="M10 12h10m0 0-3-3m3 3-3 3"/>',

  // --- direction -----------------------------------------------------------
  'chevron-down': '<path d="m6 9.5 6 6 6-6"/>',
  'chevron-up': '<path d="m6 14.5 6-6 6 6"/>',
  'chevron-left': '<path d="m14.5 6-6 6 6 6"/>',
  'chevron-right': '<path d="m9.5 6 6 6-6 6"/>',
  'arrow-up': '<path d="M12 20V4m0 0 6 6m-6-6-6 6"/>',
  'arrow-down': '<path d="M12 4v16m0 0 6-6m-6 6-6-6"/>',
  'arrow-left': '<path d="M20 12H4m0 0 6-6m-6 6 6 6"/>',
  'arrow-right': '<path d="M4 12h16m0 0-6-6m6 6-6 6"/>',
  menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
  /* The sidebar toggle: a page with a column down its left. */
  sidebar: '<rect x="3" y="4.5" width="18" height="15" rx="2.5"/><path d="M9.5 4.5v15"/>',
  'more-horizontal':
    '<circle cx="5.5" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="18.5" cy="12" r="1.6" fill="currentColor" stroke="none"/>',
  'more-vertical':
    '<circle cx="12" cy="5.5" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/><circle cx="12" cy="18.5" r="1.6" fill="currentColor" stroke="none"/>',
  drag: '<circle cx="9" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="15" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="9" cy="18" r="1.5" fill="currentColor" stroke="none"/><circle cx="15" cy="18" r="1.5" fill="currentColor" stroke="none"/>',

  // --- objects -------------------------------------------------------------
  user: '<circle cx="12" cy="8" r="3.75"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
  users:
    '<circle cx="9.5" cy="8.5" r="3.4"/><path d="M2.5 20a7 7 0 0 1 14 0"/><path d="M16 5.5a3.4 3.4 0 0 1 0 6.6M17.5 14.2A6.3 6.3 0 0 1 21.5 20"/>',
  settings:
    '<circle cx="12" cy="12" r="3"/><path d="M12 4v2.5M12 17.5V20M4 12h2.5M17.5 12H20M6.34 6.34l1.77 1.77M15.89 15.89l1.77 1.77M17.66 6.34l-1.77 1.77M6.34 17.66l1.77-1.77"/>',
  sliders:
    '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
  home: '<path d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5H15V14H9v6.5H5.5A1.5 1.5 0 0 1 4 19Z"/>',
  file: '<path d="M13.5 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9zm0 0V9H19"/>',
  folder:
    '<path d="M3.5 7a2 2 0 0 1 2-2h3.2a2 2 0 0 1 1.5.7l1.3 1.5h7a2 2 0 0 1 2 2v8.3a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z"/>',
  image:
    '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="8.75" cy="9.75" r="1.75"/><path d="m4 17 4.5-4.2a2 2 0 0 1 2.7 0l3 2.8 1.6-1.5a2 2 0 0 1 2.7 0L20 16"/>',
  calendar:
    '<rect x="3.5" y="5.5" width="17" height="15" rx="2.5"/><path d="M3.5 10h17M8 3.5V7M16 3.5V7"/>',
  clock: '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7.5 6.9 5a2 2 0 0 0 2.2 0l6.9-5"/>',
  phone:
    '<path d="M7 3.5 9.5 4l1.2 3.4-1.9 1.6a11 11 0 0 0 5.2 5.2l1.6-1.9 3.4 1.2.5 2.5a2 2 0 0 1-2 2.3A15.5 15.5 0 0 1 4.7 5.5a2 2 0 0 1 2.3-2Z"/>',
  bell: '<path d="M7 10a5 5 0 0 1 10 0c0 4 1.5 5.5 1.5 5.5h-13S7 14 7 10Z"/><path d="M10.2 18.5a2 2 0 0 0 3.6 0"/>',
  cart: '<path d="M3 4.5h2.2l2.3 10.2a1.6 1.6 0 0 0 1.6 1.3h7.4a1.6 1.6 0 0 0 1.6-1.2L20 8H6"/><circle cx="10" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/>',
  tag: '<path d="M4 11.2V5.5A1.5 1.5 0 0 1 5.5 4h5.7a2 2 0 0 1 1.4.6l7 7a2 2 0 0 1 0 2.8l-5.2 5.2a2 2 0 0 1-2.8 0l-7-7a2 2 0 0 1-.6-1.4Z"/><circle cx="8.5" cy="8.5" r="1.4" fill="currentColor" stroke="none"/>',
  link: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L11.5 7"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3A4 4 0 0 0 11 18.7L12.5 17"/>',
  'external-link':
    '<path d="M13.5 4.5H20V11"/><path d="m11 13 9-8.5"/><path d="M19 14.5v4a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 5 18.5v-11A1.5 1.5 0 0 1 6.5 6h4"/>',
  lock: '<rect x="4.5" y="10" width="15" height="10" rx="2.5"/><path d="M8 10V7.8a4 4 0 0 1 8 0V10"/>',
  unlock:
    '<rect x="4.5" y="10" width="15" height="10" rx="2.5"/><path d="M8 10V7.8a4 4 0 0 1 7.6-1.8"/>',
  grid: '<rect x="4" y="4" width="7" height="7" rx="1.8"/><rect x="13" y="4" width="7" height="7" rx="1.8"/><rect x="4" y="13" width="7" height="7" rx="1.8"/><rect x="13" y="13" width="7" height="7" rx="1.8"/>',
  list: '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="5" cy="6" r="1.4" fill="currentColor" stroke="none"/><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="5" cy="18" r="1.4" fill="currentColor" stroke="none"/>',
  eye: '<path d="M2.5 12S6.8 5.5 12 5.5 21.5 12 21.5 12 17.2 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.75"/>',
  'eye-off':
    '<path d="M10.2 6a9.9 9.9 0 0 1 1.8-.2c5.2 0 9.5 6.2 9.5 6.2a18 18 0 0 1-3.3 3.8M6.6 7.9A17.8 17.8 0 0 0 2.5 12s4.3 6.5 9.5 6.5a9.4 9.4 0 0 0 3.3-.6"/><path d="m4 4 16 16"/>',
  star: '<path d="m12 4 2.5 5.1 5.6.8-4 4 .9 5.6-5-2.7-5 2.7.9-5.6-4-4 5.6-.8z"/>',
  heart:
    '<path d="M12 19.5S4 15 4 9.8A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 20 9.8c0 5.2-8 9.7-8 9.7Z"/>',
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/>',
  moon: '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',

  // --- status --------------------------------------------------------------
  info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5"/><circle cx="12" cy="7.75" r="1.05" fill="currentColor" stroke="none"/>',
  warning:
    '<path d="M10.3 4.9a2 2 0 0 1 3.4 0l7 12.1a2 2 0 0 1-1.7 3H5a2 2 0 0 1-1.7-3z"/><path d="M12 9.5v4"/><circle cx="12" cy="16.6" r="1.05" fill="currentColor" stroke="none"/>',
  question:
    '<circle cx="12" cy="12" r="8.5"/><path d="M9.6 9.4a2.5 2.5 0 0 1 4.9.6c0 1.7-2.5 2-2.5 3.5"/><circle cx="12" cy="16.6" r="1.05" fill="currentColor" stroke="none"/>',
  loader:
    '<path d="M12 3.5v4M12 16.5v4M3.5 12h4M16.5 12h4M6 6l2.8 2.8M15.2 15.2 18 18M18 6l-2.8 2.8M8.8 15.2 6 18"/>',

  /*
   * --- files ---------------------------------------------------------------
   *
   * A name per extension, so `file-docx` is a name whether or not it draws
   * something of its own, and a screen never has to map an extension to a family
   * before it can ask for an icon. Extensions that are the same kind of file share
   * a drawing: a `.docx` and an `.odt` are both a page of prose, and pretending
   * otherwise would mean twelve marks nobody can tell apart.
   *
   * `file-generic` is what an unknown extension gets. `WxFileCard` writes the
   * extension under it, which is the part that tells a `.sketch` from a `.dwg`.
   */
  'file-generic': PAGE,

  'file-txt': TEXT,
  'file-md': TEXT,
  'file-rtf': TEXT,
  'file-doc': TEXT,
  'file-docx': TEXT,
  'file-odt': TEXT,
  'file-pages': TEXT,

  'file-csv': SHEET,
  'file-xls': SHEET,
  'file-xlsx': SHEET,
  'file-ods': SHEET,
  'file-numbers': SHEET,

  'file-ppt': SLIDES,
  'file-pptx': SLIDES,
  'file-odp': SLIDES,
  'file-key': SLIDES,

  'file-pdf': PDF,

  'file-zip': ARCHIVE,
  'file-rar': ARCHIVE,
  'file-7z': ARCHIVE,
  'file-tar': ARCHIVE,
  'file-gz': ARCHIVE,
  'file-bz2': ARCHIVE,

  'file-mp3': AUDIO,
  'file-wav': AUDIO,
  'file-ogg': AUDIO,
  'file-flac': AUDIO,
  'file-aac': AUDIO,
  'file-m4a': AUDIO,

  'file-mp4': VIDEO,
  'file-mov': VIDEO,
  'file-avi': VIDEO,
  'file-webm': VIDEO,
  'file-mkv': VIDEO,
  'file-m4v': VIDEO,

  'file-jpg': PICTURE,
  'file-jpeg': PICTURE,
  'file-png': PICTURE,
  'file-gif': PICTURE,
  'file-webp': PICTURE,
  'file-avif': PICTURE,
  'file-bmp': PICTURE,
  'file-tiff': PICTURE,
  'file-ico': PICTURE,

  'file-svg': VECTOR,
  'file-ai': VECTOR,
  'file-eps': VECTOR,

  'file-psd': LAYERED,
  'file-xcf': LAYERED,
  'file-fig': LAYERED,
  'file-sketch': LAYERED,

  'file-js': CODE,
  'file-ts': CODE,
  'file-jsx': CODE,
  'file-tsx': CODE,
  'file-vue': CODE,
  'file-json': CODE,
  'file-xml': CODE,
  'file-yml': CODE,
  'file-yaml': CODE,
  'file-html': CODE,
  'file-css': CODE,
  'file-scss': CODE,
  'file-php': CODE,
  'file-py': CODE,
  'file-rb': CODE,
  'file-go': CODE,
  'file-java': CODE,
  'file-sh': CODE,
  'file-sql': CODE,

  'file-ttf': FONT,
  'file-otf': FONT,
  'file-woff': FONT,
  'file-woff2': FONT,
} as const satisfies Record<string, string>

/** Every name the library ships with. */
export type BuiltinIconName = keyof typeof builtinIcons

const customIcons = new Map<string, string>()

/**
 * Adds icons to the set, so `<wx-icon name="my-logo" />` works anywhere in the app.
 *
 * ```ts
 * registerIcons({ 'my-logo': '<path d="M4 4h16v16H4z" />' })
 * ```
 *
 * The markup is injected as-is, so register only icons you author or control —
 * never a string that arrived from a user or an API.
 */
export function registerIcons(icons: Record<string, string>): void {
  for (const [name, content] of Object.entries(icons)) customIcons.set(name, content)
}

/** The markup of an icon, or `undefined` when nothing is registered under that name. */
export function resolveIcon(name: string): string | undefined {
  return customIcons.get(name) ?? (builtinIcons as Record<string, string>)[name]
}

/** Names currently available, built-in plus registered — handy for a docs gallery. */
export function iconNames(): string[] {
  return [...new Set([...Object.keys(builtinIcons), ...customIcons.keys()])].sort()
}
