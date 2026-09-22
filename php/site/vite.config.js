import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
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
