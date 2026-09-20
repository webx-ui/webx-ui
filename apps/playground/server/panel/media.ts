import type { MediaDirectory, MediaFile } from '../../../../packages/module-media/src/types'

/**
 * The library the playground picks pictures from.
 *
 * It exists because of one card on the article editor: `cover` is a `wx-media` field, and a
 * screen whose field type nobody registered draws "Unknown type: wx-media" where a picture
 * belongs. So the module is installed for real and answered for real — which also gives the
 * rich-text editor its image button, since that is the same door (`pickImage`).
 *
 * The pictures are drawn rather than stored: a placeholder is an SVG generated from the file's
 * own name, so the repository carries no binaries and every file has a distinct, recognisable
 * face. What is uploaded in a session is kept as bytes until the dev server restarts.
 */

let nextFileId = 1

export const directories: MediaDirectory[] = []

export const files: MediaFile[] = []

/** What was uploaded while the dev server has been up, by key. */
export const uploads = new Map<string, { bytes: Buffer; mime: string }>()

function folder(id: number, parentId: number | null, title: string): MediaDirectory {
  const parent = parentId === null ? null : find(parentId)

  const node: MediaDirectory = {
    id,
    parent_id: parentId,
    title,
    depth: parent === null ? 0 : parent.depth + 1,
    is_root: parentId === null,
    files_count: 0,
    children: [],
  }

  if (parent === null) {
    directories.push(node)
  } else {
    parent.children.push(node)
  }

  return node
}

/** A folder anywhere in the tree — the shape is nested, and everything else works by id. */
export function find(id: number): MediaDirectory | null {
  const walk = (list: MediaDirectory[]): MediaDirectory | null => {
    for (const node of list) {
      if (node.id === id) return node

      const inside = walk(node.children)

      if (inside !== null) return inside
    }

    return null
  }

  return walk(directories)
}

export function flat(): MediaDirectory[] {
  const out: MediaDirectory[] = []
  const walk = (list: MediaDirectory[]): void => {
    for (const node of list) {
      out.push(node)
      walk(node.children)
    }
  }

  walk(directories)

  return out
}

folder(1, null, 'Медиатека')
folder(2, 1, 'Блог')
folder(3, 1, 'Страницы')
folder(4, 1, 'Документы')

/**
 * @param name What the library calls it — and what the generated picture says on its face.
 */
function image(
  directoryId: number,
  path: string,
  name: string,
  width: number,
  height: number,
  createdAt: string,
): MediaFile {
  const id = nextFileId++
  const fileName = path.slice(path.lastIndexOf('/') + 1)
  const address = `/fixtures/media/${path}`

  const file: MediaFile = {
    id,
    directory_id: directoryId,
    name,
    file_name: fileName,
    extension: 'svg',
    mime: 'image/svg+xml',
    type: 'image',
    size: width * height * 3,
    width,
    height,
    path,
    url: address,
    thumb: address,
    source: address,
    // The fixture draws pictures, it does not process them: an editor that opened and then did
    // nothing is worse than no editor at all, so the button is not offered.
    editable: false,
    has_original: false,
    duplicate: false,
    created_at: createdAt,
  }

  files.push(file)

  return file
}

/* Two at the top level, so the library opens on something rather than on "this folder is
   empty" — which reads as a broken fixture and is the first thing anybody sees here. */
image(1, 'logo.svg', 'Логотип студии', 512, 512, '2026-05-30T08:00:00+00:00')
image(1, 'og-default.svg', 'Картинка для соцсетей', 1200, 630, '2026-05-30T08:05:00+00:00')

image(
  2,
  'blog/2026/09/panel-roadmap.svg',
  'Дорожная карта панели',
  1600,
  900,
  '2026-09-18T09:10:00+00:00',
)
image(
  2,
  'blog/2026/09/laravel-queues.svg',
  'Очереди в Laravel',
  1600,
  900,
  '2026-09-16T11:40:00+00:00',
)
image(
  2,
  'blog/2026/09/design-tokens.svg',
  'Токены дизайн-системы',
  1600,
  900,
  '2026-09-12T08:05:00+00:00',
)
image(
  2,
  'blog/2026/08/mobile-gestures.svg',
  'Жесты на телефоне',
  1600,
  900,
  '2026-08-29T14:20:00+00:00',
)
image(
  2,
  'blog/2026/08/case-alfatech.svg',
  'Кейс «Альфатех»',
  1600,
  900,
  '2026-08-21T10:00:00+00:00',
)
image(
  2,
  'blog/2026/08/support-sla.svg',
  'Поддержка по договору',
  1600,
  900,
  '2026-08-11T09:30:00+00:00',
)
image(
  2,
  'blog/2026/07/migration.svg',
  'Переезд с самописной CMS',
  1600,
  900,
  '2026-07-30T13:15:00+00:00',
)
image(2, 'blog/2026/07/team.svg', 'Команда студии', 1600, 900, '2026-07-18T07:45:00+00:00')
image(3, 'pages/hero-home.svg', 'Обложка главной', 2000, 1000, '2026-06-02T09:00:00+00:00')
image(3, 'pages/office.svg', 'Офис на Подоле', 1600, 1067, '2026-06-02T09:05:00+00:00')
image(3, 'pages/services.svg', 'Услуги', 1600, 900, '2026-06-04T12:00:00+00:00')

