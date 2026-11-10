import { readdirSync, readFileSync } from 'node:fs'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

/**
 * No dialog of the panel takes a machine name in a plain input (#125): a code field is
 * `<wx-code-field>`, which makes a code of whatever is typed instead of letting the save refuse
 * it. A new `<wx-input v-model="…code">` fails here unless it says why below. The server is held
 * to the same by `CodeCoverageTest` of `webx-ui/localization`; screens by the same test.
 */
const PACKAGES = join(dirname(fileURLToPath(import.meta.url)), '..', '..')

/** Inputs bound to a code-like name that are not a typed machine name, with the reason. */
const ALLOWED: Record<string, string> = {
  'module-blog/src/TagCreateDialog.vue#slug': 'an address: HasLatinSlug brings it',
  'module-catalog-landings/src/GenerateDialog.vue#templates.slug':
    'a template of addresses, {category}-{value}',
  'module-catalog-properties/src/IntervalsField.vue#(item as Row).slug':
    'an address: HasLatinSlug brings it',
  'module-catalog-properties/src/ValueDialog.vue#slug': 'an address: HasLatinSlug brings it',
  'module-delivery/src/RegionDialog.vue#code': 'an ISO 3166-2 code of a region, upper case',
  'module-pages/src/PageCreateDialog.vue#slug': 'an address: HasLatinSlug brings it',
}

/** The name a binding ends with that makes it a code. */
const CODE = /(?:^|[.\s])(code|key|slug|toggleSlug)\s*$/

function vueFiles(folder: string): string[] {
  return readdirSync(folder, { withFileTypes: true }).flatMap((entry) => {
    const path = join(folder, entry.name)
    if (entry.isDirectory()) return entry.name === 'node_modules' ? [] : vueFiles(path)
    return entry.name.endsWith('.vue') ? [path] : []
  })
}

describe('code fields of the panel', () => {
  it('are wx-code-field, not wx-input', () => {
    const found: string[] = []

    for (const name of readdirSync(PACKAGES)) {
      if (!name.startsWith('module-')) continue

      for (const file of vueFiles(join(PACKAGES, name, 'src'))) {
        const source = readFileSync(file, 'utf8')

        for (const tag of source.matchAll(/<wx-input\b[^>]*>/g)) {
          const binding = /\sv-model(?::model-value)?="([^"]+)"/.exec(tag[0])?.[1]
          if (binding === undefined || !CODE.test(binding)) continue

          const key = `${relative(PACKAGES, file)}#${binding}`
          if (ALLOWED[key] === undefined) found.push(key)
        }
      }
    }

    expect(found).toEqual([])
  })
})
