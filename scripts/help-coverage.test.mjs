import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { describe, expect, it } from 'vitest'

// The half of the coverage of help the server cannot see: it knows no routes of the panel, those
// are in the npm packages (WEBX_UI_PANEL_HELP.md §6.3). Reads the built packages, so it runs after
// `pnpm build`, the way the factories get called on a site: from `extra.webx.panel`.

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const phpPackages = join(root, 'php', 'packages')

// Routes with nothing to explain, with why. The rest of §6.5 (`webx.home`, `webx.login`,
// `webx.not-found`, `webx.site-bar`) never reaches this list: `createAdmin` adds those, not a module.
const EXEMPT = {
  '/catalog': 'A redirect to the products: there is no screen behind it.',
}

// What has no text yet, with why; shared with `AssertsHelp`. A wave strikes its keys out in the
// commit that brings their texts, and the last one deletes the file.
const pendingFile = join(phpPackages, 'module-admin', 'tests', 'help-pending.json')
const PENDING = existsSync(pendingFile) ? JSON.parse(readFileSync(pendingFile, 'utf8')) : {}

const manifests = readdirSync(phpPackages)
  .map((name) => join(phpPackages, name, 'composer.json'))
  .filter(existsSync)
  .map((file) => JSON.parse(readFileSync(file, 'utf8')))

const hasText = (key) =>
  readdirSync(phpPackages).some((name) =>
    existsSync(join(phpPackages, name, 'resources', 'help', 'en', `${key}.md`)),
  )

/** The modules a package puts into the panel, by the same two lines `webx:panel` writes out. */
async function modulesOf(panel) {
  const scope = {}

  for (const line of [panel.import].flat()) {
    const [, names, from] = line.match(/import \{(.*)\} from '@webx-ui\/(.*)'/)
    const module = await import(
      pathToFileURL(join(root, 'packages', from, 'dist', 'index.js')).href
    )

    for (const name of names.split(',').map((word) => word.trim())) scope[name] = module[name]
  }

  return [panel.register]
    .flat()
    .flatMap((call) =>
      new Function(...Object.keys(scope), `return ${call.replace(/^\.\.\./, '')}`)(
        ...Object.values(scope),
      ),
    )
}

const flatten = (routes = []) => routes.flatMap((route) => [route, ...flatten(route.children)])

async function panelRoutes() {
  const panels = manifests.map((manifest) => manifest.extra?.webx?.panel).filter(Boolean)
  const modules = (await Promise.all(panels.map(modulesOf))).flat()

  return modules.flatMap((module) => flatten(module.routes))
}

describe('help of the panel', () => {
  it('finds the routes of the panel', async () => {
    expect((await panelRoutes()).length).toBeGreaterThan(100)
  })

  it('names a key of help on every route', async () => {
    const without = (await panelRoutes())
      .filter((route) => !route.meta?.help && !(route.path in EXEMPT))
      .map((route) => route.name ?? route.path)

    expect(without).toEqual([])
  })

  it('has a text for every key, or says why not yet', async () => {
    const keys = new Set((await panelRoutes()).map((route) => route.meta?.help).filter(Boolean))

    expect([...keys].filter((key) => !hasText(key) && !(key in PENDING))).toEqual([])
  })

  it('keeps no key on the list once its text is there', () => {
    const keys = Object.keys(PENDING).filter((key) => !key.includes('#'))

    expect(keys.filter(hasText)).toEqual([])
  })
})
