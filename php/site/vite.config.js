import { createHash } from 'node:crypto'
import { existsSync, readFileSync } from 'node:fs'
import { relative, sep } from 'node:path'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

// What the bundle was built from, by content: `webx:doctor` hashes the same files and compares.
//
// Not by time. The Dockerfile builds the front end in a stage of its own, and when nothing in
// it changed Docker takes that stage from the cache — a bundle from an older build beside
// sources copied a minute ago, so every comparison of modification times says the bundle is
// stale on an image built that very minute.
//
// Every module the build pulled in, not just the entries: an edit to a file the entry imports
// is the change a stale bundle hides. The site's own files only — packages under node_modules
// are pinned by the lock file, and a link into a checkout elsewhere is not the site's to hash.
function sources() {
    let root = ''

    return {
        name: 'webx-sources',
        apply: 'build',
        configResolved(config) {
            root = config.root
        },
        generateBundle() {
            const files = {}

            for (const id of this.getModuleIds()) {
                const path = id.split('?')[0]
                const name = relative(root, path).split(sep).join('/')

                if (id.startsWith('\0') || name.startsWith('..') || name.includes('node_modules/') || !existsSync(path)) {
                    continue
                }

                files[name] = createHash('sha256').update(readFileSync(path)).digest('hex')
            }

            this.emitFile({
                type: 'asset',
                fileName: 'webx-sources.json',
                source: JSON.stringify({ algorithm: 'sha256', files: Object.fromEntries(Object.entries(files).sort()) }, null, 2) + '\n',
            })
        },
    }
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        sources(),
    ],

    // Set by docker-compose.dev.yml. Inside a container the dev server has to listen on every
    // interface or nothing outside it can reach the port, and the browser still reaches it on
    // localhost — which is what has to end up in public/hot, so Laravel points the page there.
    // Polling because a bind mount delivers no file events to watch.
    server: process.env.VITE_DOCKER
        ? { host: '0.0.0.0', hmr: { host: 'localhost' }, watch: { usePolling: true } }
        : undefined,

    resolve: {
        // The panel is Vue, and it arrives as packages that each depend on Vue themselves. A
        // second copy of it in the bundle does not fail: `createApp` comes from one and `ref()`
        // from the other, the two reactivity systems never see each other, and the panel simply
        // stops redrawing. This one line is what keeps there being only one.
        dedupe: ['vue', 'vue-router'],
    },
})
