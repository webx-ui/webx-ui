import { existsSync, readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
// @ts-expect-error — a build script, plain JavaScript without declarations
import { serverStyles, serverStylesPath } from '../scripts/server-styles.mjs'

describe('the component styles module-admin carries for its Blade pages', () => {
  // A checkout without the php half has nothing to compare.
  it.skipIf(!existsSync(serverStylesPath as string))(
    'are the components as they are written',
    () => {
      // `node packages/core/scripts/server-styles.mjs` writes them again.
      expect(readFileSync(serverStylesPath as string, 'utf8')).toBe(serverStyles())
    },
  )
})
