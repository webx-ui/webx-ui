import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import type { IncomingMessage, ServerResponse } from 'node:http'
import type { Plugin } from 'vite'
import type {
  InboxField,
  InboxForm,
  InboxStatus,
  SubmissionCounts,
} from '../../../../packages/module-inbox/src/types'
import type { BlockType, BlockUsage } from '../../../../packages/module-blocks/src/types'
import type { PageRow } from '../../../../packages/module-pages/src/types'
import type { RubricRow, TagRow } from '../../../../packages/module-blog/src/types'
import type { MediaDirectory, MediaFile } from '../../../../packages/module-media/src/types'
import {
  blockGroups,
  blockTypes,
  blockVersions,
  clone as blockClone,
  draw as drawContent,
  renderTemplate,
  templateFailure,
  type BlockVersionRecord,
} from './blocks'
import {
  admins,
  countForms,
  eventsFor,
  fields,
  forms,
  statuses,
  submissions,
  valuesFor,
  type SubmissionRecord,
} from './inbox'
import {
  ancestorsOf,
  createPage,
  descendantsOf,
  homeId,
  pages,
  pathOf,
  recount,
  slugify,
  type PageRecord,
} from './pages'
import {
  PREFIX,
  articles,
  authors as blogAuthors,
  clone as blogClone,
  find as findArticle,
  ids as blogIds,
  recount as recountBlog,
  revision as articleRevision,
  row as articleRow,
  rubrics,
  tags,
  text as blogText,
  values as articleValues,
  type ArticleRecord,
} from './blog'
import {
  bytesOf,
  directories,
  fileById,
  fileByPath as mediaByPath,
  files as mediaFiles,
  find as findFolder,
  flat as mediaFolders,
  receive,
  recount as mediaRecount,
} from './media'
import { adminRows, listCalls, roles as adminRoles } from './agents'
import { dictionary, panelLocales } from './lang'
import { screen, screenNames } from './screens'

/**
 * The panel's backend, in memory.
 *
 * Every screen in `module-inbox`, `module-pages` and `module-blocks` talks to the panel's API
 * and to nothing else, so a server that answers it is the whole of what the playground needs
 * to run the real screens rather than copies of them. It answers over HTTP, through Vite,
 * because a swapped `fetch` hides exactly the things worth looking at here: the frame the rows
 * arrive a beat after the header, the skeleton nobody sees, the request in the network tab.
 *
 * It is a fixture, not an implementation: it validates nothing beyond what a screen needs to
 * see refused, and it forgets everything when the dev server restarts.
 */

/** Long enough to see a loading state, short enough to keep clicking. */
const DELAY = 220

/** The site's own languages: what a localized field switches between. */
const CONTENT = [
  { code: 'ru', name: 'Russian', nativeName: 'Русский', direction: 'ltr', default: true },
  { code: 'en', name: 'English', nativeName: 'English', direction: 'ltr', default: false },
]

const LANGUAGES: Record<string, [string, string]> = {
  de: ['German', 'Deutsch'],
  en: ['English', 'English'],
  es: ['Spanish', 'Español'],
  fr: ['French', 'Français'],
  it: ['Italian', 'Italiano'],
  pl: ['Polish', 'Polski'],
  pt: ['Portuguese', 'Português'],
  ru: ['Russian', 'Русский'],
  tr: ['Turkish', 'Türkçe'],
  uk: ['Ukrainian', 'Українська'],
}

/** The languages the interface can be drawn in — whichever the PHP packages have files for. */
const PANEL = panelLocales().map((code) => ({
  code,
  name: LANGUAGES[code]?.[0] ?? code,
  nativeName: LANGUAGES[code]?.[1] ?? code,
  direction: 'ltr',
  default: code === 'en',
}))

type Handler = (context: {
  params: string[]
  query: URLSearchParams
  body: Record<string, unknown>
  /** The language the panel says it is currently drawn in — its `X-Webx-Locale` header. */
  locale: string
}) => unknown

interface Route {
  method: string
  pattern: RegExp
  handler: Handler
}

const routes: Route[] = []

function on(method: string, pattern: string, handler: Handler): void {
  routes.push({
    method,
    pattern: new RegExp(`^${pattern}$`),
    handler,
  })
}

/* ------------------------------------------------------------------ the panel itself ----- */

/*
 * What the panel is, in the language it is being read in.
 *
 * Section names are translated on the server rather than in the dictionary the panel holds, so
 * a manifest fixed in English would leave the menu English in a Russian panel — and the menu is
 * half of what a section's own screen is looked at next to. The names, icons and order are the
 * ones the PHP modules answer with, so what is on screen here is what a site gets.
 */
on('GET', '/manifest', ({ locale }) => ({
  data: {
    title: 'WebX Demo',
    path: '/panel',
    apiPath: '/api/cms',
    locale,
    locales: CONTENT,
    panelLocales: PANEL,
    groups: [
      {
        id: 'blog',
        title: line(locale, 'webx-blog', 'module.group'),
        icon: 'newspaper',
        order: 300,
      },
      { id: 'system', title: line(locale, 'webx-admin', 'nav.system'), icon: 'gear', order: 900 },
    ],
    modules: [
      {
        id: 'inbox',
        title: line(locale, 'webx-inbox', 'module.title'),
        icon: 'mail',
        order: 100,
        group: null,
        permissions: ['inbox.view', 'inbox.update', 'inbox.manage'],
        meta: {},
      },
      {
        id: 'pages',
        title: line(locale, 'webx-pages', 'module.title'),
        icon: 'file',
        order: 200,
        group: null,
        permissions: ['pages.view', 'pages.manage'],
        meta: {},
      },
      {
        id: 'blocks',
        title: line(locale, 'webx-blocks', 'module.title'),
        icon: 'grid',
        order: 600,
        group: 'system',
        permissions: ['blocks.view', 'blocks.manage'],
        /* Nothing: this demo has no bundle of its own, so a block asking for a library
           through `webx.use()` would wait forever. The editor says so in as many words. */
        meta: { groups: blockGroups, editing: true, provides: [] },
      },
      {
        id: 'articles',
        title: line(locale, 'webx-blog', 'module.articles'),
        icon: 'file-text',
        order: 300,
        group: 'blog',
        permissions: ['blog.articles.view', 'blog.articles.manage'],
        meta: {},
      },
      {
        id: 'rubrics',
        title: line(locale, 'webx-blog', 'module.rubrics'),
        icon: 'folder',
        order: 310,
        group: 'blog',
        permissions: ['blog.taxonomy.manage'],
        meta: {},
      },
      {
        id: 'tags',
        title: line(locale, 'webx-blog', 'module.tags'),
        icon: 'tag',
        order: 320,
        group: 'blog',
        permissions: ['blog.taxonomy.manage'],
        meta: {},
      },
      {
        id: 'media',
        title: line(locale, 'webx-media', 'module.title'),
        icon: 'folder',
        order: 500,
        group: 'system',
        permissions: ['media.view', 'media.upload', 'media.manage'],
        meta: {},
      },
      {
        id: 'seo',
        title: line(locale, 'webx-seo', 'module.title'),
        icon: 'search',
        order: 700,
        group: 'system',
        permissions: ['seo.view', 'seo.manage'],
        meta: {},
      },
      {
        id: 'admins',
        title: line(locale, 'webx-auth', 'module.title'),
        icon: 'users',
        order: 900,
        group: 'system',
        permissions: ['admins.view', 'admins.manage', 'admins.audit'],
        meta: { roles: true, loginLog: true },
      },
    ],
    screens: screenNames,
  },
}))

on('GET', '/locales', () => ({ data: { panel: PANEL, content: CONTENT } }))

/* The words the server owns — every screen label among them — read off the PHP packages. */
on('GET', '/translations/([\\w-]+)', ({ params }) => ({
  data: { locale: params[0], fallback: 'en', namespaces: dictionary(params[0]) },
}))

on('GET', '/screens/([\\w.-]+)', ({ params }) => {
  const tree = screen(params[0])

  if (tree === null) {
    throw new HttpFailure(404, `No screen named ${params[0]}.`)
  }

  return { data: { screen: params[0], root: tree } }
})

/* ------------------------------------------------------------------------------ pages ----- */

on('GET', '/pages', ({ query }) => {
  const search = (query.get('search') ?? '').trim().toLowerCase()
  const status = query.get('status') ?? ''
  const trashed = query.get('trashed') === '1'
  const flat = query.get('flat') === '1'
  const parent = query.get('parent')

  const live = [...pages.values()].filter((record) => (record.row.deleted_at === null) !== trashed)

  if (trashed) {
    return { data: { home: null, items: live.map((record) => record.row) } }
  }

  if (search !== '') {
    const matches = live.filter(
      (record) =>
        record.row.title.toLowerCase().includes(search) ||
        (record.row.path ?? '').toLowerCase().includes(search),
    )

    return { data: { home: null, items: withStatus(matches, status).map((record) => record.row) } }
  }

  if (flat) {
    const home = pages.get(homeId)?.row ?? null
    const rest = live.filter((record) => record.row.id !== homeId)

    return { data: { home, items: withStatus(rest, status).map((record) => record.row) } }
  }

  const parentId = parent === null ? homeId : Number(parent)
  const children = live.filter((record) => record.row.parent_id === parentId)

  return {
    data: {
      home: parent === null ? (pages.get(homeId)?.row ?? null) : null,
      items: withStatus(children, status).map((record) => record.row),
    },
  }
})

on('POST', '/pages', ({ body }) => {
  const record = createPage({
    title: String(body.title ?? 'Новая страница'),
    slug: typeof body.slug === 'string' && body.slug !== '' ? body.slug : undefined,
    parent_id: typeof body.parent_id === 'number' ? body.parent_id : null,
  })

  return { data: record.row }
})

