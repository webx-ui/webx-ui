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
import type { TagRow } from '../../../../packages/module-blog/src/types'
import type { MediaDirectory, MediaFile } from '../../../../packages/module-media/src/types'
import {
  blockGroups,
  blockTypes,
  blockVersions,
  clone as blockClone,
  blockShapes,
  callersOf,
  callsOf,
  declaredComponents,
  draw as drawContent,
  fallbackSource,
  renderTemplate,
  tagCaller,
  templateFailure,
  useCollectionResolver,
  useLinkResolver,
  type BlockVersionRecord,
} from './blocks'
import {
  collectionSources,
  createCategory as createFaqCategory,
  faqCategories,
  faqCategoryDetail,
  faqCategoryRow,
  findCategory as findFaqCategory,
  findQuestion as findFaqQuestion,
  listQuestions as listFaqQuestions,
  questionDetail as faqQuestionDetail,
  questionRow as faqQuestionRow,
  reorderCategories as reorderFaqCategories,
  reorderQuestions as reorderFaqQuestions,
  resolveFaq,
  writeCategory as writeFaqCategory,
  writeQuestion as writeFaqQuestion,
} from './faq'
import {
  createCategory as createReviewCategory,
  findCategory as findReviewCategory,
  findReview,
  listReviews,
  reorderCategories as reorderReviewCategories,
  reorderReviews,
  resolveReviews,
  reviewCategories,
  reviewCategoryDetail,
  reviewCategoryRow,
  reviewDetail,
  reviewRow,
  writeCategory as writeReviewCategory,
  writeReview,
} from './reviews'
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
  type RubricRecord,
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
import {
  CACHE_ENABLED,
  addItem as addMenuItem,
  childrenOf,
  countItems as countMenuItems,
  create as createMenu,
  depthOf,
  ensure as ensureMenu,
  find as findMenu,
  forget as forgetMenu,
  forgetAll as forgetAllMenus,
  isDeclared as isDeclaredMenu,
  itemOf,
  list as listMenus,
  moveItem as moveMenuItem,
  remove as removeMenu,
  removeItem as removeMenuItem,
  rename as renameMenu,
  type ItemRecord,
  type MenuRecord,
} from './menus'
import { adminRows, endConnection, listCalls, listConnections, roles as adminRoles } from './agents'
import { dictionary, panelLocales } from './lang'
import {
  createRecipe,
  createTerm as createRecipeTerm,
  discard as discardRecipe,
  find as findRecipe,
  findTerm as findRecipeTerm,
  listRecipes,
  publish as publishRecipe,
  recipeDetail,
  recipePage,
  reorderRecipes,
  reorderTerms as reorderRecipeTerms,
  resolveRecipes,
  restoreVersion as restoreRecipeVersion,
  revision as recipeRevision,
  row as recipeRow,
  termDetail as recipeTermDetail,
  termRow as recipeTermRow,
  terms as recipeTerms,
  writeRecipe,
  writeTerm as writeRecipeTerm,
  PREFIX as RECIPES_PREFIX,
  type RecipeRecord,
  type TermKind as RecipeTermKind,
} from './recipes'
import { drawRecipePage, RECIPE_PAGE_STYLES } from './recipes-site'
import { relationCandidates } from './relations'
import { screen, screenNames } from './screens'
import {
  PREFIX as SERVICES_PREFIX,
  categoryIds as serviceCategoryIds,
  categoryRow as serviceCategoryRow,
  clone as serviceClone,
  draftedFields as serviceDraftedFields,
  file as fileService,
  find as findService,
  findCategory as findServiceCategory,
  recount as recountServices,
  restate as restateService,
  revision as serviceRevision,
  row as serviceRow,
  serviceCategories,
  services,
  text as serviceText,
  type ServiceCategoryRecord,
  type ServiceRecord,
} from './services'

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
      {
        id: 'services',
        title: line(locale, 'webx-services', 'module.group'),
        icon: 'briefcase',
        order: 400,
      },
      {
        id: 'faq',
        title: line(locale, 'webx-faq', 'module.group'),
        icon: 'question',
        order: 500,
      },
      {
        id: 'reviews',
        title: line(locale, 'webx-reviews', 'module.group'),
        icon: 'star',
        order: 600,
      },
      {
        id: 'recipes',
        title: line(locale, 'webx-recipes', 'module.group'),
        icon: 'heart',
        order: 700,
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
        meta: { groups: blockGroups, editing: true, provides: [], stage: '/_preview/block-stage' },
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
        id: 'services',
        title: line(locale, 'webx-services', 'module.services'),
        icon: 'list',
        order: 400,
        group: 'services',
        permissions: ['services.view', 'services.manage'],
        meta: {},
      },
      {
        id: 'service-categories',
        title: line(locale, 'webx-services', 'module.categories'),
        icon: 'folder',
        order: 410,
        group: 'services',
        permissions: ['services.categories.manage'],
        meta: {},
      },
      {
        id: 'faq',
        title: line(locale, 'webx-faq', 'module.questions'),
        icon: 'list',
        order: 500,
        group: 'faq',
        permissions: ['faq.view', 'faq.manage'],
        meta: {},
      },
      {
        id: 'faq-categories',
        title: line(locale, 'webx-faq', 'module.categories'),
        icon: 'folder',
        order: 510,
        group: 'faq',
        permissions: ['faq.categories.manage'],
        meta: {},
      },
      {
        id: 'reviews',
        title: line(locale, 'webx-reviews', 'module.reviews'),
        icon: 'list',
        order: 600,
        group: 'reviews',
        permissions: ['reviews.view', 'reviews.manage'],
        meta: {},
      },
      {
        id: 'review-categories',
        title: line(locale, 'webx-reviews', 'module.categories'),
        icon: 'folder',
        order: 610,
        group: 'reviews',
        permissions: ['reviews.categories.manage'],
        meta: {},
      },
      {
        id: 'recipes',
        title: line(locale, 'webx-recipes', 'module.recipes'),
        icon: 'file-text',
        order: 700,
        group: 'recipes',
        permissions: ['recipes.view', 'recipes.manage'],
        meta: {},
      },
      {
        id: 'recipe-categories',
        title: line(locale, 'webx-recipes', 'module.categories'),
        icon: 'folder',
        order: 710,
        group: 'recipes',
        permissions: ['recipes.categories.manage'],
        meta: {},
      },
      {
        id: 'recipe-nutrients',
        title: line(locale, 'webx-recipes', 'module.nutrients'),
        icon: 'tag',
        order: 720,
        group: 'recipes',
        permissions: ['recipes.categories.manage'],
        meta: {},
      },
      {
        id: 'menu',
        title: line(locale, 'webx-menu', 'module.title'),
        icon: 'menu',
        /* Among the content sections and after them: a menu is a way of pointing at pages and
           articles rather than a thing of its own. */
        order: 400,
        group: null,
        permissions: ['menu.view', 'menu.manage'],
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
      {
        id: 'connect',
        title: line(locale, 'webx-auth', 'connect.title'),
        icon: 'link',
        order: 950,
        group: 'system',
        /* Nobody needs one: whoever got into the panel may connect an agent. */
        permissions: [],
        /* Not a real door — there is no MCP server behind the playground — but the address
           is what the page is about, and a made-up host would read as one. */
        meta: { url: 'https://webx-demo.test/api/cms/mcp' },
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

/* The places modules declared ride beside the list, not in it (§3.10 of the components spec). */
on('GET', '/blocks', () => ({
  data: blockTypes.map((type) => withCalls(withoutContent(type))),
  declared: declaredComponents.map((place) => ({
    slug: place.slug,
    module: place.module,
    title: place.title,
    description: place.description,
    fallback: place.fallback,
    customised: blockTypes.some((type) => type.slug === place.slug),
  })),
}))

/**
 * What a type says about calls (§3.10): its kind, the slugs its template calls and the types
 * whose published version calls it. Worked out on every answer rather than stored, so a template
 * edited in the constructor shows in the section straight away.
 */
function withCalls<T extends BlockType>(type: T): T {
  const source = blockTypes.find((item) => item.id === type.id)

  return {
    ...type,
    kind: type.kind ?? 'block',
    uses: callsOf(source?.content?.template ?? ''),
    used_by: callersOf(type.slug),
  }
}

/** The declaration of the slug and the shapes its schema names — only the one type's answer. */
function withDeclaration(type: BlockType): BlockType {
  const place = declaredComponents.find((item) => item.slug === type.slug)
  const shape: NonNullable<BlockType['shape']> = {}

  for (const node of wxData(type.content?.schema ?? [])) {
    const name = typeof node.props?.shape === 'string' ? node.props.shape : null
    const found = name === null ? undefined : blockShapes[name]

    if (name !== null && found !== undefined) shape[name] = { fields: found.fields }
  }

  return {
    ...withCalls(type),
    declared:
      place === undefined
        ? null
        : {
            slug: place.slug,
            module: place.module,
            title: place.title,
            description: place.description,
            fallback: place.fallback,
            customised: true,
          },
    shape,
  }
}

function wxData(schema: NonNullable<BlockType['content']>['schema']): typeof schema {
  return schema.flatMap((node) => (node.type === 'wx-data' ? [node] : wxData(node.children ?? [])))
}

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
    .filter((type) => type.kind !== 'component' && type.is_enabled && type.published !== null)
    .map((type) => ({ ...type, thumbnail: thumbnailOf(type) })),
}))

