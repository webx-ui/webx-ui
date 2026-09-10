import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import dts from 'vite-plugin-dts'

export default defineConfig({
  plugins: [
    vue(),
    dts({
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
      // Emitted next to the JS as dist/style.css — consumers import it explicitly.
      cssFileName: 'style',
    },
    rollupOptions: {
      external: ['vue'],
    },
  },
})