on('GET', '/pages/(\\d+)', ({ params }) => {
  const record = page(params[0])

  return {
    data: {
      page: record.row,
      ancestors: ancestorsOf(record.row),
      values: withSeoImage(record.values),
      revision: revisionOf(record),
      address_prefix: prefixOf(record.row),
      preview_url: `/preview/page/${record.row.id}`,
    },
  }
})

on('PUT', '/pages/(\\d+)', ({ params, body }) => {
  const record = page(params[0])
  const sent = (body.values ?? {}) as Record<string, unknown>

  if (typeof body.revision === 'string' && body.revision !== revisionOf(record)) {
    throw new HttpFailure(409, 'Somebody else saved this page while you were editing it.', {
      page: record.row,
      ancestors: ancestorsOf(record.row),
      values: withSeoImage(record.values),
      revision: revisionOf(record),
      address_prefix: prefixOf(record.row),
      preview_url: `/preview/page/${record.row.id}`,
    })
  }

  record.values = { ...record.values, ...sent }
  record.values.seo = storedSeo(record.values.seo)
  record.row.title = localized(record.values.title) || record.row.title
  record.row.slug = record.row.is_home ? '' : localized(record.values.slug)
  record.row.path = pathOf(record.row)
  record.row.url = `https://webx-demo.test/${record.row.path}`
  record.row.updated_at = new Date().toISOString()
  record.row.status = record.row.status === 'draft' ? 'draft' : 'modified'

  return {
    data: {
      page: record.row,
      ancestors: ancestorsOf(record.row),
      values: withSeoImage(record.values),
      revision: revisionOf(record),
      address_prefix: prefixOf(record.row),
      preview_url: `/preview/page/${record.row.id}`,
    },
  }
})

on('DELETE', '/pages/(\\d+)', ({ params }) => {
  const record = page(params[0])
  const now = new Date().toISOString()
  const branch = [record, ...descendantsOf(record.row.id)]

  for (const node of branch) {
    node.row.deleted_at = now
    node.row.trashed_with = node.row.id === record.row.id ? null : record.row.id
  }

  recount()

  return { data: { trashed: branch.length } }
})

on('POST', '/pages/(\\d+)/restore', ({ params }) => {
  const record = page(params[0])
  const branch = [record, ...descendantsOf(record.row.id)]

  for (const node of branch) {
    node.row.deleted_at = null
    node.row.trashed_with = null
  }

  recount()

  return { data: { restored: branch.length } }
})

on('POST', '/pages/(\\d+)/move', ({ params, body }) => {
  const record = page(params[0])
  const target = page(String(body.target))
  const zone = String(body.zone)

  record.row.parent_id = zone === 'inside' ? target.row.id : target.row.parent_id
  record.row.depth = ancestorsOf(record.row).length
  record.row.path = pathOf(record.row)
  record.row.url = `https://webx-demo.test/${record.row.path}`

  const moved = descendantsOf(record.row.id)

  for (const node of moved) {
    node.row.depth = ancestorsOf(node.row).length
    node.row.path = pathOf(node.row)
    node.row.url = `https://webx-demo.test/${node.row.path}`
  }

  recount()

  return { data: { page: record.row, addresses_changed: moved.length + 1 } }
})

on('POST', '/pages/(\\d+)/duplicate', ({ params }) => {
  const record = page(params[0])
  const copy = createPage({
    title: `${record.row.title} — копия`,
    slug: `${record.row.slug || slugify(record.row.title)}-copy`,
    parent_id: record.row.parent_id,
  })

  copy.values = { ...record.values, title: { ru: copy.row.title, en: copy.row.title } }

  return { data: copy.row }
})

on('POST', '/pages/(\\d+)/publish', ({ params }) => {
  const record = page(params[0])
  const now = new Date().toISOString()

  record.row.status = 'published'
  record.row.published_at = now
  record.versions.unshift({
    number: (record.versions[0]?.number ?? 0) + 1,
    created_at: now,
    author: 'Анна Ковальчук',
    source: 'panel',
    comment: null,
    is_pinned: false,
  })

  return { data: record.row }
})

on('POST', '/pages/(\\d+)/unpublish', ({ params }) => {
  const record = page(params[0])

  record.row.status = 'draft'
  record.row.published_at = null

  return { data: record.row }
})

on('GET', '/pages/(\\d+)/versions', ({ params }) => ({ data: page(params[0]).versions }))

on('POST', '/pages/(\\d+)/versions/(\\d+)/restore', ({ params }) => {
  const record = page(params[0])

  record.row.status = 'modified'
  record.row.updated_at = new Date().toISOString()

  return {
    data: {
      page: record.row,
      ancestors: ancestorsOf(record.row),
      values: withSeoImage(record.values),
      revision: revisionOf(record),
      address_prefix: prefixOf(record.row),
      preview_url: `/preview/page/${record.row.id}`,
    },
  }
})

/* ----------------------------------------------------------------------------- blocks ----- */

on('GET', '/blocks', () => ({ data: blockTypes.map(withoutContent) }))

/* What the panel would get from a server that keeps one: the type drawn on its own sample,
   ready for the card's iframe. Without it the section is eight grey rectangles. */
function thumbnailOf(type: BlockType): BlockType['thumbnail'] {
  if (type.content === undefined) {
    return null
  }

  const drawn = drawContent(type.content, type.content.sample)

  return { html: drawn.html, styles: drawn.styles }
}

/* With content, because the field draws a block's form from its schema — and with a picture,
   because the picker offers types by their picture the same way the section lists them. */
on('GET', '/blocks/catalog', () => ({
  data: blockTypes
    .filter((type) => type.is_enabled && type.published !== null)
    .map((type) => ({ ...type, thumbnail: thumbnailOf(type) })),
}))

on('POST', '/blocks', ({ body }) => {
  const now = new Date().toISOString()
  const type: BlockType = {
    id: Math.max(...blockTypes.map((item) => item.id)) + 1,
    slug: String(body.slug ?? 'block'),
    title: String(body.title ?? 'Новый блок'),
    description: (body.description as string | null) ?? null,
    icon: (body.icon as string | null) ?? 'square',
    group: String(body.group ?? 'content'),
    sort: blockTypes.length * 10 + 10,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft: {
      number: 1,
      source: 'panel',
      comment: null,
      author_id: 1,
      author: 'Анна Ковальчук',
      created_at: now,
    },
    published: null,
    usage_count: 0,
    thumbnail: null,
    created_at: now,
    updated_at: now,
    content: {
      schema: [],
      template: '<section class="block">\n</section>\n',
      styles: '',
      script: null,
      sample: {},
    },
  }

  blockTypes.push(type)

  return { data: counted(type) }
})

on('GET', '/blocks/(\\d+)', ({ params }) => ({ data: counted(blockType(params[0])) }))

/** The editor asks how many entities stand on the type; the answer is taken, not stored. */
function counted(type: BlockType): BlockType {
  type.usage_count = usageOf(type.slug).length

  return type
}

on('PUT', '/blocks/(\\d+)', ({ params, body }) => {
  const type = blockType(params[0])
  const content = body.content as Partial<NonNullable<BlockType['content']>> | undefined

  Object.assign(type, omit(body, ['content', 'comment']))

  if (content !== undefined && type.content !== undefined) {
    type.content = { ...type.content, ...content }
  }

  type.updated_at = new Date().toISOString()

  /* A save without a draft opens one; a save on top of a draft stays that same number, the
     way the module's own versioning works — the history gets a row per draft, not per key. */
  const opened = type.draft === null
  const record = history(type).find((item) => item.number === type.draft?.number)

  type.draft = {
    number: opened ? next(type) : (type.draft?.number ?? 1),
    source: 'panel',
    comment: (body.comment as string | null) ?? null,
    author_id: 1,
    author: 'Анна Ковальчук',
    created_at: type.updated_at,
  }

  if (opened || record === undefined) {
    history(type).push({ ...type.draft, content: blockClone(contentOf(type)) })
  } else {
    Object.assign(record, { ...type.draft, content: blockClone(contentOf(type)) })
  }

  return { data: counted(type) }
})

on('DELETE', '/blocks/(\\d+)', ({ params }) => {
  const type = blockType(params[0])

  if (type.usage_count > 0) {
    throw new HttpFailure(422, 'This type is still standing on pages.', undefined, {
      slug: ['Used on ' + type.usage_count + ' pages.'],
    })
  }

  blockTypes.splice(blockTypes.indexOf(type), 1)

  return { data: null }
})

on('POST', '/blocks/(\\d+)/publish', ({ params }) => {
  const type = blockType(params[0])
  const failure = templateFailure(contentOf(type).template)

  /* The gate before a version goes live (§15): a template that does not compile is a 422 in
     the shape the panel reads — the sentence under `template`, and the line it is on. */
  if (failure !== null) {
    throw new HttpFailure(
      422,
      failure.reason,
      undefined,
      { template: [failure.reason] },
      { line: failure.line, entity: null },
    )
  }

  type.published = type.draft ?? type.published
  type.draft = null
  type.updated_at = new Date().toISOString()

  return { data: counted(type) }
})

on('POST', '/blocks/(\\d+)/render', ({ params, body }) => {
  const type = blockType(params[0])
  const content = {
    ...type.content,
    ...((body.content ?? {}) as Record<string, unknown>),
  } as NonNullable<BlockType['content']>
  const values = (body.values ?? content.sample ?? {}) as Record<string, unknown>
  const drawn = drawContent(content, values)

  return {
    data: {
      html: drawn.html,
      /* The styles of everything in the picture, not only of the type being edited: a
         container drawn without its children's CSS is a stack of bare paragraphs. */
      styles: drawn.styles,
      /* Wrapped the way the server wraps it (`Bundles::wrapScript`): the field holds the body
         of the initialiser, and the frame is handed the initialiser. Sent raw, it is a `el is
         not defined` in a console nobody has open. */
      script:
        content.script === null || content.script.trim() === ''
          ? null
          : `webx.block(${JSON.stringify(type.slug)}, async (el, values) => {\n${content.script.trim()}\n});\n`,
      runtime: '/blocks-runtime.js',
      version: type.published?.number ?? type.draft?.number ?? 1,
    },
  }
})