/* The ids take the places they held, in the new order; the rest of the list does not move. */
on('POST', '/blocks/reorder', ({ body }) => {
  const ids = (body.ids as number[]).map(Number)
  const next = ids.map((id) => blockTypes.find((type) => type.id === id)!)
  const slots = blockTypes.flatMap((type, index) => (ids.includes(type.id) ? [index] : []))

  slots.forEach((slot, index) => (blockTypes[slot] = next[index]!))

  return { data: { ids } }
})

on('POST', '/blocks', ({ body }) => {
  const now = new Date().toISOString()
  const type: BlockType = {
    id: Math.max(...blockTypes.map((item) => item.id)) + 1,
    kind: body.kind === 'component' ? 'component' : 'block',
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

  return { data: withDeclaration(counted(type)) }
})

/**
 * "Customise" (§4.2): a component of the declared slug, its schema from the declaration, and a
 * draft whose template is the module's view as the playground has it on disk — not published,
 * so the site keeps the module's view until somebody does.
 */
on('POST', '/blocks/components/([\\w-]+)/customise', ({ params }) => {
  const place = declaredComponents.find((item) => item.slug === params[0])

  if (place === undefined) throw new HttpFailure(404, 'No such declared component.')

  const taken = blockTypes.find((item) => item.slug === place.slug)

  if (taken !== undefined) {
    throw new HttpFailure(409, 'The site already has this type.', undefined, undefined, {
      id: taken.id,
    })
  }

  const now = new Date().toISOString()
  const sample: Record<string, unknown> = {}

  for (const node of wxData(place.schema)) {
    const shape = typeof node.props?.shape === 'string' ? blockShapes[node.props.shape] : undefined

    sample[node.id] = shape?.sample() ?? {}
  }

  const draft = {
    number: 1,
    source: 'panel' as const,
    comment: `From ${place.fallback}`,
    author_id: 1,
    author: 'Анна Ковальчук',
    created_at: now,
  }

  const type: BlockType = {
    id: Math.max(...blockTypes.map((item) => item.id)) + 1,
    kind: 'component',
    slug: place.slug,
    title: place.title,
    description: place.description,
    icon: null,
    group: 'content',
    sort: blockTypes.length * 10 + 10,
    allow: null,
    allowed_in: null,
    max_per_entity: null,
    is_enabled: true,
    draft,
    published: null,
    usage_count: 0,
    thumbnail: null,
    created_at: now,
    updated_at: now,
    content: {
      schema: blockClone(place.schema),
      template: fallbackSource(place.fallback) ?? '',
      styles: '',
      script: null,
      sample,
    },
  }

  blockTypes.push(type)
  history(type).push({ ...draft, content: blockClone(contentOf(type)) })

  return { data: withDeclaration(counted(type)) }
})

on('GET', '/blocks/(\\d+)', ({ params }) => ({
  data: withDeclaration(counted(blockType(params[0]))),
}))

/** The editor asks how many entities stand on the type; the answer is taken, not stored. */
function counted(type: BlockType): BlockType {
  type.usage_count = usageOf(type.slug).length

  return type
}

on('PUT', '/blocks/(\\d+)', ({ params, body }) => {
  const type = blockType(params[0])
  const content = body.content as Partial<NonNullable<BlockType['content']>> | undefined

  /* §3.1: a block on pages cannot turn into a component — nobody could add it, and the pages
     would keep one that nothing offers. The other way is always fine. */
  if (body.kind === 'component' && type.kind !== 'component' && usageOf(type.slug).length > 0) {
    throw new HttpFailure(422, 'The block stands on pages.', undefined, {
      kind: ['The block stands on pages, so it cannot become a component.'],
    })
  }

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

  return { data: withDeclaration(counted(type)) }
})

on('DELETE', '/blocks/(\\d+)', ({ params }) => {
  const type = blockType(params[0])
  const callers = callersOf(type.slug)

  /* A declared place's own module is not a caller: it has its fallback, and deleting is how the
     site goes back to it. The blocks calling it would draw nothing. */
  if (callers.length > 0) {
    throw new HttpFailure(
      422,
      'Other blocks call this type.',
      undefined,
      { used_by: callers.map((one) => `Called by "${one.title}" (${one.slug})`) },
      { used_by: callers },
    )
  }

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

  return { data: withDeclaration(counted(type)) }
})

on('POST', '/blocks/(\\d+)/render', ({ params, body }) => {
  const type = blockType(params[0])
  const content = {
    ...type.content,
    ...((body.content ?? {}) as Record<string, unknown>),
  } as NonNullable<BlockType['content']>
  const values = (body.values ?? content.sample ?? {}) as Record<string, unknown>
  const drawn = drawContent(content, values, 0, [type.slug])
  const key = typeof body.key === 'string' && body.key !== '' ? body.key : 'sample'

  return {
    data: {
      /* Between the pair of markers, as `RenderController` draws it: the stage swaps the block
         between them, and a block without them is a place the next change cannot find. */
      html: `<!--wx:${key}-->${drawn.html}<!--/wx:${key}-->`,
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
      stage: '/_preview/block-stage',
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

  return { data: withDeclaration(counted(type)) }
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

on('GET', '/auth/connections', ({ query }) => listConnections(query))

on('DELETE', '/auth/connections/(\\d+)', ({ params }) => ({
  data: endConnection(Number(params[0])),
}))

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

/* ------------------------------------------------------------------------------ links ----- */

/**
 * What the panel can be asked to link to.
 *
 * The sections are the ones the PHP sources register, answered out of the same fixtures the
 * sections themselves are drawn from — so a link picker here offers the pages and rubrics that are
 * on the screen next to it. `available` is the part worth keeping honest: a draft page has an
 * address and is not on the site, and it is offered marked rather than hidden.
 */
interface LinkRow {
  id: number
  title: string
  url: string | null
  available: boolean
  hint: string | null
}

const LINK_SOURCES = [
  { type: 'page', title: { en: 'Pages', ru: 'Страницы' }, icon: 'file' },
  { type: 'article', title: { en: 'Articles', ru: 'Статьи' }, icon: 'file-text' },
  { type: 'rubric', title: { en: 'Rubrics', ru: 'Рубрики' }, icon: 'folder' },
  { type: 'tag', title: { en: 'Tags', ru: 'Теги' }, icon: 'tag' },
  { type: 'service', title: { en: 'Services', ru: 'Услуги' }, icon: 'grid' },
  {
    type: 'service-category',
    title: { en: 'Service categories', ru: 'Категории услуг' },
    icon: 'folder',
  },
] as const

function linkRows(type: string, locale: string): LinkRow[] {
  if (type === 'page') {
    return [...pages.values()]
      .filter((record) => record.row.deleted_at === null)
      .map((record) => ({
        id: record.row.id,
        title: record.row.is_home ? 'Главная' : record.row.title,
        url: record.row.url,
        available: record.row.status !== 'draft' && record.row.url !== null,
        // The line of titles above it, which is what tells two pages of the same name apart.
        hint:
          ancestorsOf(record.row)
            .filter((node) => !node.is_home)
            .map((node) => node.title)
            .join(' / ') || null,
      }))
  }

  if (type === 'article') {
    return articles
      .filter((record) => record.deleted_at === null)
      .map((record) => articleRow(record, locale))
      .map((row) => ({
        id: row.id,
        title: row.title,
        url: row.url,
        available: row.status === 'published' || row.status === 'modified',
        hint:
          row.rubrics.map((one) => one.title).join(', ') || row.published_at?.slice(0, 10) || null,
      }))
  }

  if (type === 'rubric') {
    return rubrics.map((row) => ({
      id: row.id,
      title: row.name,
      url: row.url,
      available: row.is_visible && row.url !== null,
      hint: null,
    }))
  }

  if (type === 'service') {
    return services
      .filter((record) => record.deleted_at === null)
      .map((record) => serviceRow(record, locale))
      .map((row) => ({
        id: row.id,
        title: row.title,
        url: row.url,
        available: row.status === 'published' || row.status === 'modified',
        hint: row.categories.map((one) => one.title).join(', ') || null,
      }))
  }

  if (type === 'service-category') {
    return serviceCategories.map((row) => ({
      id: row.id,
      title: serviceText(row.title, locale) || row.name,
      url: row.url,
      available: row.is_visible && row.url !== null,
      hint: null,
    }))
  }

  return tags.map((row) => ({
    id: row.id,
    title: row.title,
    url: row.url,
    available: row.url !== null,
    hint: null,
  }))
}

/*
 * A link printed by a block template: the entity's address, worked out now rather than stored —
 * which is the whole point of keeping the entity and not the address.
 */
useLinkResolver((link) => {
  const type =
    typeof link.target === 'string' && link.target === 'entity' ? String(link.entity_type) : null
  const found =
    type === null
      ? null
      : (linkRows(type, 'ru').find((row) => row.id === Number(link.entity_id)) ?? null)

  const address = found?.url ?? (link.target === 'url' ? link.url : null)

  /* The anchor is appended rather than stored in the address, as `LinkUrls::href()` does it: an
     anchor with no address is a link to a place on the page the block is printed on. */
  const fragment = typeof link.hash === 'string' && link.hash !== '' ? `#${link.hash}` : ''

  return {
    ...link,
    url: typeof address === 'string' ? address + fragment : fragment || null,
    label: found?.title ?? null,
    available: link.target === 'entity' ? (found?.available ?? false) : true,
  }
})

on('GET', '/links/sources', ({ locale }) => ({
  data: LINK_SOURCES.map((source) => ({
    type: source.type,
    title: source.title[locale === 'ru' ? 'ru' : 'en'],
    icon: source.icon,
  })),
}))

on('GET', '/links/search', ({ locale, query }) => {
  const term = String(query.get('q') ?? '')
    .trim()
    .toLowerCase()
  const found = linkRows(String(query.get('type') ?? 'page'), locale).filter(
    (row) => term === '' || row.title.toLowerCase().includes(term),
  )

  return { data: found.slice(0, Number(query.get('limit') ?? 20)) }
})

on('POST', '/links/resolve', ({ locale, body }) => {
  const asked = Array.isArray(body.links) ? (body.links as { type: string; id: number }[]) : []
  const resolved: (LinkRow & { type: string })[] = []

  for (const { type, id } of asked) {
    const row = linkRows(String(type), locale).find((one) => one.id === Number(id))

    // A link whose entity is gone comes back missing rather than as an error: the form has to be
    // able to show which one fell out.
    if (row !== undefined) {
      resolved.push({ type: String(type), ...row })
    }
  }

  return { data: resolved }
})

/** The addresses this site has that no entity owns — two of them, as on the demo site. */
on('GET', '/links/routes', () => ({
  data: [
    { name: 'webx.blog.feed', path: '/blog' },
    { name: 'webx.blog.rss', path: '/blog/rss' },
    { name: 'webx.services.index', path: `/${SERVICES_PREFIX}` },
    { name: 'webx.recipes.index', path: `/${RECIPES_PREFIX}` },
  ],
}))

/* ------------------------------------------------------------------------ collections ----- */

/*
 * The sections a block can show records from (`wx-collection`), and the two there are here: the
 * FAQ (`faq.ts`) and the reviews (`reviews.ts`). The preview reads a block's choice the way the site does — in the language of
 * the page, which is Russian here, as everywhere else the preview draws.
 */
useCollectionResolver((source, value) => {
  if (source === 'faq') return resolveFaq(value, 'ru')
  if (source === 'reviews') return resolveReviews(value, 'ru')
  if (source === 'recipes') return resolveRecipes(value, 'ru')

  return { items: [], groups: [], filter: false }
})

on('GET', '/collections', ({ locale }) => ({ data: collectionSources(locale) }))

/* -------------------------------------------------------------------------- relations ----- */

/* What a `wx-relations` field picks from and names its choice by (`relations.ts`, §3.7). */
on('GET', '/relations/([\\w-]+)', ({ params, query, locale }) => {
  const found = relationCandidates(params[0], query, locale)

  if (found === null) throw new HttpFailure(404, `Nothing can be related to "${params[0]}".`)

  return { data: found }
})

/* -------------------------------------------------------------------------------- faq ----- */

/*
 * The questions (§4.6 of the FAQ spec): the whole list at once, no pages — the list is where
 * they are put in order. `POST` takes the values of the form, so a new question is written
 * before it exists and made on its first save.
 */
on('GET', '/faq/questions', ({ query, locale }) =>
  listFaqQuestions(Object.fromEntries(query), locale),
)

on('POST', '/faq/questions', ({ body, locale }) => {
  const written = writeFaqQuestion(null, (body.values ?? {}) as Record<string, unknown>)

  if ('errors' in written) throw new HttpFailure(422, 'Invalid', undefined, written.errors)

  return { data: faqQuestionDetail(written.record, locale) }
})

/* Before `{id}` for the reader; the pattern tells them apart anyway — a word, not a number. */
on('POST', '/faq/questions/reorder', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]).map(Number) : []

  reorderFaqQuestions(ids, typeof body.category === 'number' ? body.category : null)

  return { data: null }
})

on('GET', '/faq/questions/(\\d+)', ({ params, locale }) => ({
  data: faqQuestionDetail(faqQuestion(params[0]), locale),
}))

on('PUT', '/faq/questions/(\\d+)', ({ params, body, locale }) => {
  const record = faqQuestion(params[0])
  const written = writeFaqQuestion(record, (body.values ?? {}) as Record<string, unknown>)

  if ('errors' in written) throw new HttpFailure(422, 'Invalid', undefined, written.errors)

  return { data: faqQuestionDetail(record, locale) }
})

on('DELETE', '/faq/questions/(\\d+)', ({ params }) => {
  faqQuestion(params[0]).deleted_at = new Date().toISOString()

  return { data: null }
})

on('POST', '/faq/questions/(\\d+)/restore', ({ params, locale }) => {
  const record = findFaqQuestion(Number(params[0]))

  if (record === null || record.deleted_at === null) throw new HttpFailure(404, 'Not found.')

  record.deleted_at = null

  return { data: faqQuestionRow(record, locale) }
})

/* The FAQ's categories, through the shared category API: no address, so no prefix. */
on('GET', '/faq/categories', ({ locale }) => ({
  data: faqCategories
    .filter((category) => category.deleted_at === null)
    .map((category) => faqCategoryRow(category, locale)),
  prefix: null,
}))

on('POST', '/faq/categories', ({ body, locale }) => ({
  data: faqCategoryRow(createFaqCategory(body.title), locale),
}))

on('POST', '/faq/categories/reorder', ({ body }) => {
  reorderFaqCategories(Array.isArray(body.ids) ? (body.ids as number[]).map(Number) : [])

  return { data: null }
})

on('GET', '/faq/categories/(\\d+)', ({ params, locale }) => ({
  data: faqCategoryDetail(faqCategory(params[0]), locale),
}))

on('PUT', '/faq/categories/(\\d+)', ({ params, body, locale }) => {
  const record = faqCategory(params[0])
  const errors = writeFaqCategory(record, (body.values ?? {}) as Record<string, unknown>)

  if (errors !== null) throw new HttpFailure(422, 'Invalid', undefined, errors)

  return { data: faqCategoryDetail(record, locale) }
})

on('DELETE', '/faq/categories/(\\d+)', ({ params, locale }) => {
  const record = faqCategory(params[0])
  const count = Number(faqCategoryRow(record, locale).questions_count)

  // The panel keeps the button out of reach while a category holds anything.
  if (count > 0) throw new HttpFailure(422, `В категории ещё ${count} вопросов.`)

  record.deleted_at = new Date().toISOString()

  return { data: null }
})

function faqQuestion(id: string) {
  const record = findFaqQuestion(Number(id))

  // The bin is not reachable by id, as with route-model binding on the server.
  if (record === null || record.deleted_at !== null) throw new HttpFailure(404, 'No such question.')

  return record
}

function faqCategory(id: string) {
  const record = findFaqCategory(Number(id))

  if (record === null) throw new HttpFailure(404, 'No such category.')

  return record
}

/* ---------------------------------------------------------------------------- reviews ----- */

/*
 * The reviews (§4.7 of the reviews spec): the list whole, no pages, and a new review written
 * before it exists — the same shape as the questions of the FAQ, under `/reviews` itself rather
 * than a word below it, with the categories beside the records.
 */
on('GET', '/reviews', ({ query, locale }) => listReviews(Object.fromEntries(query), locale))

on('POST', '/reviews', ({ body, locale }) => {
  const written = writeReview(null, (body.values ?? {}) as Record<string, unknown>)

  if ('errors' in written) throw new HttpFailure(422, 'Invalid', undefined, written.errors)

  return { data: reviewDetail(written.record, locale) }
})

on('POST', '/reviews/reorder', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]).map(Number) : []

  reorderReviews(ids, typeof body.category === 'number' ? body.category : null)

  return { data: null }
})

