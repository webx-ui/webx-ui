// Builds src/ into dist/: the files `php artisan webx:theme:sync` publishes on a site and
// `@webxTheme` links. dist/ is committed — a site installs this theme from Composer and has no
// Node to build it with — so the build also writes dist/sources.json, the hashes of what it was
// built from, and the theme's test (ThemeContract) fails when src/ changed and dist/ did not.
//
// src/js/theme.js is built only when it exists: a theme's script is rare, and a slider, a
// lightbox, a menu or a dialog is a widget, not a script of the theme's.

import { createHash } from 'node:crypto'
import { existsSync, readFileSync, readdirSync } from 'node:fs'
import { join, relative, sep } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('.', import.meta.url))

// The same walk and the same hash as WebxUi\Themes\ThemeSources: every file under src/, line
// endings normalised, so a Windows checkout and CI agree on what "built from" means.
function sources() {
  const files = {}
  const walk = (directory) => {
    for (const entry of readdirSync(directory, { withFileTypes: true })) {
      const path = join(directory, entry.name)

      if (entry.isDirectory()) {
        walk(path)
      } else {
        const content = readFileSync(path, 'utf8').replace(/\r\n/g, '\n')
        files[relative(root, path).split(sep).join('/')] = createHash('sha256')
          .update(content)
          .digest('hex')
      }
    }
  }

  return {
    name: 'webx-theme-sources',
    apply: 'build',
    generateBundle() {
      for (const name of Object.keys(files)) delete files[name]
      walk(join(root, 'src'))

      const sorted = Object.fromEntries(Object.entries(files).sort(([a], [b]) => (a < b ? -1 : 1)))

      this.emitFile({
        type: 'asset',
        fileName: 'sources.json',
        source: JSON.stringify({ algorithm: 'sha256', files: sorted }, null, 2) + '\n',
      })
    },
  }
}

const input = { theme: join(root, 'src/css/theme.css') }

if (existsSync(join(root, 'src/js/theme.js'))) {
  input.script = join(root, 'src/js/theme.js')
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
    rollupOptions: {
      input,
      output: {
        assetFileNames: '[name][extname]',
        // The stylesheet's entry leaves an empty chunk that would take the name theme.js first.
        entryFileNames: (chunk) => (chunk.name === 'script' ? 'theme.js' : '[name]-entry.js'),
      },
    },
  },
}