on('GET', '/blocks/(\\d+)/usage', ({ params }) => ({ data: usageOf(blockType(params[0]).slug) }))

/**
 * Where a type stands, counted rather than remembered.
 *
 * Both kinds of entity, because both are made of blocks: a count that skipped the articles
 * would tell an editor a type is free to delete while the blog is standing on it. Nested
 * blocks count too — a type inside a container is on the page as much as one at the root.
 */
function usageOf(slug: string): BlockUsage[] {
  const used: BlockUsage[] = []

  for (const record of pages.values()) {
    if (record.row.deleted_at === null && holds((record.values.blocks ?? []) as Block[], slug)) {
      used.push({
        model: 'page',
        id: record.row.id,
        title: record.row.title,
        published: record.row.status === 'published',
      })
    }
  }

  for (const record of articles) {
    if (record.deleted_at === null && holds((record.values.blocks ?? []) as Block[], slug)) {
      used.push({
        model: 'article',
        id: record.id,
        title: blogText(record.values.title, 'ru') || `#${record.id}`,
        published: record.status === 'published' || record.status === 'modified',
      })
    }
  }

  return used
}

function holds(nodes: Block[], slug: string): boolean {
  return nodes.some((node) => types(node).includes(slug))
}

/* Newest first: the history reads downwards from what is being worked on. The content of a
   version is not in the list — that is what asking for one of them is for. */
on('GET', '/blocks/(\\d+)/versions', ({ params }) => ({
  data: history(blockType(params[0]))
    .map((record) => ({ ...record, content: undefined }))
    .reverse(),
}))

on('GET', '/blocks/(\\d+)/versions/(\\d+)', ({ params }) => ({
  data: version(blockType(params[0]), Number(params[1])),
}))

/* Restoring is a save of old content, not a pointer moved: the type gets a new draft with
   what that version held, and publishing it stays the separate step it always is. */
on('POST', '/blocks/(\\d+)/versions/(\\d+)/restore', ({ params }) => {
  const type = blockType(params[0])
  const restored = version(type, Number(params[1]))

  type.content = blockClone(restored.content)
  type.updated_at = new Date().toISOString()
  type.draft = {
    number: next(type),
    source: 'panel',
    comment: `Восстановлена версия ${restored.number}`,
    author_id: 1,
    author: 'Анна Ковальчук',
    created_at: type.updated_at,
  }

  history(type).push({ ...type.draft, content: blockClone(type.content) })

  return { data: counted(type) }
})

/** The content a type is holding — an empty one for a type that somehow has none. */
function contentOf(type: BlockType): NonNullable<BlockType['content']> {
  return type.content ?? { schema: [], template: '', styles: '', script: null, sample: {} }
}

function history(type: BlockType): BlockVersionRecord[] {
  const versions = blockVersions.get(type.id)

  if (versions === undefined) {
    const started: BlockVersionRecord[] = []

    blockVersions.set(type.id, started)

    return started
  }

  return versions
}

function version(type: BlockType, number: number): BlockVersionRecord {
  const found = history(type).find((record) => record.number === number)

  if (found === undefined) {
    throw new HttpFailure(404, 'No such version.')
  }

  return found
}

/** One past the highest number the type has ever had — numbers are not reused. */
function next(type: BlockType): number {
  return Math.max(0, ...history(type).map((record) => record.number)) + 1
}

/* ------------------------------------------------------------------------------ inbox ----- */

on('GET', '/inbox/forms', () => {
  countForms()

  return { data: forms }
})

on('POST', '/inbox/forms', ({ body }) => {
  const form: InboxForm = {
    id: Math.max(...forms.map((item) => item.id)) + 1,
    slug: String(body.slug ?? 'form'),
    title: (body.title ?? { ru: 'Новая форма' }) as InboxForm['title'],
    is_enabled: body.is_enabled !== false,
    options: (body.options ?? {}) as InboxForm['options'],
    position: forms.length + 1,
    submissions_count: 0,
    unread_count: 0,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  }

  forms.push(form)

  return { data: form }
})

on('POST', '/inbox/forms/sorting', ({ body }) => {
  order(forms, body.ids as number[])

  return { data: null }
})

on('GET', '/inbox/forms/(\\d+)', ({ params }) => {
  const item = form(params[0])

  return { data: { ...item, fields: fields.filter((field) => field.form_id === item.id) } }
})

on('PUT', '/inbox/forms/(\\d+)', ({ params, body }) => {
  const item = form(params[0])

  Object.assign(item, omit(body, []))
  item.updated_at = new Date().toISOString()

  return { data: { ...item, fields: fields.filter((field) => field.form_id === item.id) } }
})

on('DELETE', '/inbox/forms/(\\d+)', ({ params }) => {
  const item = form(params[0])

  if (submissions.some((record) => record.form_id === item.id)) {
    throw new HttpFailure(422, 'This form has submissions.', undefined, {
      form: ['Switch it off instead: its submissions are still readable.'],
    })
  }

  forms.splice(forms.indexOf(item), 1)

  return { data: null }
})

on('POST', '/inbox/forms/(\\d+)/duplicate', ({ params }) => {
  const item = form(params[0])
  const copy: InboxForm = {
    ...item,
    id: Math.max(...forms.map((each) => each.id)) + 1,
    slug: `${item.slug}-copy`,
    title: { ru: `${item.title.ru ?? item.slug} — копия` },
    is_enabled: false,
    position: forms.length + 1,
    submissions_count: 0,
    unread_count: 0,
  }

  forms.push(copy)

  for (const field of fields.filter((each) => each.form_id === item.id)) {
    fields.push({ ...field, id: nextFieldId(), form_id: copy.id })
  }

  return { data: copy }
})

on('GET', '/inbox/forms/(\\d+)/fields', ({ params }) => ({
  data: fields.filter((field) => field.form_id === Number(params[0])),
}))

on('POST', '/inbox/forms/(\\d+)/fields', ({ params, body }) => {
  const formId = Number(params[0])
  const field: InboxField = {
    id: nextFieldId(),
    form_id: formId,
    name: (body.name as string | null) ?? null,
    key: String(body.name ?? `f${nextFieldId()}`),
    type: (body.type ?? 'text') as InboxField['type'],
    title: (body.title ?? {}) as InboxField['title'],
    placeholder: (body.placeholder ?? {}) as InboxField['placeholder'],
    help: (body.help ?? {}) as InboxField['help'],
    options: (body.options ?? {}) as InboxField['options'],
    is_enabled: body.is_enabled !== false,
    is_required: body.is_required === true,
    is_fullsize: body.is_fullsize === true,
    in_table: body.in_table === true,
    position: fields.filter((item) => item.form_id === formId).length + 1,
  }

  fields.push(field)

  return { data: field }
})

on('POST', '/inbox/forms/(\\d+)/fields/sorting', ({ body }) => {
  order(fields, body.ids as number[])

  return { data: null }
})

on('PUT', '/inbox/fields/(\\d+)', ({ params, body }) => {
  const field = fields.find((item) => item.id === Number(params[0]))

  if (field === undefined) {
    throw new HttpFailure(404, 'No such field.')
  }

  Object.assign(field, omit(body, []))

  return { data: field }
})

on('DELETE', '/inbox/fields/(\\d+)', ({ params }) => {
  const index = fields.findIndex((item) => item.id === Number(params[0]))

  if (index >= 0) {
    fields.splice(index, 1)
  }

  return { data: null }
})

on('GET', '/inbox/statuses', () => {
  countForms()

  return { data: statuses }
})

on('POST', '/inbox/statuses', ({ body }) => {
  const status: InboxStatus = {
    id: Math.max(...statuses.map((item) => item.id)) + 1,
    key: String(body.key ?? 'status'),
    title: (body.title ?? {}) as InboxStatus['title'],
    color: (body.color ?? 'default') as InboxStatus['color'],
    is_default: body.is_default === true,
    is_spam: body.is_spam === true,
    is_closed: body.is_closed === true,
    position: statuses.length + 1,
    submissions_count: 0,
  }

  statuses.push(status)

  return { data: status }
})

on('POST', '/inbox/statuses/sorting', ({ body }) => {
  order(statuses, body.ids as number[])

  return { data: null }
})

on('PUT', '/inbox/statuses/(\\d+)', ({ params, body }) => {
  const status = statuses.find((item) => item.id === Number(params[0]))

  if (status === undefined) {
    throw new HttpFailure(404, 'No such status.')
  }

  Object.assign(status, omit(body, []))

  return { data: status }
})

on('DELETE', '/inbox/statuses/(\\d+)', ({ params }) => {
  const status = statuses.find((item) => item.id === Number(params[0]))

  if (status !== undefined && submissions.some((record) => record.status_id === status.id)) {
    throw new HttpFailure(422, 'Submissions are still in this status.', undefined, {
      key: ['Move them somewhere else first.'],
    })
  }

  if (status !== undefined) {
    statuses.splice(statuses.indexOf(status), 1)
  }

  return { data: null }
})

on('GET', '/inbox/recipients', () => ({ data: admins }))

/* ------------------------------------------------------------------ administrators ------- */

/*
 * The people, and the trail of what their agents did. Enough of `module-auth`'s API for its
 * section to open: the list with its search and filters, the roles the form offers, and the
 * call log with the filters the server derives from it. Nobody signs in here — the session is
 * the plugin in `main.ts` — so there is no `me`, `login` or `logout`.
 */