on('GET', '/reviews/(\\d+)', ({ params, locale }) => ({
  data: reviewDetail(reviewRecord(params[0]), locale),
}))

on('PUT', '/reviews/(\\d+)', ({ params, body, locale }) => {
  const record = reviewRecord(params[0])
  const written = writeReview(record, (body.values ?? {}) as Record<string, unknown>)

  if ('errors' in written) throw new HttpFailure(422, 'Invalid', undefined, written.errors)

  return { data: reviewDetail(record, locale) }
})

on('DELETE', '/reviews/(\\d+)', ({ params }) => {
  reviewRecord(params[0]).deleted_at = new Date().toISOString()

  return { data: null }
})

on('POST', '/reviews/(\\d+)/restore', ({ params, locale }) => {
  const record = findReview(Number(params[0]))

  if (record === null || record.deleted_at === null) throw new HttpFailure(404, 'Not found.')

  record.deleted_at = null

  return { data: reviewRow(record, locale) }
})

/* The categories of the reviews, through the shared category API: no address, so no prefix. */
on('GET', '/reviews/categories', ({ locale }) => ({
  data: reviewCategories
    .filter((category) => category.deleted_at === null)
    .map((category) => reviewCategoryRow(category, locale)),
  prefix: null,
}))

on('POST', '/reviews/categories', ({ body, locale }) => ({
  data: reviewCategoryRow(createReviewCategory(body.title), locale),
}))

