import { existsSync, readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const here = dirname(fileURLToPath(import.meta.url))
const dist = resolve(here, '../dist/tokens.css')
const php = resolve(here, '../../../php/packages/module-admin/resources/css/tokens.css')

describe('the copy module-admin carries', () => {
  // Without a build there is nothing to compare against; the gate builds before it tests.
  it.skipIf(!existsSync(dist))('is the stylesheet this package builds', () => {
    expect(readFileSync(php, 'utf8')).toBe(readFileSync(dist, 'utf8'))
  })
})