on('GET', '/auth/roles', () => ({ data: adminRoles }))

on('GET', '/auth/admins/(\\d+)', ({ params }) => {
  const found = adminRows.find((row) => row.id === Number(params[0]))

  if (found === undefined) throw new HttpFailure(404, 'No such administrator.')

  return { data: found }
})

on('GET', '/auth/admins', ({ query }) => {
  const search = (query.get('q') ?? '').trim().toLowerCase()
  const role = query.get('role')
  const active = query.get('active')
  const sort = query.get('sort') ?? 'name'
  const page = Math.max(1, Number(query.get('page') ?? 1))
  const perPage = Math.min(100, Math.max(1, Number(query.get('per_page') ?? 20)))

  let found = adminRows.filter(
    (row) =>
      (search === '' ||
        row.name.toLowerCase().includes(search) ||
        row.email.toLowerCase().includes(search)) &&
      (!role || row.roles.some((one) => one.slug === role)) &&
      (!active || row.is_active === (active === 'yes')),
  )

  const key = sort.replace(/^-/, '') as 'name' | 'email' | 'last_login_at'
  found = [...found].sort(
    (one, two) =>
      String(one[key] ?? '').localeCompare(String(two[key] ?? '')) *
      (sort.startsWith('-') ? -1 : 1),
  )

  const total = found.length
  const from = (page - 1) * perPage
  const rows = found.slice(from, from + perPage)

  return {
    data: rows,
    meta: {
      current_page: page,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      per_page: perPage,
      total,
      from: total === 0 ? null : from + 1,
      to: total === 0 ? null : from + rows.length,
    },
  }
})

on('GET', '/auth/mcp-calls', ({ query }) => listCalls(query))

on('GET', '/inbox/forms/(\\d+)/submissions', ({ params, query }) => {
  const formId = Number(params[0])
  const all = submissions.filter((record) => record.form_id === formId)
  const view = query.get('view') ?? ''
  const search = (query.get('search') ?? '').toLowerCase()
  const assignee = query.get('assignee')
  const perPage = Number(query.get('per_page') ?? 25)
  const page = Number(query.get('page') ?? 1)

  const spam = new Set(statuses.filter((status) => status.is_spam).map((status) => status.id))

  let found = all.filter((record) => {
    if (view === 'unread') return !record.is_read && !spam.has(record.status_id)
    if (view !== '' && view !== 'all') {
      return statuses.find((status) => status.key === view)?.id === record.status_id
    }

    return !spam.has(record.status_id)
  })

  if (search !== '') {
    found = found.filter((record) =>
      Object.values(record.values).some((value) => (value ?? '').toLowerCase().includes(search)),
    )
  }

  if (assignee !== null && assignee !== '') {
    found = found.filter((record) =>
      assignee === 'none' ? record.assignee_id === null : record.assignee_id === Number(assignee),
    )
  }

  found = [...found].sort((one, two) => sortValue(two, query) - sortValue(one, query))

  const total = found.length
  const from = (page - 1) * perPage
  const rows = found.slice(from, from + perPage)

  return {
    data: rows.map(row),
    meta: {
      current_page: page,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      per_page: perPage,
      total,
      from: total === 0 ? null : from + 1,
      to: total === 0 ? null : from + rows.length,
    },
    columns: fields
      .filter((field) => field.form_id === formId && field.in_table)
      .map((field) => ({ key: field.key, label: field.title.ru ?? field.key, type: field.type })),
    counts: counts(all),
  }
})

on('POST', '/inbox/forms/(\\d+)/submissions', ({ params, body }) => {
  const formId = Number(params[0])
  const now = new Date().toISOString()
  const record: SubmissionRecord = {
    id: Math.max(...submissions.map((item) => item.id)) + 1,
    form_id: formId,
    status_id: statuses.find((status) => status.is_default)?.id ?? 1,
    assignee_id: null,
    is_read: true,
    source: 'panel',
    values: (body.fields ?? {}) as Record<string, string | null>,
    files: [],
    meta: { locale: 'ru' },
    events: [],
    notified_at: null,
    notify_error: null,
    created_at: now,
    updated_at: now,
  }

  submissions.unshift(record)
  countForms()

  return { data: detail(record) }
})

on('POST', '/inbox/submissions/mass', ({ body }) => {
  const ids = new Set((body.ids ?? []) as number[])
  const action = String(body.action)
  let count = 0

  for (const record of [...submissions]) {
    if (!ids.has(record.id)) continue

    count += 1

    if (action === 'read') record.is_read = true
    if (action === 'unread') record.is_read = false
    if (action === 'status') record.status_id = Number(body.status_id)
    if (action === 'delete') submissions.splice(submissions.indexOf(record), 1)
  }

  countForms()

  return { data: { count } }
})

on('GET', '/inbox/submissions/(\\d+)', ({ params, query }) => {
  const record = submission(params[0])

  record.is_read = true
  countForms()

  return { data: detail(record, query) }
})

on('PUT', '/inbox/submissions/(\\d+)', ({ params, body }) => {
  const record = submission(params[0])

  if (typeof body.status_id === 'number') record.status_id = body.status_id
  if ('assignee_id' in body) record.assignee_id = (body.assignee_id as number | null) ?? null

  if (typeof body.values === 'object' && body.values !== null) {
    record.values = { ...record.values, ...(body.values as Record<string, string | null>) }
  }

  record.updated_at = new Date().toISOString()
  record.events = []
  countForms()

  return { data: detail(record) }
})

on('DELETE', '/inbox/submissions/(\\d+)', ({ params }) => {
  const record = submission(params[0])

  submissions.splice(submissions.indexOf(record), 1)
  countForms()

  return { data: null }
})

/* ------------------------------------------------------------------------------ notes ----- */

const notes = new Map<
  string,
  { id: number; body: string; author: unknown; created_at: string; updated_at: string | null }[]
>()

on('GET', '/entities/([\\w-]+)/(\\d+)/notes', ({ params }) => ({
  data: notes.get(`${params[0]}:${params[1]}`) ?? [],
}))

on('POST', '/entities/([\\w-]+)/(\\d+)/notes', ({ params, body }) => {
  const key = `${params[0]}:${params[1]}`
  const list = notes.get(key) ?? []
  const note = {
    id: Date.now(),
    body: String(body.body ?? ''),
    author: { id: 1, name: 'Анна Ковальчук', avatar: null },
    created_at: new Date().toISOString(),
    updated_at: null,
  }

  list.unshift(note)
  notes.set(key, list)

  return { data: note }
})

on('PUT', '/notes/(\\d+)', ({ params, body }) => {
  for (const list of notes.values()) {
    const note = list.find((item) => item.id === Number(params[0]))

    if (note !== undefined) {
      note.body = String(body.body ?? '')
      note.updated_at = new Date().toISOString()

      return { data: note }
    }
  }

  throw new HttpFailure(404, 'No such note.')
})

on('DELETE', '/notes/(\\d+)', ({ params }) => {
  for (const list of notes.values()) {
    const index = list.findIndex((item) => item.id === Number(params[0]))

    if (index >= 0) {
      list.splice(index, 1)
    }
  }

  return { data: null }
})

/* -------------------------------------------------------------------------------- blog ----- */

/**
 * The fields that go into the draft, and the ones that do not.
 *
 * Rubrics, tags, related articles and the pin take effect when they are saved — a rubric is a
 * row in a pivot and there is no such thing as half a row — so only these five decide whether
 * an article on the site has edits waiting.
 */
const DRAFTED = ['title', 'slug', 'lead', 'blocks', 'cover'] as const

on('GET', '/blog/articles', ({ query, locale }) => {
  const trashed = query.get('trashed') === '1'
  const search = (query.get('q') ?? '').trim().toLowerCase()
  const status = query.get('status') ?? ''
  const rubric = number(query.get('rubric'))
  const word = number(query.get('tag'))
  const writer = number(query.get('author'))
  const sort = query.get('sort') ?? ''
  const perPage = Number(query.get('per_page') ?? 20)
  const current = Number(query.get('page') ?? 1)

  let found = articles.filter((record) => (record.deleted_at === null) !== trashed)

  if (search !== '') {
    found = found.filter((record) => {
      const row = articleRow(record, locale)

      return `${row.title} ${row.slug}`.toLowerCase().includes(search)
    })
  }

  if (status !== '') found = found.filter((record) => record.status === status)
  if (rubric !== null) found = found.filter((record) => blogIds(record, 'rubrics').includes(rubric))
  if (word !== null) found = found.filter((record) => blogIds(record, 'tags').includes(word))
  if (writer !== null) found = found.filter((record) => record.values.author_id === writer)

  found = [...found].sort(byArticle(sort, locale))

  const total = found.length
  const from = (current - 1) * perPage
  const rows = found.slice(from, from + perPage)

  return {
    data: rows.map((record) => articleRow(record, locale)),
    meta: {
      current_page: current,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      per_page: perPage,
      total,
      from: total === 0 ? null : from + 1,
      to: total === 0 ? null : from + rows.length,
    },
    /* Everything the three dropdowns can be set to, whatever this page happens to hold. */
    filters: {
      rubrics: rubrics.map((row) => ({
        id: row.id,
        title: blogText(row.title, locale) || row.name,
      })),
      tags: tags.map((row) => ({ id: row.id, title: blogText(row.titles, locale) || row.title })),
      authors: blogAuthors.map((one) => ({ id: one.id, title: one.title })),
    },
  }
})