on('POST', '/reviews/categories/reorder', ({ body }) => {
  reorderReviewCategories(Array.isArray(body.ids) ? (body.ids as number[]).map(Number) : [])

  return { data: null }
})

on('GET', '/reviews/categories/(\\d+)', ({ params, locale }) => ({
  data: reviewCategoryDetail(reviewCategory(params[0]), locale),
}))

on('PUT', '/reviews/categories/(\\d+)', ({ params, body, locale }) => {
  const record = reviewCategory(params[0])
  const errors = writeReviewCategory(record, (body.values ?? {}) as Record<string, unknown>)

  if (errors !== null) throw new HttpFailure(422, 'Invalid', undefined, errors)

  return { data: reviewCategoryDetail(record, locale) }
})

on('DELETE', '/reviews/categories/(\\d+)', ({ params, locale }) => {
  const record = reviewCategory(params[0])
  const count = Number(reviewCategoryRow(record, locale).reviews_count)

  // The panel keeps the button out of reach while a category holds anything.
  if (count > 0) throw new HttpFailure(422, `В категории ещё ${count} отзывов.`)

  record.deleted_at = new Date().toISOString()

  return { data: null }
})

function reviewRecord(id: string) {
  const record = findReview(Number(id))

  // The bin is not reachable by id, as with route-model binding on the server.
  if (record === null || record.deleted_at !== null) throw new HttpFailure(404, 'No such review.')

  return record
}

function reviewCategory(id: string) {
  const record = findReviewCategory(Number(id))

  if (record === null) throw new HttpFailure(404, 'No such category.')

  return record
}

/* ------------------------------------------------------------------------------ menus ----- */

/**
 * The menus of the site, and the tree of one of them (§10).
 *
 * A menu is addressed by its key throughout, because a key is what a template calls it by — and
 * because a declared menu has no row until somebody saves into it, so for part of its life it
 * has no id to be addressed by.
 *
 * The tree comes back whole and nested: menus are small, and a level at a time would cost a
 * request per fold on a screen that is opened to see the shape of the thing. What is not small
 * is what the items point at, so every target is resolved here — the row draws a title and an
 * address, and neither of those is a column of the item.
 */

/** One menu as the left-hand list draws it. */
function menuRow(record: MenuRecord): unknown {
  return {
    id: record.id,
    key: record.key,
    title: record.title,
    declared: record.declared,
    items_count: countMenuItems(record.key),
    variants: record.variants,
    cache: { enabled: CACHE_ENABLED, built_at: CACHE_ENABLED ? record.built_at : null },
    // The session here is a superuser, so `menu.manage` is the half that is always true; what
    // is left is the rule itself — a declared menu keeps its key and keeps existing, because a
    // template names it by that spelling.
    can: { rename: !record.declared, delete: !record.declared },
  }
}

/** One item of the tree, with its target already resolved and its children under it. */
function menuItem(item: ItemRecord, locale: string): unknown {
  const candidate =
    item.target === 'entity' && item.entity_type !== null && item.entity_id !== null
      ? (linkRows(item.entity_type, locale).find((row) => row.id === item.entity_id) ?? null)
      : null

  const address = candidate?.url ?? (item.target === 'url' ? item.url : null)
  /* Appended rather than stored, as `LinkUrls::href()` does it: an anchor with no address is a
     link to a place on the page the menu is printed on. */
  const fragment = item.hash === null || item.hash === '' ? '' : `#${item.hash}`

  return {
    id: item.id,
    parent_id: item.parent_id,
    depth: depthOf(item),
    title: item.title,
    label: menuLabel(item, candidate, locale),
    target: item.target,
    entity_type: item.entity_type,
    entity_id: item.entity_id,
    url: item.url,
    hash: item.hash,
    href: address === null ? (fragment === '' ? null : fragment) : address + fragment,
    variant: item.variant,
    is_heading: item.is_heading,
    new_tab: item.new_tab,
    rel: item.rel,
    locales: item.locales,
    visible: item.visible,
    // A draft is a legitimate target — menus are built before the pages in them are published —
    // so the row is drawn dimmed rather than left out.
    available: item.target !== 'entity' || (candidate?.available ?? false),
    resolved: candidate,
    children: childrenOf(item.menu, item.id).map((child) => menuItem(child, locale)),
  }
}

/** What the row says: the item's own label, else the name of the thing it points at. */
function menuLabel(item: ItemRecord, candidate: LinkRow | null, locale: string): string {
  const written = (item.title[locale] ?? '').trim()

  return written === '' ? (candidate?.title ?? '').trim() : written
}

/** A key that is either declared or in the table; anything else is a menu that is not here. */
function knownMenu(key: string): string {
  if (findMenu(key) === null && !isDeclaredMenu(key)) {
    throw new HttpFailure(404, `No menu called ${key}.`)
  }

  return key
}

/** The row of a menu that has one. A declared menu that nobody has saved into has not. */
function savedMenu(key: string): MenuRecord {
  const record = findMenu(knownMenu(key))

  if (record === null) {
    throw new HttpFailure(404, `No menu called ${key}.`)
  }

  return record
}

function menuItemOf(key: string, id: string): ItemRecord {
  const item = itemOf(key, Number(id))

  if (item === null) {
    throw new HttpFailure(404, 'No such menu item.')
  }

  return item
}

/** The link a dialog sent, in the shape the columns keep it. */
function menuLink(body: Record<string, unknown>): Partial<ItemRecord> {
  const link = (body.link ?? {}) as Record<string, unknown>

  return {
    target: (link.target ?? 'none') as ItemRecord['target'],
    entity_type: typeof link.entity_type === 'string' ? link.entity_type : null,
    entity_id: typeof link.entity_id === 'number' ? link.entity_id : null,
    url: typeof link.url === 'string' && link.url !== '' ? link.url : null,
    hash: typeof link.hash === 'string' && link.hash !== '' ? link.hash : null,
    new_tab: link.new_tab === true,
    rel: Array.isArray(link.rel) ? (link.rel as ItemRecord['rel']) : [],
  }
}

/** Everything the item dialog sends beside the link itself. */
function menuFields(body: Record<string, unknown>): Partial<ItemRecord> {
  const fields: Partial<ItemRecord> = {}

  if (typeof body.title === 'object' && body.title !== null) {
    fields.title = body.title as ItemRecord['title']
  }

  if (typeof body.variant === 'string') fields.variant = body.variant
  if (typeof body.is_heading === 'boolean') fields.is_heading = body.is_heading
  if (typeof body.visible === 'boolean') fields.visible = body.visible
  if (Array.isArray(body.locales)) fields.locales = body.locales.map(String)

  return fields
}

