import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],

    resolve: {
        // The panel is Vue, and it arrives as packages that each depend on Vue themselves. A
        // second copy of it in the bundle does not fail: `createApp` comes from one and `ref()`
        // from the other, the two reactivity systems never see each other, and the panel simply
        // stops redrawing. This one line is what keeps there being only one.
        dedupe: ['vue', 'vue-router'],
    },
})