on('POST', '/blog/articles', ({ body, locale }) => {
  const title = String(body.title ?? 'Новая статья')
  const slug = typeof body.slug === 'string' && body.slug !== '' ? body.slug : slugify(title)
  const now = new Date().toISOString()

  const record: ArticleRecord = {
    id: Math.max(0, ...articles.map((one) => one.id)) + 1,
    values: {
      title: { ru: title, en: title },
      slug: { ru: slug, en: slug },
      lead: { ru: '', en: '' },
      blocks: [],
      cover: null,
      author_id: 1,
      pinned: false,
      published_at: null,
      rubrics: [],
      tags: [],
      related: [],
      seo: {},
    },
    live: null,
    status: 'draft',
    published_at: null,
    updated_at: now,
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  articles.unshift(record)
  recountBlog()

  return { data: articleRow(record, locale) }
})

on('GET', '/blog/articles/(\\d+)', ({ params, locale }) => ({
  data: articleDetail(article(params[0]), locale),
}))

on('PUT', '/blog/articles/(\\d+)', ({ params, body, locale }) => {
  const record = article(params[0])
  const sent = (body.values ?? {}) as Record<string, unknown>

  if (typeof body.revision === 'string' && body.revision !== articleRevision(record)) {
    throw new HttpFailure(
      409,
      'Кто-то сохранил эту статью, пока вы её редактировали.',
      articleDetail(record, locale),
    )
  }

  /* Only what travelled: saving one tab must not empty another. */
  record.values = { ...record.values, ...sent }
  record.updated_at = new Date().toISOString()

  // An article is never related to itself — the list under it would offer the page the reader
  // is already on.
  record.values.related = blogIds(record, 'related').filter((id) => id !== record.id)

  /*
   * The key alone, which is what a described screen stores: the media field sends the address
   * beside it so it can draw the picture it just picked, and a fixture that kept that address
   * would be storing a link that expires — exactly the thing the key exists to avoid.
   */
  const picked = record.values.cover as { path?: unknown } | null

  record.values.cover = typeof picked?.path === 'string' ? { path: picked.path } : null
  record.values.seo = storedSeo(record.values.seo)

  /*
   * The day is the column for an article that has been on the site, and the draft for one that
   * has not: `published_at` is what "on the site" means, so an article that has never been
   * there has no column to move and its day waits until somebody publishes.
   */
  const day = record.values.published_at

  if (record.published_at !== null && typeof day === 'string' && day !== '') {
    record.published_at = day
  }

  restate(record)
  recountBlog()

  return { data: articleDetail(record, locale) }
})

on('DELETE', '/blog/articles/(\\d+)', ({ params }) => {
  const record = article(params[0])

  record.deleted_at = new Date().toISOString()
  recountBlog()

  return { data: null }
})

on('POST', '/blog/articles/(\\d+)/restore', ({ params, locale }) => {
  const record = article(params[0])

  record.deleted_at = null
  recountBlog()

  return { data: articleRow(record, locale) }
})

/* Throw away what is waiting and keep what the site is showing (§10). */
on('POST', '/blog/articles/(\\d+)/discard', ({ params, locale }) => {
  const record = article(params[0])

  if (record.live !== null) {
    for (const field of DRAFTED) {
      record.values[field] = blogClone(record.live[field])
    }
  }

  record.updated_at = new Date().toISOString()
  restate(record)

  return { data: articleDetail(record, locale) }
})

on('POST', '/blog/articles/(\\d+)/publish', ({ params, body, locale }) => {
  const record = article(params[0])
  const now = new Date().toISOString()
  const at = typeof body.at === 'string' && body.at !== '' ? body.at : now
  const number = (record.versions[0]?.number ?? 0) + 1

  record.published_at = at
  record.values.published_at = at
  // A day in the future means the article does not appear — it waits (§7).
  record.status = new Date(at).valueOf() > Date.now() ? 'scheduled' : 'published'
  record.live = blogClone(record.values)
  record.updated_at = now
  record.snapshots[number] = blogClone(record.values)
  record.versions.unshift({
    number,
    created_at: now,
    author: 'Анна Ковальчук',
    source: 'panel',
    comment: null,
    is_pinned: false,
  })

  return { data: articleRow(record, locale) }
})

on('POST', '/blog/articles/(\\d+)/unpublish', ({ params, locale }) => {
  const record = article(params[0])

  // Not "draft": the article was on the site this morning, and only its history tells the two
  // apart (§10).
  record.status = 'unpublished'
  record.updated_at = new Date().toISOString()

  return { data: articleRow(record, locale) }
})

on('GET', '/blog/articles/(\\d+)/versions', ({ params }) => ({ data: article(params[0]).versions }))

on('POST', '/blog/articles/(\\d+)/versions/(\\d+)/restore', ({ params, locale }) => {
  const record = article(params[0])
  const snapshot = record.snapshots[Number(params[1])]

  if (snapshot === undefined) {
    throw new HttpFailure(404, 'No such version.')
  }

  /* An old publication becomes the draft; putting it on the site is a separate step. */
  for (const field of DRAFTED) {
    record.values[field] = blogClone(snapshot[field])
  }

  record.updated_at = new Date().toISOString()
  restate(record)

  return { data: articleDetail(record, locale) }
})

on('GET', '/blog/rubrics', ({ locale }) => ({
  data: rubrics.map((row) => ({ ...row, name: blogText(row.title, locale) || row.name })),
  prefix: PREFIX,
}))

on('POST', '/blog/rubrics', ({ body, locale }) => {
  const title = localized(body.title) || 'Новая рубрика'
  const slug = localized(body.slug) || slugify(title)
  const row: RubricRow = {
    id: Math.max(0, ...rubrics.map((one) => one.id)) + 1,
    name: title,
    title: asMap(body.title, title),
    slug: asMap(body.slug, slug),
    lead: asMap(body.lead, ''),
    path: `${PREFIX}/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/${slug}`,
    cover: null,
    is_visible: body.is_visible !== false,
    position: rubrics.length + 1,
    articles_count: 0,
    seo: (body.seo ?? {}) as Record<string, unknown>,
  }

  rubrics.push(row)

  return { data: { ...row, name: blogText(row.title, locale) || row.name } }
})

on('POST', '/blog/rubrics/reorder', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]) : []

  order(rubrics, ids)

  return { data: null }
})

on('PUT', '/blog/rubrics/(\\d+)', ({ params, body, locale }) => {
  const row = rubric(params[0])

  /* Only what travelled: a form that sent the cover alone must not blank the lead. */
  if (body.title !== undefined) row.title = asMap(body.title, '')
  if (body.slug !== undefined) row.slug = asMap(body.slug, '')
  if (body.lead !== undefined) row.lead = asMap(body.lead, '')
  if (body.is_visible !== undefined) row.is_visible = body.is_visible === true
  if (body.seo !== undefined) row.seo = (body.seo ?? {}) as Record<string, unknown>

  if (body.cover !== undefined) {
    const path = (body.cover as { path?: string } | null)?.path
    const file = typeof path === 'string' ? mediaByPath(path) : null

    row.cover =
      file === null ? null : { id: file.id, path: file.path, url: file.url, thumb: file.thumb }
  }

  const slug = blogText(row.slug, locale) || blogText(row.slug, 'ru')

  row.name = blogText(row.title, locale) || row.name
  row.path = `${PREFIX}/${slug}`
  row.url = `https://webx-demo.test/${row.path}`

  return { data: row }
})

on('DELETE', '/blog/rubrics/(\\d+)', ({ params }) => {
  const row = rubric(params[0])

  // The panel keeps the button out of reach while a rubric holds anything, so reaching this is
  // somebody else's save landing between the list and the click (§6).
  if (row.articles_count > 0) {
    throw new HttpFailure(422, `В рубрике ещё ${row.articles_count} статей.`)
  }

  rubrics.splice(rubrics.indexOf(row), 1)

  return { data: null }
})

/*
 * One answer to "which tags are there" and not two: the dropdown on the article form asks for
 * the first page of this same list, most used first, which is exactly what a dropdown is worth
 * scrolling.
 */
on('GET', '/blog/tags', ({ query, locale }) => {
  const search = (query.get('q') ?? '').trim().toLowerCase()
  const perPage = Number(query.get('per_page') ?? 30)
  const current = Number(query.get('page') ?? 1)

  let found = tags.map((row) => ({ ...row, title: blogText(row.titles, locale) || row.title }))

  if (search !== '') {
    found = found.filter((row) => row.title.toLowerCase().includes(search))
  }

  if (query.get('empty') === '1') found = found.filter((row) => row.articles_count === 0)
  if (query.get('noindex') === '1') found = found.filter((row) => row.indexing !== 'open')

  found.sort(
    query.get('sort') === 'name'
      ? (one, two) => one.title.localeCompare(two.title)
      : (one, two) => two.articles_count - one.articles_count,
  )

  const total = found.length
  const from = (current - 1) * perPage

  return {
    data: found.slice(from, from + perPage),
    meta: {
      current_page: current,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      per_page: perPage,
      total,
      from: total === 0 ? null : from + 1,
      to: total === 0 ? null : Math.min(total, from + perPage),
    },
    filters: {
      total: tags.length,
      empty: tags.filter((row) => row.articles_count === 0).length,
      noindex: tags.filter((row) => row.indexing !== 'open').length,
    },
  }
})

on('POST', '/blog/tags', ({ body }) => {
  const title = String(body.title ?? '').trim()

  if (title === '') {
    throw new HttpFailure(422, 'Слово не может быть пустым.', undefined, {
      title: ['Слово не может быть пустым.'],
    })
  }

  const slug = typeof body.slug === 'string' && body.slug !== '' ? body.slug : slugify(title)
  const row: TagRow = {
    id: Math.max(0, ...tags.map((one) => one.id)) + 1,
    title,
    titles: { ru: title, en: title },
    slug,
    path: `${PREFIX}/tag/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/tag/${slug}`,
    // A tag page is out of the index until somebody decides otherwise, and that decision
    // belongs on the tags screen rather than in passing while writing a sentence (§2.9).
    noindex: body.noindex === undefined ? true : body.noindex === true,
    indexing: body.noindex === false ? 'open' : 'noindex',
    articles_count: 0,
  }

  tags.push(row)

  return { data: row }
})