/**
 * The spelling a template writes inside `menu('…')`, and the one thing this dialog can be
 * refused over — under the field it was typed into, which is where the dialog reads it.
 */
function checkMenuKey(key: string, current: string | null): void {
  if (!/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/.test(key)) {
    throw new HttpFailure(422, 'The key is not one a template could write.', undefined, {
      key: ['Lower case letters and digits, with single hyphens or underscores between them.'],
    })
  }

  if (key !== current && (findMenu(key) !== null || isDeclaredMenu(key))) {
    throw new HttpFailure(422, 'That key is taken.', undefined, {
      key: ['A menu with this key already exists.'],
    })
  }
}

/*
 * The SEO section, as far as the screens need it to be drawn: no rules, no redirects, no moves,
 * and a sitemap counted from what the fixtures have published. The map's own verdicts — noindex,
 * a canonical elsewhere — live on the server and are tested there; here the card only has to
 * have numbers to show.
 */
const emptyPage = {
  data: [],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 0, from: null, to: null },
}
let sitemapBuiltAt = new Date().toISOString()

function sitemapStatus(): unknown {
  const now = Date.now()
  const page = [...pages.values()].filter((record) => record.row.published_at !== null).length
  const article = articles.filter(
    (record) =>
      record.deleted_at === null &&
      record.published_at !== null &&
      Date.parse(record.published_at) <= now,
  ).length

  return {
    data: {
      enabled: true,
      url: 'http://localhost:5174/sitemap.xml',
      built_at: sitemapBuiltAt,
      files: { page, article, rubric: rubrics.length, routes: 1 },
      total: page + article + rubrics.length + 1,
      excluded: { noindex: tags.length, canonical: 0 },
    },
  }
}

on('GET', '/seo/urls', () => emptyPage)
on('GET', '/seo/redirects', () => emptyPage)
on('GET', '/seo/aliases', () => emptyPage)
on('GET', '/seo/sitemap', () => sitemapStatus())
on('POST', '/seo/sitemap', () => {
  sitemapBuiltAt = new Date().toISOString()

  return sitemapStatus()
})
on('POST', '/seo/test-url', ({ body }) => {
  const url = String((body as { url?: unknown }).url ?? '/')

  return {
    data: {
      url,
      redirect: null,
      route: null,
      matched: null,
      chain: [],
      seo: {
        title: null,
        h1: null,
        description: null,
        keywords: null,
        canonical: url,
        robots: null,
        og: {},
        json_ld: [],
      },
      sitemap: { included: false, reason: 'unknown' },
    },
  }
})

on('GET', '/menus', () => ({ data: listMenus().map(menuRow) }))

/*
 * Written before the one for a single menu: `{key}` would otherwise match the word `cache` and
 * take the request meant for all of them.
 */
on('POST', '/menus/cache/flush', () => {
  forgetAllMenus()

  return { data: null }
})

on('POST', '/menus', ({ body }) => {
  const key = String(body.key ?? '').trim()

  checkMenuKey(key, null)

  return { data: menuRow(createMenu(key, String(body.title ?? '').trim())) }
})

/* This is where a declared menu's row first appears: what was sent is written over it. */
on('PATCH', '/menus/([A-Za-z0-9_-]+)', ({ params, body }) => {
  const record = ensureMenu(knownMenu(params[0]))
  const wanted = typeof body.key === 'string' ? body.key.trim() : ''

  // Only when it was sent and only when it differs: a request that merely echoed the key back
  // would otherwise be refused for doing nothing.
  if (wanted !== '' && wanted !== record.key) {
    checkMenuKey(wanted, record.key)
    renameMenu(record, wanted)
  }

  record.title = String(body.title ?? record.title).trim()

  return { data: menuRow(record) }
})

on('DELETE', '/menus/([A-Za-z0-9_-]+)', ({ params }) => {
  const record = savedMenu(params[0])

  // The rule lives with the menu rather than with the button that is not drawn: an import and
  // an agent come through the same door as the panel.
  if (record.declared) {
    throw new HttpFailure(422, 'A menu a template asks for cannot be deleted.')
  }

  removeMenu(record.key)

  return { data: null }
})

on('POST', '/menus/([A-Za-z0-9_-]+)/cache/flush', ({ params }) => {
  forgetMenu(knownMenu(params[0]))

  return { data: null }
})

on('GET', '/menus/([A-Za-z0-9_-]+)/items', ({ params, locale }) => {
  const record = findMenu(knownMenu(params[0]))

  // A declared menu with no row is an empty tree rather than a 404: the section shows it from
  // the first day, and the first item saved into it is what makes the row.
  return {
    data: record === null ? [] : childrenOf(record.key, null).map((item) => menuItem(item, locale)),
  }
})

on('POST', '/menus/([A-Za-z0-9_-]+)/items', ({ params, body, locale }) => {
  const record = ensureMenu(knownMenu(params[0]))
  const parent = typeof body.parent_id === 'number' ? body.parent_id : null

  if (parent !== null && itemOf(record.key, parent) === null) {
    throw new HttpFailure(404, 'No such parent item.')
  }

  // At the end of its level and not where a form said: where an item sits is the tree's
  // business, and there is one gesture for it.
  const item = addMenuItem(record.key, {
    ...menuLink(body),
    ...menuFields(body),
    parent_id: parent,
  })

  forgetMenu(record.key)

  return { data: menuItem(item, locale) }
})

on('PATCH', '/menus/([A-Za-z0-9_-]+)/items/(\\d+)', ({ params, body, locale }) => {
  const record = savedMenu(params[0])
  const item = menuItemOf(record.key, params[1])

  Object.assign(item, menuLink(body), menuFields(body))
  forgetMenu(record.key)

  return { data: menuItem(item, locale) }
})

on('POST', '/menus/([A-Za-z0-9_-]+)/items/(\\d+)/move', ({ params, body }) => {
  const record = savedMenu(params[0])
  const item = menuItemOf(record.key, params[1])
  const parent = typeof body.parent_id === 'number' ? body.parent_id : null

  if (!moveMenuItem(item, parent, Number(body.index ?? 0))) {
    throw new HttpFailure(404, 'An item cannot be moved inside its own branch.')
  }

  forgetMenu(record.key)

  return { data: { id: item.id } }
})

/*
 * An item and everything under it. How many went is in the answer because the panel says it out
 * loud before asking, and the promise and the act have to be the same question.
 */
on('DELETE', '/menus/([A-Za-z0-9_-]+)/items/(\\d+)', ({ params }) => {
  const record = savedMenu(params[0])
  const deleted = removeMenuItem(menuItemOf(record.key, params[1]))

  forgetMenu(record.key)

  return { data: { deleted } }
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
  const row: RubricRecord = {
    id: Math.max(0, ...rubrics.map((one) => one.id)) + 1,
    name: title,
    title: asMap(body.title, title),
    slug: asMap(body.slug, slug),
    lead: {},
    path: `${PREFIX}/${slug}`,
    url: `https://webx-demo.test/${PREFIX}/${slug}`,
    cover: null,
    is_visible: true,
    position: rubrics.length + 1,
    articles_count: 0,
    deleted_at: null,
    seo: {},
    extra: {},
  }

  rubrics.push(row)

  return { data: { ...row, name: blogText(row.title, locale) || row.name } }
})

on('POST', '/blog/rubrics/reorder', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]) : []

  order(rubrics, ids)

  return { data: null }
})

on('GET', '/blog/rubrics/(\\d+)', ({ params, locale }) => ({
  data: rubricDetail(rubric(params[0]), locale),
}))

/*
 * The page of one rubric saves the values of `blog.category-form`, the way the real server does
 * (`CategoryForm::save()`): its own fields into their columns, the SEO card into its table, and
 * everything else the screen drew — a field a project patched on — into `extra`, merged rather
 * than replaced.
 */