recount()

/** Counts are derived: every upload, move and delete would otherwise have to keep them. */
export function recount(): void {
  for (const node of flat()) {
    node.files_count = files.filter((file) => file.directory_id === node.id).length
  }
}

export function fileById(id: number): MediaFile | null {
  return files.find((file) => file.id === id) ?? null
}

export function fileByPath(path: string): MediaFile | null {
  return files.find((file) => file.path === path) ?? null
}

/**
 * A file made out of what was uploaded.
 *
 * The bytes are kept as they arrived and served back from the panel's own origin, so a picture
 * uploaded here behaves like one from a real disk: same key, same address, same field.
 */
export function receive(
  directoryId: number,
  fileName: string,
  mime: string,
  bytes: Buffer,
): MediaFile {
  const id = nextFileId++
  const extension = fileName.slice(fileName.lastIndexOf('.') + 1).toLowerCase()
  const stem = fileName.slice(
    0,
    fileName.lastIndexOf('.') === -1 ? undefined : fileName.lastIndexOf('.'),
  )
  const path = `uploads/${new Date().getFullYear()}/${id}-${slug(stem)}.${extension}`
  const address = `/fixtures/media/${path}`

  uploads.set(path, { bytes, mime })

  const file: MediaFile = {
    id,
    directory_id: directoryId,
    name: stem,
    file_name: `${slug(stem)}.${extension}`,
    extension,
    mime,
    type: kind(mime),
    size: bytes.length,
    width: null,
    height: null,
    path,
    url: address,
    thumb: mime.startsWith('image/') ? address : null,
    source: address,
    editable: false,
    has_original: false,
    duplicate: false,
    created_at: new Date().toISOString(),
  }

  files.unshift(file)
  recount()

  return file
}

function kind(mime: string): MediaFile['type'] {
  if (mime.startsWith('image/')) return 'image'
  if (mime.startsWith('video/')) return 'video'
  if (mime.startsWith('audio/')) return 'audio'

  return mime === 'application/pdf' || mime.includes('word') || mime.includes('sheet')
    ? 'document'
    : 'other'
}

/**
 * The bytes behind a key: what was uploaded, or a picture drawn from the name.
 *
 * `null` for a key nothing answers to — the caller turns that into a 404.
 */
export function bytesOf(path: string): { bytes: Buffer; mime: string } | null {
  const uploaded = uploads.get(path)

  if (uploaded !== undefined) {
    return uploaded
  }

  const file = fileByPath(path)

  if (file === null || file.extension !== 'svg') {
    return null
  }

  return {
    bytes: Buffer.from(placeholder(file.name, file.width ?? 1600, file.height ?? 900), 'utf8'),
    mime: 'image/svg+xml',
  }
}

/**
 * A picture with a face of its own.
 *
 * The hue comes from the name, so the same file is the same colour on every reload and no two
 * covers in a list look alike — which is the whole job a placeholder has in a playground.
 */
function placeholder(name: string, width: number, height: number): string {
  let hash = 0

  for (const letter of name) {
    hash = (hash * 31 + letter.codePointAt(0)!) % 360
  }

  const from = `hsl(${hash} 62% 46%)`
  const to = `hsl(${(hash + 48) % 360} 58% 30%)`
  const size = Math.round(Math.min(width, height) / 12)

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" role="img" aria-label="${escape(name)}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${from}" />
      <stop offset="1" stop-color="${to}" />
    </linearGradient>
  </defs>
  <rect width="${width}" height="${height}" fill="url(#g)" />
  <text x="50%" y="50%" fill="rgba(255,255,255,.92)" font-family="system-ui, sans-serif" font-size="${size}" font-weight="600" text-anchor="middle" dominant-baseline="middle">${escape(name)}</text>
</svg>`
}

function escape(text: string): string {
  return text.replace(/[<>&"]/g, (char) => `&#${char.codePointAt(0)};`)
}

function slug(text: string): string {
  return (
    text
      .toLowerCase()
      .replace(/[^a-z0-9а-яё]+/gi, '-')
      .replace(/^-|-$/g, '') || 'file'
  )
}