on('POST', '/blog/tags/mass', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]) : []
  const action = String(body.action)
  const picked = tags.filter((row) => ids.includes(row.id))

  for (const row of picked) {
    if (action === 'delete') {
      forget(row.id)
      tags.splice(tags.indexOf(row), 1)

      continue
    }

    row.noindex = action === 'noindex'
    row.indexing = action === 'noindex' ? 'noindex' : 'open'
  }

  recountBlog()

  return { data: { affected: picked.length } }
})

on('POST', '/blog/tags/merge', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]) : []
  const keep = tag(String(body.keep))
  const gone = tags.filter((row) => ids.includes(row.id) && row.id !== keep.id)

  for (const record of articles) {
    const carried = blogIds(record, 'tags')

    if (!carried.some((id) => gone.some((row) => row.id === id))) {
      continue
    }

    record.values.tags = [
      ...new Set(carried.map((id) => (gone.some((row) => row.id === id) ? keep.id : id))),
    ]
  }

  for (const row of gone) tags.splice(tags.indexOf(row), 1)

  recountBlog()

  return { data: { tag: keep, articles_count: keep.articles_count, merged: gone.length } }
})

on('PUT', '/blog/tags/(\\d+)', ({ params, body }) => {
  const row = tag(params[0])

  if (typeof body.title === 'string' && body.title !== '') {
    row.title = body.title
    row.titles = { ...row.titles, ru: body.title }
  }

  // A rename is a rename: the address moves only when the address was sent, or a word spelled
  // three ways before lunch leaves three aliases behind a decision nobody made.
  if (typeof body.slug === 'string' && body.slug !== '') {
    row.slug = body.slug
    row.path = `${PREFIX}/tag/${body.slug}`
    row.url = `https://webx-demo.test/${row.path}`
  }

  if (body.noindex !== undefined) {
    row.noindex = body.noindex === true
    row.indexing = row.noindex ? 'noindex' : 'open'
  }

  return { data: row }
})

on('DELETE', '/blog/tags/(\\d+)', ({ params }) => {
  const row = tag(params[0])

  forget(row.id)
  tags.splice(tags.indexOf(row), 1)
  recountBlog()

  return { data: null }
})

/* ------------------------------------------------------------------------------- media ----- */

on('GET', '/media/directories', () => ({ data: directories }))

on('POST', '/media/directories', ({ body }) => {
  const parent = directory(String(body.parent_id))
  const node = {
    id: Math.max(0, ...mediaFolders().map((one) => one.id)) + 1,
    parent_id: parent.id,
    title: String(body.title ?? 'Новая папка'),
    depth: parent.depth + 1,
    is_root: false,
    files_count: 0,
    children: [],
  }

  parent.children.push(node)

  return { data: node }
})

on('PATCH', '/media/directories/(\\d+)', ({ params, body }) => {
  const node = directory(params[0])

  node.title = String(body.title ?? node.title)

  return { data: node }
})

on('PATCH', '/media/directories/(\\d+)/move', ({ params, body }) => {
  const node = directory(params[0])
  const parent = directory(String(body.parent_id))
  const from = directory(String(node.parent_id))

  from.children.splice(from.children.indexOf(node), 1)
  parent.children.push(node)
  node.parent_id = parent.id
  node.depth = parent.depth + 1

  return { data: node }
})

on('DELETE', '/media/directories/(\\d+)', ({ params, query }) => {
  const node = directory(params[0])
  const held = mediaFiles.filter((file) => file.directory_id === node.id).length

  // Without `force` a folder that holds anything is refused, and the refusal says what it
  // holds: the dialog that asks the second question is built out of these numbers.
  if (query.get('force') !== '1' && (held > 0 || node.children.length > 0)) {
    throw new HttpFailure(422, 'Папка не пуста.', {
      code: 'directory_not_empty',
      message: 'Папка не пуста.',
      counts: { files: held, directories: node.children.length },
    })
  }

  const parent = directory(String(node.parent_id))

  parent.children.splice(parent.children.indexOf(node), 1)

  for (const file of [...mediaFiles]) {
    if (file.directory_id === node.id) mediaFiles.splice(mediaFiles.indexOf(file), 1)
  }

  mediaRecount()

  return { data: null }
})

on('GET', '/media/files', ({ query }) => {
  const inside = number(query.get('directory_id'))
  const search = (query.get('q') ?? '').trim().toLowerCase()
  const kind = query.get('type') ?? ''
  const perPage = Number(query.get('per_page') ?? 48)
  const current = Number(query.get('page') ?? 1)

  let found = [...mediaFiles]

  if (inside !== null) found = found.filter((file) => file.directory_id === inside)
  if (search !== '') found = found.filter((file) => file.name.toLowerCase().includes(search))
  if (kind !== '') found = found.filter((file) => file.type === kind)

  found.sort((one, two) => (two.created_at ?? '').localeCompare(one.created_at ?? ''))

  const total = found.length
  const from = (current - 1) * perPage

  return {
    data: found.slice(from, from + perPage),
    meta: {
      current_page: current,
      last_page: Math.max(1, Math.ceil(total / perPage)),
      per_page: perPage,
      total,
    },
    /* Everything the filters match, not just this page: the toolbar counts the whole folder. */
    stats: { files: total, size: found.reduce((sum, file) => sum + file.size, 0) },
  }
})

/* Before the one that takes an id: `by-path` is a word, and a route that only avoids being one
   by a number constraint is a route waiting to be read as an id. */
on('GET', '/media/files/by-path', ({ query }) => {
  const file = mediaByPath(query.get('path') ?? '')

  if (file === null) {
    throw new HttpFailure(404, 'Файла с таким ключом в библиотеке нет.')
  }

  return { data: file }
})

on('GET', '/media/files/(\\d+)', ({ params }) => ({ data: mediaFile(params[0]) }))

on('PATCH', '/media/files/(\\d+)', ({ params, body }) => {
  const file = mediaFile(params[0])

  file.name = String(body.name ?? file.name)

  return { data: file }
})

on('POST', '/media/files/move', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]) : []
  const into = directory(String(body.directory_id))
  const moved = mediaFiles.filter((file) => ids.includes(file.id))

  for (const file of moved) file.directory_id = into.id

  mediaRecount()

  return { data: { moved: moved.length } }
})

on('POST', '/media/files/delete', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]) : []
  const gone = mediaFiles.filter((file) => ids.includes(file.id))

  for (const file of gone) mediaFiles.splice(mediaFiles.indexOf(file), 1)

  mediaRecount()

  return { data: { deleted: gone.length } }
})

on('DELETE', '/media/files/(\\d+)', ({ params }) => {
  const file = mediaFile(params[0])

  mediaFiles.splice(mediaFiles.indexOf(file), 1)
  mediaRecount()

  return { data: null }
})

on('POST', '/media/files/(\\d+)/copy', ({ params }) => {
  const file = mediaFile(params[0])
  const copy = { ...file, id: Math.max(0, ...mediaFiles.map((one) => one.id)) + 1, duplicate: true }

  mediaFiles.unshift(copy)
  mediaRecount()

  return { data: copy }
})

/* ---------------------------------------------------------------------------- plumbing ----- */

class HttpFailure extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly data?: unknown,
    readonly errors?: Record<string, string[]>,
    /** Keys of the body itself, for a refusal whose shape is more than message and errors. */
    readonly extra?: Record<string, unknown>,
  ) {
    super(message)
  }
}

function page(id: string): PageRecord {
  const record = pages.get(Number(id))

  if (record === undefined) {
    throw new HttpFailure(404, 'No such page.')
  }

  return record
}

function blockType(id: string): BlockType {
  const type = blockTypes.find((item) => item.id === Number(id))

  if (type === undefined) {
    throw new HttpFailure(404, 'No such block type.')
  }

  return type
}

function form(id: string): InboxForm {
  const item = forms.find((each) => each.id === Number(id))

  if (item === undefined) {
    throw new HttpFailure(404, 'No such form.')
  }

  return item
}

function submission(id: string): SubmissionRecord {
  const record = submissions.find((item) => item.id === Number(id))

  if (record === undefined) {
    throw new HttpFailure(404, 'No such submission.')
  }

  return record
}

/** One line out of a package's own `lang` files, falling back to English and then to the key. */
function line(locale: string, namespace: string, path: string): string {
  const [group, key] = path.split('.')

  for (const code of [locale, 'en']) {
    const value = dictionary(code)[namespace]?.[group]?.[key]

    if (typeof value === 'string') {
      return value
    }
  }

  return path
}

function withStatus(records: PageRecord[], status: string): PageRecord[] {
  return status === '' ? records : records.filter((record) => record.row.status === status)
}

/** What the editor reads the page as — a save carrying an older one is refused with a 409. */
function revisionOf(record: PageRecord): string {
  return `${record.row.id}:${record.row.updated_at ?? ''}`
}

/** The address of the branch above, by language: what this page's own address is built on. */
function prefixOf(row: PageRow): Record<string, string> {
  const above = ancestorsOf(row)
    .map((node) => node.slug)
    .filter((slug) => slug !== '')
    .join('/')

  return { ru: above, en: above }
}

/**
 * What is kept of `seo.og_image`: the key, and the words this record uses for the picture.
 *
 * Never the address. The panel's media field sends one along so that it can draw what was just
 * picked, and the server works it out again on every read — which is the only answer that
 * survives a signed link expiring or the library moving disks. A fixture that stored what
 * arrived would make the share preview look right here and leave it broken on a real site.
 */