on('PUT', '/blog/rubrics/(\\d+)', ({ params, body, locale }) => {
  const row = rubric(params[0])
  const values = (body.values ?? {}) as Record<string, unknown>
  const errors: Record<string, string[]> = {}

  if (values.title !== undefined && Object.values(asMap(values.title, '')).every((one) => !one)) {
    errors.title = ['Название нужно хотя бы на одном языке.']
  }

  const slugs = values.slug === undefined ? {} : asMap(values.slug, '')

  for (const [code, slug] of Object.entries(slugs)) {
    if (slug !== '' && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
      errors[`slug.${code}`] = ['Буквы, цифры и одиночные дефисы между ними.']
    }
  }

  if (Object.keys(errors).length > 0) throw new HttpFailure(422, 'Invalid', undefined, errors)

  for (const [name, value] of Object.entries(values)) {
    if (name === 'title') row.title = asMap(value, '')
    else if (name === 'slug') row.slug = asMap(value, '')
    else if (name === 'lead') row.lead = asMap(value, '')
    else if (name === 'is_visible') row.is_visible = value === true
    else if (name === 'seo') row.seo = (value ?? {}) as Record<string, unknown>
    else if (name === 'cover') {
      const path = (value as { path?: string } | null)?.path
      const file = typeof path === 'string' ? mediaByPath(path) : null

      row.cover =
        file === null ? null : { id: file.id, path: file.path, url: file.url, thumb: file.thumb }
    } else row.extra[name] = value
  }

  const slug = blogText(row.slug, locale) || blogText(row.slug, 'ru')

  row.name = blogText(row.title, locale) || row.name
  row.path = `${PREFIX}/${slug}`
  row.url = `https://webx-demo.test/${row.path}`

  return { data: rubricDetail(row, locale) }
})

/** One rubric as its page opens it: the row, the values of the screen and the prefix. */
function rubricDetail(row: RubricRecord, locale: string) {
  return {
    category: { ...row, name: blogText(row.title, locale) || row.name },
    values: {
      ...row.extra,
      title: row.title,
      slug: row.slug,
      is_visible: row.is_visible,
      lead: row.lead,
      cover: row.cover === null ? null : { path: row.cover.path, url: row.cover.url },
      seo: row.seo,
    },
    prefix: PREFIX,
  }
}

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

/* ---------------------------------------------------------------------------- services ----- */

/*
 * The catalogue (§4.6): the whole list at once and no pages — the list is where services are put
 * in order, and a drag cannot cross a page boundary. Narrowed to a category it comes in that
 * category's own order; otherwise in the order of the whole list. The other filters narrow
 * without reordering.
 */
on('GET', '/services', ({ query, locale }) => {
  const trashed = query.get('trashed') === '1'
  const search = (query.get('q') ?? '').trim().toLowerCase()
  const status = query.get('status') ?? ''
  const within = number(query.get('category'))

  let found = services.filter((record) => (record.deleted_at === null) !== trashed)

  if (trashed) {
    found.sort((one, two) => Date.parse(two.deleted_at ?? '') - Date.parse(one.deleted_at ?? ''))
  } else {
    if (search !== '') {
      // In any language the site has: the list draws the title a service has, and one written
      // only in English is on a Russian panel's screen.
      found = found.filter((record) =>
        [record.values.title, record.values.slug].some((value) =>
          Object.values(asMap(value, '')).some((one) => one.toLowerCase().includes(search)),
        ),
      )
    }

    if (status !== '') found = found.filter((record) => record.status === status)

    const inside = within === null ? null : findServiceCategory(within)

    if (within !== null) {
      const items = inside?.items ?? []

      found = found
        .filter((record) => items.includes(record.id))
        .sort((one, two) => items.indexOf(one.id) - items.indexOf(two.id))
    } else {
      found.sort((one, two) => one.position - two.position)
    }
  }

  return {
    data: found.map((record) => serviceRow(record, locale)),
    filters: {
      categories: serviceCategories.map((row) => ({
        id: row.id,
        title: serviceText(row.title, locale) || row.name,
      })),
    },
  }
})

on('POST', '/services', ({ body, locale }) => {
  const title = String(body.title ?? 'Новая услуга')
  const slug = typeof body.slug === 'string' && body.slug !== '' ? body.slug : slugify(title)

  const record: ServiceRecord = {
    id: Math.max(0, ...services.map((one) => one.id)) + 1,
    values: {
      title: { ru: title, en: title },
      slug: { ru: slug, en: slug },
      lead: { ru: '', en: '' },
      blocks: [],
      cover: null,
      categories: [],
      seo: {},
    },
    live: null,
    status: 'draft',
    // A new one goes to the end of the list — where the next person to reorder will see it.
    position: Math.max(0, ...services.map((one) => one.position)) + 1,
    published_at: null,
    updated_at: new Date().toISOString(),
    deleted_at: null,
    versions: [],
    snapshots: {},
  }

  services.push(record)

  return { data: serviceRow(record, locale) }
})

/*
 * The order of the list, as it is dragged: without a category the whole list, with one the
 * order inside it and nothing else (`CategoryRoutes::items()`). What the screen did not send
 * keeps its place after what it did.
 */
on('POST', '/services/reorder', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as unknown[]).map(Number) : []
  const within =
    body.category === undefined || body.category === null ? null : Number(body.category)

  if (within !== null) {
    const row = serviceCategory(String(within))

    row.items = [
      ...ids.filter((id) => row.items.includes(id)),
      ...row.items.filter((id) => !ids.includes(id)),
    ]

    return { data: null }
  }

  const rest = services
    .filter((record) => !ids.includes(record.id))
    .sort((one, two) => one.position - two.position)
  const sequence = [...ids.flatMap((id) => findService(id) ?? []), ...rest]

  sequence.forEach((record, index) => {
    record.position = index + 1
  })

  return { data: null }
})

on('GET', '/services/(\\d+)', ({ params, locale }) => ({
  data: serviceDetail(service(params[0]), locale),
}))

on('PUT', '/services/(\\d+)', ({ params, body, locale }) => {
  const record = service(params[0])
  const sent = { ...((body.values ?? {}) as Record<string, unknown>) }

  if (typeof body.revision === 'string' && body.revision !== serviceRevision(record)) {
    throw new HttpFailure(
      409,
      'Кто-то сохранил эту услугу, пока вы её редактировали.',
      serviceDetail(record, locale),
    )
  }

  const categories = sent.categories
  delete sent.categories

  /* Only what travelled: saving one tab must not empty another. */
  record.values = { ...record.values, ...sent }

  // The key alone, as a described screen stores it: an address beside it would expire.
  const picked = record.values.cover as { path?: unknown } | null

  record.values.cover = typeof picked?.path === 'string' ? { path: picked.path } : null
  record.values.seo = storedSeo(record.values.seo)

  if (Array.isArray(categories)) {
    fileService(record, categories.map(Number))
  }

  record.updated_at = new Date().toISOString()
  restateService(record)

  return { data: serviceDetail(record, locale) }
})

on('DELETE', '/services/(\\d+)', ({ params }) => {
  const record = service(params[0])

  record.deleted_at = new Date().toISOString()
  recountServices()

  return { data: null }
})

on('POST', '/services/(\\d+)/restore', ({ params, locale }) => {
  const record = service(params[0])

  record.deleted_at = null
  recountServices()

  return { data: serviceRow(record, locale) }
})

/* Throw away what is waiting and keep what the site is showing. */
on('POST', '/services/(\\d+)/discard', ({ params, locale }) => {
  const record = service(params[0])

  if (record.live !== null) {
    for (const field of serviceDraftedFields({ ...record.values, ...record.live })) {
      record.values[field] = serviceClone(record.live[field] ?? null)
    }
  }

  record.updated_at = new Date().toISOString()
  restateService(record)

  return { data: serviceDetail(record, locale) }
})

on('POST', '/services/(\\d+)/publish', ({ params, locale }) => {
  const record = service(params[0])
  const now = new Date().toISOString()
  const number = (record.versions[0]?.number ?? 0) + 1

  record.published_at = now
  record.status = 'published'
  record.live = serviceClone(record.values)
  record.updated_at = now
  record.snapshots[number] = serviceClone(record.values)
  record.versions.unshift({
    number,
    created_at: now,
    author: 'Анна Ковальчук',
    source: 'panel',
    comment: null,
    is_pinned: false,
  })

  return { data: serviceRow(record, locale) }
})

on('POST', '/services/(\\d+)/unpublish', ({ params, locale }) => {
  const record = service(params[0])

  // Not "draft": it was on the site this morning, and only its history tells the two apart.
  record.status = 'unpublished'
  record.published_at = null
  record.updated_at = new Date().toISOString()

  return { data: serviceRow(record, locale) }
})

on('GET', '/services/(\\d+)/versions', ({ params }) => ({ data: service(params[0]).versions }))

on('POST', '/services/(\\d+)/versions/(\\d+)/restore', ({ params, locale }) => {
  const record = service(params[0])
  const snapshot = record.snapshots[Number(params[1])]

  if (snapshot === undefined) {
    throw new HttpFailure(404, 'No such version.')
  }

  /* An old publication becomes the draft; putting it on the site is a separate step. */
  for (const field of serviceDraftedFields(snapshot)) {
    record.values[field] = serviceClone(snapshot[field])
  }

  record.updated_at = new Date().toISOString()
  restateService(record)

  return { data: serviceDetail(record, locale) }
})

