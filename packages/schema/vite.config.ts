import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import dts from 'vite-plugin-dts'

export default defineConfig({
  plugins: [
    vue(),
    // Declarations are most of the build and only typecheck reads them; CI's test and docs
    // jobs set WEBX_SKIP_DTS.
    process.env.WEBX_SKIP_DTS
      ? null
      : dts({
          tsconfigPath: './tsconfig.build.json',
          cleanVueFileName: true,
        }),
  ],
  build: {
    target: 'es2022',
    cssCodeSplit: false,
    sourcemap: true,
    lib: {
      entry: fileURLToPath(new URL('./src/index.ts', import.meta.url)),
      formats: ['es'],
      fileName: () => 'index.js',
      cssFileName: 'style',
    },
    rollupOptions: {
      external: ['vue', '@webx-ui/core'],
    },
  },
})