function storedSeo(value: unknown): unknown {
  if (value === null || typeof value !== 'object') return value

  const seo = value as Record<string, unknown>
  const image = seo.og_image as Record<string, unknown> | null | undefined

  if (image === null || image === undefined || typeof image.path !== 'string') {
    return seo
  }

  const kept = { ...image }

  delete kept.url

  return { ...seo, og_image: kept }
}

/** The other half: the key with the address beside it, for a form that draws a preview. */
function withSeoImage(values: Record<string, unknown>): Record<string, unknown> {
  const seo = values.seo as Record<string, unknown> | null | undefined
  const image = seo?.og_image as Record<string, unknown> | null | undefined

  if (image === null || image === undefined || typeof image.path !== 'string') {
    return values
  }

  const file = mediaByPath(image.path)

  return { ...values, seo: { ...seo, og_image: { ...image, url: file?.url ?? null } } }
}

function localized(value: unknown): string {
  if (typeof value === 'string') return value
  if (value === null || typeof value !== 'object') return ''

  const map = value as Record<string, unknown>

  return String(map.ru ?? map.en ?? '')
}

/** The list's shape: no content, a picture instead, and a count taken rather than stored. */
function withoutContent(type: BlockType): BlockType {
  const copy = { ...type, thumbnail: thumbnailOf(type), usage_count: usageOf(type.slug).length }

  delete copy.content

  return copy
}

function nextFieldId(): number {
  return Math.max(0, ...fields.map((field) => field.id)) + 1
}

/** Reorders a list in place by the ids the screen dragged them into. */
function order<T extends { id: number; position: number }>(list: T[], ids: number[]): void {
  ids.forEach((id, index) => {
    const item = list.find((each) => each.id === id)

    if (item !== undefined) {
      item.position = index + 1
    }
  })

  list.sort((one, two) => one.position - two.position)
}

function row(record: SubmissionRecord) {
  return {
    id: record.id,
    values: record.values,
    status: statuses.find((status) => status.id === record.status_id) ?? null,
    assignee: admins.find((admin) => admin.id === record.assignee_id) ?? null,
    is_read: record.is_read,
    source: record.source,
    files_count: record.files.length,
    created_at: record.created_at,
  }
}

function detail(record: SubmissionRecord, query?: URLSearchParams) {
  const siblings = submissions
    .filter((item) => item.form_id === record.form_id)
    .sort((one, two) => Date.parse(two.created_at) - Date.parse(one.created_at))
  const index = siblings.indexOf(record)
  const item = forms.find((each) => each.id === record.form_id)

  return {
    id: record.id,
    form: { id: record.form_id, slug: item?.slug ?? null, title: item?.title ?? null },
    status: statuses.find((status) => status.id === record.status_id) ?? null,
    assignee: admins.find((admin) => admin.id === record.assignee_id) ?? null,
    values: valuesFor(record),
    files: record.files,
    meta: record.meta,
    events: eventsFor(record),
    is_read: record.is_read,
    source: record.source,
    notified_at: record.notified_at,
    notify_error: record.notify_error,
    previous_id: index > 0 ? siblings[index - 1].id : null,
    next_id: index >= 0 && index < siblings.length - 1 ? siblings[index + 1].id : null,
    created_at: record.created_at,
    updated_at: record.updated_at,
    /* The filters travel with a submission so the neighbours are the ones either side of it in
       the list somebody was actually looking at; here they only have to arrive. */
    view: query?.get('view') ?? null,
  }
}

function counts(all: SubmissionRecord[]): SubmissionCounts {
  const spam = new Set(statuses.filter((status) => status.is_spam).map((status) => status.id))
  const byStatus: Record<string, number> = {}

  for (const status of statuses) {
    byStatus[status.key] = all.filter((record) => record.status_id === status.id).length
  }

  return {
    all: all.filter((record) => !spam.has(record.status_id)).length,
    unread: all.filter((record) => !record.is_read && !spam.has(record.status_id)).length,
    statuses: byStatus,
  }
}

/** Newest first unless the list asked for something else. */
function sortValue(record: SubmissionRecord, query: URLSearchParams): number {
  const sort = query.get('sort') ?? ''

  if (sort === 'created_at') {
    return -Date.parse(record.created_at)
  }

  return Date.parse(record.created_at)
}

function omit(body: Record<string, unknown>, keys: string[]): Record<string, unknown> {
  const copy = { ...body }

  for (const key of keys) {
    delete copy[key]
  }

  return copy
}

function article(id: string): ArticleRecord {
  const record = findArticle(Number(id))

  if (record === null) {
    throw new HttpFailure(404, 'No such article.')
  }

  return record
}

function rubric(id: string): RubricRow {
  const row = rubrics.find((one) => one.id === Number(id))

  if (row === undefined) {
    throw new HttpFailure(404, 'No such rubric.')
  }

  return row
}

function tag(id: string): TagRow {
  const row = tags.find((one) => one.id === Number(id))

  if (row === undefined) {
    throw new HttpFailure(404, 'No such tag.')
  }

  return row
}

/**
 * One article as its editor opens it: the record, the values of the screen, and the few things
 * around them that the description cannot carry.
 *
 * The preview link is minted per response rather than stored, the same way the real one is:
 * there it is signed and short-lived, and a form left open all morning would otherwise offer a
 * link that expired before lunch.
 */
function articleDetail(record: ArticleRecord, locale: string): Record<string, unknown> {
  return {
    article: articleRow(record, locale),
    values: withSeoImage(articleValues(record)),
    revision: articleRevision(record),
    prefix: PREFIX,
    preview_url: `/preview/article/${record.id}`,
    options: {
      rubrics: rubrics.map((row) => ({
        id: row.id,
        title: blogText(row.title, locale) || row.name,
      })),
      // Everybody who could be named as the author, not only those who already are: an article
      // written by one person and signed by another is an ordinary thing in a newsroom.
      authors: blogAuthors.map((one) => ({ id: one.id, title: one.title })),
    },
    /* Titles for the ids in `values.related`; nothing else carries them. */
    related: blogIds(record, 'related').flatMap((id) => {
      const other = findArticle(id)

      return other === null ? [] : [{ id, title: articleRow(other, locale).title }]
    }),
  }
}

/**
 * Whether what is written still matches what the site is showing.
 *
 * Only for an article that is on the site: "draft" and "waiting for its day" have nothing to
 * compare against, and "taken off it" is a fact about the site rather than about the last
 * keystroke.
 */
function restate(record: ArticleRecord): void {
  if (record.live === null || (record.status !== 'published' && record.status !== 'modified')) {
    return
  }

  const written = DRAFTED.map((field) => JSON.stringify(record.values[field] ?? null)).join('|')
  const live = DRAFTED.map((field) => JSON.stringify(record.live?.[field] ?? null)).join('|')

  record.status = written === live ? 'published' : 'modified'
}

/** The order the list asked for; left out, it is pinned first and then by date. */
function byArticle(
  sort: string,
  locale: string,
): (one: ArticleRecord, two: ArticleRecord) => number {
  const descending = sort.startsWith('-')
  const key = descending ? sort.slice(1) : sort

  if (key === '') {
    return (one, two) => {
      const pinned = Number(two.values.pinned === true) - Number(one.values.pinned === true)

      return pinned !== 0 ? pinned : day(two) - day(one)
    }
  }

  return (one, two) => {
    const answer =
      key === 'title'
        ? articleRow(one, locale).title.localeCompare(articleRow(two, locale).title)
        : key === 'updated_at'
          ? Date.parse(one.updated_at) - Date.parse(two.updated_at)
          : day(one) - day(two)

    return descending ? -answer : answer
  }
}

function day(record: ArticleRecord): number {
  return record.published_at === null ? 0 : Date.parse(record.published_at)
}

/** Every article forgets one tag — what a delete and a mass delete both have to do. */
function forget(id: number): void {
  for (const record of articles) {
    record.values.tags = blogIds(record, 'tags').filter((one) => one !== id)
  }
}

/** A localized field as it arrived: a map stays a map, a plain string becomes one. */
function asMap(value: unknown, fallback: string): Record<string, string> {
  if (typeof value === 'string') return { ru: value, en: value }
  if (value === null || typeof value !== 'object') return { ru: fallback, en: fallback }

  const map = value as Record<string, unknown>
  const out: Record<string, string> = {}

  for (const [code, text] of Object.entries(map)) {
    out[code] = typeof text === 'string' ? text : ''
  }

  return out
}

function directory(id: string): MediaDirectory {
  const node = findFolder(Number(id))

  if (node === null) {
    throw new HttpFailure(404, 'No such directory.')
  }

  return node
}

function mediaFile(id: string): MediaFile {
  const file = fileById(Number(id))

  if (file === null) {
    throw new HttpFailure(404, 'No such file.')
  }

  return file
}

/** A query parameter that means a number, or `null` for one that was not asked. */
function number(value: string | null): number | null {
  if (value === null || value === '') return null

  const parsed = Number(value)

  return Number.isNaN(parsed) ? null : parsed
}

/** The site, as the preview iframe of the constructor shows it. */
function previewPage(id: number): string {
  const record = pages.get(id)

  if (record === undefined) {
    return missingPreview('No such page.')
  }

  return document(record.row.title, (record.values.blocks ?? []) as Block[])
}

/**
 * The same, for an article — the draft of it, which is the whole point of a preview.
 *
 * One document and not two: an article of this site is a page made of the same blocks, and a
 * second renderer beside it would drift from the first the first time a block type changed.
 */
function previewArticle(id: number): string {
  const record = findArticle(id)

  if (record === null) {
    return missingPreview('No such article.')
  }

  return document(
    blogText(record.values.title, 'ru') || `#${record.id}`,
    (record.values.blocks ?? []) as Block[],
  )
}