/*
 * The categories: the panel's shared category API (`CategoryRoutes::register()`), the same
 * answers the blog's rubrics give under their own path.
 */
on('GET', '/services/categories', ({ locale }) => ({
  data: serviceCategories.map((row) => serviceCategoryRow(row, locale)),
  prefix: SERVICES_PREFIX,
}))

on('POST', '/services/categories', ({ body, locale }) => {
  const title = localized(body.title) || 'Новая категория'
  const slug = localized(body.slug) || slugify(title)
  const row: ServiceCategoryRecord = {
    id: Math.max(0, ...serviceCategories.map((one) => one.id)) + 1,
    name: title,
    title: asMap(body.title, title),
    slug: asMap(body.slug, slug),
    lead: {},
    path: `${SERVICES_PREFIX}/${slug}`,
    url: `https://webx-demo.test/${SERVICES_PREFIX}/${slug}`,
    cover: null,
    is_visible: true,
    position: serviceCategories.length + 1,
    services_count: 0,
    deleted_at: null,
    seo: {},
    extra: {},
    items: [],
  }

  serviceCategories.push(row)

  return { data: serviceCategoryRow(row, locale) }
})

on('POST', '/services/categories/reorder', ({ body }) => {
  const ids = Array.isArray(body.ids) ? (body.ids as number[]) : []

  order(serviceCategories, ids)

  return { data: null }
})

on('GET', '/services/categories/(\\d+)', ({ params, locale }) => ({
  data: serviceCategoryDetail(serviceCategory(params[0]), locale),
}))

/* The values of `services.category-form`: own fields into columns, the rest into `extra`. */
on('PUT', '/services/categories/(\\d+)', ({ params, body, locale }) => {
  const row = serviceCategory(params[0])
  const values = (body.values ?? {}) as Record<string, unknown>
  const errors: Record<string, string[]> = {}

  if (values.title !== undefined && Object.values(asMap(values.title, '')).every((one) => !one)) {
    errors.title = ['Название нужно хотя бы на одном языке.']
  }

  for (const [code, slug] of Object.entries(
    values.slug === undefined ? {} : asMap(values.slug, ''),
  )) {
    if (slug !== '' && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
      errors[`slug.${code}`] = ['Буквы, цифры и одиночные дефисы между ними.']
    }
  }

  if (Object.keys(errors).length > 0) throw new HttpFailure(422, 'Invalid', undefined, errors)

  for (const [name, value] of Object.entries(values)) {
    if (name === 'title') row.title = asMap(value, '')
    else if (name === 'slug') row.slug = asMap(value, '')
    else if (name === 'lead') row.lead = asMap(value, '')
    else if (name === 'is_visible') row.is_visible = value === true
    else if (name === 'seo') row.seo = (value ?? {}) as Record<string, unknown>
    else if (name === 'cover') {
      const path = (value as { path?: string } | null)?.path
      const file = typeof path === 'string' ? mediaByPath(path) : null

      row.cover =
        file === null ? null : { id: file.id, path: file.path, url: file.url, thumb: file.thumb }
    } else row.extra[name] = value
  }

  const slug = serviceText(row.slug, locale) || serviceText(row.slug, 'ru')

  row.name = serviceText(row.title, locale) || row.name
  row.path = `${SERVICES_PREFIX}/${slug}`
  row.url = `https://webx-demo.test/${row.path}`

  return { data: serviceCategoryDetail(row, locale) }
})

on('DELETE', '/services/categories/(\\d+)', ({ params }) => {
  const row = serviceCategory(params[0])

  // The panel keeps the button out of reach while a category holds anything.
  if (row.services_count > 0) {
    throw new HttpFailure(422, `В категории ещё ${row.services_count} услуг.`)
  }

  serviceCategories.splice(serviceCategories.indexOf(row), 1)

  for (const record of services) {
    record.values.categories = serviceCategoryIds(record).filter((id) => id !== row.id)
  }

  return { data: null }
})

function service(id: string): ServiceRecord {
  const record = findService(Number(id))

  if (record === null) {
    throw new HttpFailure(404, 'No such service.')
  }

  return record
}

function serviceCategory(id: string): ServiceCategoryRecord {
  const row = findServiceCategory(Number(id))

  if (row === null) {
    throw new HttpFailure(404, 'No such category.')
  }

  return row
}

/** One service as its editor opens it (`ServiceForm::describe()`). */
function serviceDetail(record: ServiceRecord, locale: string): Record<string, unknown> {
  return {
    service: serviceRow(record, locale),
    values: withSeoImage(serviceClone(record.values)),
    revision: serviceRevision(record),
    prefix: SERVICES_PREFIX,
    preview_url: `/preview/service/${record.id}`,
  }
}

/** One category as its page opens it: the row, the values of the screen and the prefix. */
function serviceCategoryDetail(row: ServiceCategoryRecord, locale: string) {
  return {
    category: serviceCategoryRow(row, locale),
    values: {
      ...row.extra,
      title: row.title,
      slug: row.slug,
      is_visible: row.is_visible,
      lead: row.lead,
      cover: row.cover === null ? null : { path: row.cover.path, url: row.cover.url },
      seo: row.seo,
    },
    prefix: SERVICES_PREFIX,
  }
}

/* ----------------------------------------------------------------------------- recipes ----- */

/*
 * The recipes (§5.10 of the recipes spec): the whole list at once and in its one order — no
 * category has an order of its own (decision 4), so every filter just narrows.
 */
on('GET', '/recipes', ({ query, locale }) => listRecipes(query, locale))

on('POST', '/recipes', ({ body, locale }) => {
  const title = String(body.title ?? 'Новый рецепт')
  const slug = typeof body.slug === 'string' && body.slug !== '' ? body.slug : slugify(title)
  const record = createRecipe(title, slug)

  return {
    data: { recipe: recipeRow(record, locale), values: recipeDetail(record, locale).values },
  }
})

/* No `category` here, ever: the one order is the only one there is. */
on('POST', '/recipes/reorder', ({ body }) => {
  reorderRecipes(Array.isArray(body.ids) ? (body.ids as unknown[]).map(Number) : [])

  return { data: null }
})

on('GET', '/recipes/(\\d+)', ({ params, locale }) => ({
  data: recipeDetail(recipe(params[0]), locale),
}))

on('PUT', '/recipes/(\\d+)', ({ params, body, locale }) => {
  const record = recipe(params[0])
  const sent = { ...((body.values ?? {}) as Record<string, unknown>) }

  if (typeof body.revision === 'string' && body.revision !== recipeRevision(record)) {
    throw new HttpFailure(
      409,
      'Кто-то сохранил этот рецепт, пока вы его редактировали.',
      recipeDetail(record, locale),
    )
  }

  if (sent.title !== undefined && Object.values(asMap(sent.title, '')).every((one) => !one)) {
    throw new HttpFailure(422, 'Invalid', undefined, {
      title: ['Название нужно хотя бы на одном языке.'],
    })
  }

  if (sent.seo !== undefined) sent.seo = storedSeo(sent.seo)

  writeRecipe(record, sent)

  return { data: recipeDetail(record, locale) }
})

on('DELETE', '/recipes/(\\d+)', ({ params }) => {
  recipe(params[0]).deleted_at = new Date().toISOString()

  return { data: null }
})

on('POST', '/recipes/(\\d+)/restore', ({ params, locale }) => {
  const record = recipe(params[0])

  record.deleted_at = null

  return { data: recipeRow(record, locale) }
})

on('POST', '/recipes/(\\d+)/discard', ({ params, locale }) => {
  const record = recipe(params[0])

  discardRecipe(record)

  return { data: recipeDetail(record, locale) }
})

on('POST', '/recipes/(\\d+)/publish', ({ params, locale }) => {
  const record = recipe(params[0])

  publishRecipe(record)

  return { data: recipeRow(record, locale) }
})

on('POST', '/recipes/(\\d+)/unpublish', ({ params, locale }) => {
  const record = recipe(params[0])

  record.status = 'unpublished'
  record.published_at = null
  record.updated_at = new Date().toISOString()

  return { data: recipeRow(record, locale) }
})

on('GET', '/recipes/(\\d+)/versions', ({ params }) => ({ data: recipe(params[0]).versions }))

on('POST', '/recipes/(\\d+)/versions/(\\d+)/restore', ({ params, locale }) => {
  const record = recipe(params[0])

  if (!restoreRecipeVersion(record, Number(params[1]))) {
    throw new HttpFailure(404, 'No such version.')
  }

  return { data: recipeDetail(record, locale) }
})

