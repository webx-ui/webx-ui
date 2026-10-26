// Builds resources/js/configurator.js into dist/configurator.js — the widget of the site (§12.4),
// with the engine of @webx-ui/module-configurator built in from its source. dist/ is committed: a
// site installs the package from Composer and has no Node to build it with. The build also writes
// dist/sources.json, the hashes of what it was built from, and the package's test fails when the
// sources changed and dist/ did not.
//
// Nothing imported but Node's own: the config runs with the monorepo's Vite (`npx vite build` in
// this folder) and has nothing of its own to install.

import { createHash } from 'node:crypto'
import { readFileSync, readdirSync } from 'node:fs'
import { join, relative, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('.', import.meta.url))
const engine = join(root, '../../../packages/module-configurator/src/engine')

// The same walk and the same hash as tests/DistTest.php: the widget and the engine, without their
// tests, line endings normalised.
function sources() {
  const files = {}
  const walk = (directory) => {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
      const path = join(directory, entry.name)

      if (entry.isDirectory()) {
        walk(path)
      } else if (!entry.name.includes('.test.')) {
        const content = readFileSync(path, 'utf8').replace(/\r\n/g, '\n')
        files[relative(root, path).split(sep).join('/')] = createHash('sha256')
          .update(content)
          .digest('hex')
      }
    }
  }

  return {
    name: 'webx-configurator-sources',
    apply: 'build',
    generateBundle() {
      for (const name of Object.keys(files)) delete files[name]
      walk(join(root, 'resources/js'))
      walk(engine)

      const sorted = Object.fromEntries(Object.entries(files).sort(([a], [b]) => (a < b ? -1 : 1)))

      this.emitFile({
        type: 'asset',
        fileName: 'sources.json',
        source: JSON.stringify({ algorithm: 'sha256', files: sorted }, null, 2) + '\n',
      })
    },
  }
}

export default {
  root,
  publicDir: false,
  logLevel: 'warn',
  plugins: [sources()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    assetsDir: '',
    modulePreload: false,
    target: 'es2022',
    rollupOptions: {
      input: { configurator: join(root, 'resources/js/configurator.js') },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name].js',
        format: 'es',
      },
    },
  },
}