function missingPreview(message: string): string {
  return `<!doctype html><title>404</title><p>${message}</p>`
}

function document(title: string, nodes: Block[]): string {
  /* Every type on the page, nested ones included: a container's styles are not enough. */
  const used = new Set(nodes.flatMap((node) => types(node)))
  const styles = blockTypes
    .filter((type) => used.has(type.slug))
    .map((type) => type.content?.styles ?? '')
    .join('\n')

  const html = nodes
    .filter((node) => node.hidden !== true)
    .map((node) => draw(node))
    .join('\n')

  return `<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${title}</title>
    <style>
      body { margin: 0; font-family: system-ui, sans-serif; background: #fff; }
      ${styles}
    </style>
  </head>
  <body>
${html}
  </body>
</html>`
}

function draw(node: Block): string {
  const type = blockTypes.find((item) => item.slug === node.type)

  if (type?.content === undefined) {
    return ''
  }

  /* A container holds its blocks in one of its own values; `@blocks('children')` prints them. */
  const children: Record<string, string> = {}

  for (const [key, value] of Object.entries(node.values)) {
    if (Array.isArray(value) && value.every((item) => isBlock(item))) {
      children[key] = (value as Block[])
        .filter((child) => child.hidden !== true)
        .map((child) => draw(child))
        .join('\n')
    }
  }

  const html = renderTemplate(type.content.template, node.values, children)

  /* The pair of markers is what the panel replaces a block between after a field changes. */
  return `<!--wx:${node.key}-->\n${html}\n<!--/wx:${node.key}-->`
}

interface Block {
  key: string
  type: string
  values: Record<string, unknown>
  hidden?: boolean
}

function isBlock(value: unknown): value is Block {
  return typeof value === 'object' && value !== null && 'type' in value && 'key' in value
}

/** The slugs on a block and everything nested inside it. */
function types(node: Block): string[] {
  const nested = Object.values(node.values)
    .filter(
      (value): value is Block[] => Array.isArray(value) && value.every((item) => isBlock(item)),
    )
    .flat()

  return [node.type, ...nested.flatMap((child) => types(child))]
}

/**
 * The runtime a block's script is mounted by, read off the composer package on every request
 * for the same reason the dictionary is: Vite watches what it imports, and this file is not
 * imported by anything.
 */
function blocksRuntime(): string {
  return readFileSync(
    fileURLToPath(
      new URL('../../../../php/packages/module-blocks/resources/js/runtime.js', import.meta.url),
    ),
    'utf8',
  )
}

/**
 * The panel's own API, its preview, and the history fallback that makes `/panel/...` a page.
 */
export function panelServer(): Plugin {
  return {
    name: 'webx-playground-panel',
    configureServer(server) {
      server.middlewares.use((request, response, next) => {
        const url = new URL(request.url ?? '/', 'http://localhost')

        const preview = url.pathname.match(/^\/preview\/(page|article)\/(\d+)$/)

        if (preview !== null) {
          const id = Number(preview[2])

          response.setHeader('Content-Type', 'text/html; charset=utf-8')
          response.end(preview[1] === 'page' ? previewPage(id) : previewArticle(id))

          return
        }

        /* The library's own bytes, off the panel's origin — which is the only place a canvas
           may read a picture back from, and the only address a stored key can still resolve
           to after the library moves disks. */
        if (url.pathname.startsWith('/fixtures/media/')) {
          const found = bytesOf(decodeURIComponent(url.pathname.slice('/fixtures/media/'.length)))

          if (found === null) {
            response.statusCode = 404
            response.end('No such file.')

            return
          }

          response.setHeader('Content-Type', found.mime)
          response.setHeader('Cache-Control', 'no-store')
          response.end(found.bytes)

          return
        }

        /* The blocks runtime, the same file the composer package ships: the preview tells the
           frame to load it, and without it a block's script is fetched into a 404 and never
           runs at all — which looks exactly like a block that has no script. */
        if (url.pathname === '/blocks-runtime.js') {
          response.setHeader('Content-Type', 'text/javascript; charset=utf-8')
          response.setHeader('Cache-Control', 'no-store')
          response.end(blocksRuntime())

          return
        }

        /* An upload is the one request that is not JSON, so it never reaches the router. */
        if (url.pathname === '/api/cms/media/files' && request.method === 'POST') {
          void upload(request, response)

          return
        }

        if (url.pathname.startsWith('/api/cms/')) {
          void answer(request, response, url)

          return
        }

        /* The panel is a single page behind a router: every address under it is that page. */
        if (/^\/panel(\/|$)/.test(url.pathname) && !url.pathname.includes('.')) {
          request.url = '/panel.html'
        }

        next()
      })
    },
  }
}

async function answer(request: IncomingMessage, response: ServerResponse, url: URL): Promise<void> {
  const path = url.pathname.replace(/^\/api\/cms/, '')
  const method = (request.method ?? 'GET').toUpperCase()
  const route = routes.find((each) => each.method === method && each.pattern.test(path))

  response.setHeader('Content-Type', 'application/json; charset=utf-8')

  if (route === undefined) {
    response.statusCode = 404
    response.end(JSON.stringify({ message: `No route for ${method} ${path}.` }))

    return
  }

  const params = route.pattern.exec(path)?.slice(1) ?? []
  const body = await read(request)
  const header = request.headers['x-webx-locale']
  const locale = typeof header === 'string' && header !== '' ? header : 'en'

  try {
    const payload = route.handler({ params, query: url.searchParams, body, locale })

    await wait(DELAY)

    response.statusCode = method === 'POST' ? 201 : 200
    response.end(JSON.stringify(payload))
  } catch (error) {
    await wait(DELAY)

    if (error instanceof HttpFailure) {
      response.statusCode = error.status
      response.end(
        JSON.stringify({
          message: error.message,
          errors: error.errors ?? {},
          data: error.data,
          ...error.extra,
        }),
      )

      return
    }

    response.statusCode = 500
    response.end(
      JSON.stringify({ message: error instanceof Error ? error.message : String(error) }),
    )
  }
}

/**
 * An upload, which is the one request here that is not JSON.
 *
 * Handled before the router rather than inside it, because everything the router does starts
 * with parsing a body as JSON — and because the bytes are the point: a fixture that answered
 * with a made-up file would leave the picture somebody just chose broken in the field.
 */
async function upload(request: IncomingMessage, response: ServerResponse): Promise<void> {
  const type = request.headers['content-type'] ?? ''
  const boundary = /boundary=(?:"([^"]+)"|([^;]+))/.exec(type)

  response.setHeader('Content-Type', 'application/json; charset=utf-8')

  if (boundary === null) {
    response.statusCode = 422
    response.end(JSON.stringify({ message: 'Expected a multipart body.' }))

    return
  }

  const parts = split(await raw(request), `--${boundary[1] ?? boundary[2]}`)
  const directoryId = Number(
    parts.find((part) => part.name === 'directory_id')?.bytes.toString('utf8') ?? 1,
  )

  const files = parts
    .filter((part) => part.fileName !== null)
    .map((part) => receive(directoryId, part.fileName!, part.mime, part.bytes))

  await wait(DELAY)

  response.statusCode = 201
  response.end(JSON.stringify({ data: files }))
}

interface Part {
  name: string
  fileName: string | null
  mime: string
  bytes: Buffer
}

/**
 * A multipart body, cut into its parts.
 *
 * On bytes rather than on a string: a picture turned into UTF-8 and back is not the same
 * picture, and the failure looks like a corrupted file rather than like a parser.
 */
function split(body: Buffer, boundary: string): Part[] {
  const parts: Part[] = []
  const mark = Buffer.from(`\r\n${boundary}`)
  const sections = splitBuffer(Buffer.concat([Buffer.from('\r\n'), body]), mark)

  for (const section of sections) {
    const head = section.indexOf('\r\n\r\n')

    if (head < 0) continue

    const headers = section.subarray(0, head).toString('utf8')
    const name = /name="([^"]*)"/.exec(headers)
    const fileName = /filename="([^"]*)"/.exec(headers)
    const mime = /Content-Type:\s*([^\r\n]+)/i.exec(headers)

    if (name === null) continue

    parts.push({
      name: name[1],
      fileName: fileName === null || fileName[1] === '' ? null : fileName[1],
      mime: mime?.[1].trim() ?? 'application/octet-stream',
      bytes: section.subarray(head + 4),
    })
  }

  return parts
}

function splitBuffer(body: Buffer, mark: Buffer): Buffer[] {
  const out: Buffer[] = []
  let from = 0

  for (;;) {
    const at = body.indexOf(mark, from)

    if (at < 0) break

    if (from > 0) out.push(body.subarray(from, at))

    from = at + mark.length

    /* The closing boundary carries `--`; everything after it is the epilogue. */
    if (body.subarray(from, from + 2).toString('utf8') === '--') break

    from += 2
  }

  return out
}

function raw(request: IncomingMessage): Promise<Buffer> {
  return new Promise((resolve) => {
    const chunks: Buffer[] = []

    request.on('data', (chunk: Buffer) => chunks.push(chunk))
    request.on('end', () => resolve(Buffer.concat(chunks)))
  })
}

function read(request: IncomingMessage): Promise<Record<string, unknown>> {
  return new Promise((resolve) => {
    const chunks: Buffer[] = []

    request.on('data', (chunk: Buffer) => chunks.push(chunk))
    request.on('end', () => {
      const text = Buffer.concat(chunks).toString('utf8')

      try {
        resolve(text === '' ? {} : (JSON.parse(text) as Record<string, unknown>))
      } catch {
        resolve({})
      }
    })
  })
}

function wait(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}