/*
 * The categories and the nutrients: the panel's shared category API twice, under two paths —
 * a category has an address, a nutrient does not.
 */
for (const kind of ['categories', 'nutrients'] as const) {
  const path = `/recipes/${kind}`

  on('GET', path, ({ locale }) => ({
    data: recipeTerms(kind)
      .filter((one) => one.deleted_at === null)
      .map((one) => recipeTermRow(kind, one, locale)),
    prefix: kind === 'categories' ? RECIPES_PREFIX : null,
  }))

  on('POST', path, ({ body, locale }) => {
    const title = localized(body.title) || 'Новая категория'
    const slug = localized(body.slug) || slugify(title)

    return {
      data: recipeTermRow(kind, createRecipeTerm(kind, asMap(body.title, title), slug), locale),
    }
  })

  on('POST', `${path}/reorder`, ({ body }) => {
    reorderRecipeTerms(kind, Array.isArray(body.ids) ? (body.ids as unknown[]).map(Number) : [])

    return { data: null }
  })

  on('GET', `${path}/(\\d+)`, ({ params, locale }) => ({
    data: recipeTermDetail(kind, recipeTerm(kind, params[0]), locale),
  }))

  on('PUT', `${path}/(\\d+)`, ({ params, body, locale }) => {
    const record = recipeTerm(kind, params[0])
    const errors = writeRecipeTerm(
      kind,
      record,
      (body.values ?? {}) as Record<string, unknown>,
      locale,
    )

    if (errors !== null) throw new HttpFailure(422, 'Invalid', undefined, errors)

    return { data: recipeTermDetail(kind, record, locale) }
  })

  on('DELETE', `${path}/(\\d+)`, ({ params, locale }) => {
    const record = recipeTerm(kind, params[0])
    const count = recipeTermRow(kind, record, locale).recipes_count

    // The panel keeps the button out of reach while anything is filed here.
    if (count > 0) throw new HttpFailure(422, `Рецептов здесь ещё: ${count}.`)

    record.deleted_at = new Date().toISOString()

    return { data: null }
  })
}

function recipe(id: string): RecipeRecord {
  const record = findRecipe(Number(id))

  if (record === null) throw new HttpFailure(404, 'No such recipe.')

  return record
}

function recipeTerm(kind: RecipeTermKind, id: string) {
  const record = findRecipeTerm(kind, Number(id))

  if (record === null) throw new HttpFailure(404, 'No such category.')

  return record
}

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

function rubric(id: string): RubricRecord {
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

/** The draft of a service, on the same page every other preview is drawn on. */
function previewService(id: number): string {
  const record = findService(id)

  if (record === null) {
    return missingPreview('No such service.')
  }

  return document(
    serviceText(record.values.title, 'ru') || `#${record.id}`,
    (record.values.blocks ?? []) as Block[],
  )
}

/**
 * The draft of a recipe, drawn as its page (§5.5) — no blocks: the module's view is the page, so
 * the playground draws it in code (`recipes-site.ts`) inside the same layout as everything else.
 */
function previewRecipe(id: number): string {
  const record = findRecipe(id)

  if (record === null) {
    return missingPreview('No such recipe.')
  }

  const page = recipePage(record, 'ru')

  return siteLayout(
    page.title || `#${record.id}`,
    `<style>${RECIPE_PAGE_STYLES}</style>`,
    drawRecipePage(page),
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

  /* And the ones the blocks' templates call with a tag, which no tree names. */
  const called: string[] = []

  const html = nodes
    .filter((node) => node.hidden !== true)
    .map((node) => draw(node, called))
    .join('\n')

  return siteLayout(title, `<style>${[styles, ...called].join('\n')}</style>`, html)
}

/**
 * The layout of the playground's site — what `<x-layout>` is on a real one: a header with the
 * `header` menu, a footer with the `footer` one, and the site's own base styles.
 *
 * Every picture of the site goes through it: the preview of a page, of an article, and the stage
 * a block type is drawn on in its editor. A block is only worth judging against the page it will
 * stand on — against the browser's defaults every block looks like a draft.
 */
function siteLayout(title: string, head: string, body: string): string {
  const top = siteMenu('header')
    .map((item) => `<a href="${item.href ?? '#'}">${escapeHtml(item.label)}</a>`)
    .join('')

  const groups = siteMenu('footer')
    .map((item) =>
      item.is_heading
        ? `<div class="site-footer__group"><strong>${escapeHtml(item.label)}</strong>${item.children
            .map((child) => `<a href="${child.href ?? '#'}">${escapeHtml(child.label)}</a>`)
            .join('')}</div>`
        : `<div class="site-footer__group"><a href="${item.href ?? '#'}">${escapeHtml(item.label)}</a></div>`,
    )
    .join('')

  return `<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>${escapeHtml(title)}</title>
    <style>${SITE_STYLES}</style>
    ${head}
  </head>
  <body>
    <header class="site-header">
      <div class="site-wrap site-header__inner">
        <a class="site-logo" href="/">Webx Demo</a>
        <nav class="site-nav">${top}</nav>
      </div>
    </header>
    <main class="site-main">
${body}
    </main>
    <footer class="site-footer">
      <div class="site-wrap site-footer__inner">${groups}</div>
      <div class="site-wrap site-footer__legal">© 2026 Webx Demo</div>
    </footer>
  </body>
</html>`
}

/** What a site's base CSS does before any block adds its own: a typeface, a ground, a width. */
const SITE_STYLES = `
  *, *::before, *::after { box-sizing: border-box; }
  body { margin: 0; font: 16px/1.6 Inter, "Segoe UI", system-ui, -apple-system, sans-serif; color: #1f2430; background: #fff; }
  h1, h2, h3, h4 { line-height: 1.2; margin: 0 0 .5em; font-weight: 700; }
  p { margin: 0 0 1em; }
  a { color: #2f6fdb; }
  img { max-width: 100%; height: auto; }
  .site-wrap { max-width: 1160px; margin: 0 auto; padding: 0 24px; }
  .site-header { border-bottom: 1px solid #e6e8ee; background: #fff; }
  .site-header__inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; min-height: 68px; flex-wrap: wrap; }
  .site-logo { font-weight: 800; font-size: 20px; color: #1f2430; text-decoration: none; }
  .site-nav { display: flex; gap: 20px; flex-wrap: wrap; }
  .site-nav a { color: #1f2430; text-decoration: none; font-weight: 500; }
  .site-footer { margin-top: 48px; padding: 40px 0 24px; background: #11151f; color: #c7cbd6; }
  .site-footer a { color: #c7cbd6; text-decoration: none; display: block; margin-top: 6px; }
  .site-footer strong { color: #fff; }
  .site-footer__inner { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 24px; }
  .site-footer__legal { margin-top: 32px; font-size: 14px; color: #7d8494; }
`

interface SiteLink {
  label: string
  href: string | null
  is_heading: boolean
  visible: boolean
  available: boolean
  children: SiteLink[]
}

/** A menu as the site prints it: what is switched off or points nowhere is left out. */
function siteMenu(key: string): SiteLink[] {
  const shown = (item: SiteLink): boolean => item.visible && item.available

  return childrenOf(key, null)
    .map((item) => menuItem(item, 'ru') as SiteLink)
    .filter(shown)
    .map((item) => ({ ...item, children: item.children.filter(shown) }))
}

function escapeHtml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

/**
 * The page a block type is drawn on in its editor — `StageController` on a real site: the
 * layout with an empty place and an empty `<style>`, which the panel fills on every change.
 */
function blockStage(): string {
  return siteLayout(
    'Block',
    '<script src="/blocks-runtime.js"></script><style id="wx-stage-styles"></style>',
    '<!--wx:sample--><!--/wx:sample-->',
  )
}

function draw(node: Block, called: string[] = []): string {
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
        .map((child) => draw(child, called))
        .join('\n')
    }
  }

  const html = renderTemplate(
    type.content.template,
    node.values,
    children,
    type.content.schema,
    tagCaller(0, [type.slug], called),
  )

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

        if (url.pathname === '/_preview/block-stage') {
          response.setHeader('Content-Type', 'text/html; charset=utf-8')
          response.end(blockStage())

          return
        }

        const preview = url.pathname.match(/^\/preview\/(page|article|service|recipe)\/(\d+)$/)

        if (preview !== null) {
          const id = Number(preview[2])

          response.setHeader('Content-Type', 'text/html; charset=utf-8')
          response.end(
            preview[1] === 'page'
              ? previewPage(id)
              : preview[1] === 'service'
                ? previewService(id)
                : preview[1] === 'recipe'
                  ? previewRecipe(id)
                  : previewArticle(id),
          )

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
