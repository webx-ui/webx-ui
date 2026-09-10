import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitepress'

// GitHub Pages serves this repo from a subpath; the domain root would need a repo named
// literally `webx-ui.github.io`.
export default defineConfig({
  title: 'WebX UI',
  description: 'Vue 3 design system for Laravel admin panels',
  lang: 'en-US',
  base: '/webx-ui/',
  cleanUrls: true,
  lastUpdated: true,
  head: [['meta', { name: 'theme-color', content: '#2563eb' }]],
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/', activeMatch: '/guide/' },
      { text: 'Tokens', link: '/tokens/', activeMatch: '/tokens/' },
      { text: 'Components', link: '/components/button', activeMatch: '/components/' },
      { text: 'npm', link: 'https://www.npmjs.com/org/webx-ui' },
    ],
    sidebar: {
      '/guide/': [
        {
          text: 'Guide',
          items: [
            { text: 'Introduction', link: '/guide/' },
            { text: 'Installation', link: '/guide/installation' },
            { text: 'Theming', link: '/guide/theming' },
            { text: 'Roadmap', link: '/guide/roadmap' },
          ],
        },
      ],
      '/tokens/': [
        {
          text: 'Tokens',
          items: [{ text: 'Overview', link: '/tokens/' }],
        },
      ],
      '/components/': [
        {
          text: 'Basic',
          items: [
            { text: 'Button', link: '/components/button' },
            { text: 'Card', link: '/components/card' },
          ],
        },
        {
          text: 'Form',
          items: [
            { text: 'Form', link: '/components/form' },
            { text: 'Input', link: '/components/input' },
            { text: 'Textarea', link: '/components/textarea' },
            { text: 'InputNumber', link: '/components/input-number' },
            { text: 'Checkbox', link: '/components/checkbox' },
            { text: 'Radio', link: '/components/radio' },
            { text: 'Switch', link: '/components/switch' },
          ],
        },
        {
          text: 'Planned',
          items: [{ text: 'Component roadmap', link: '/guide/roadmap' }],
        },
      ],
    },
    socialLinks: [{ icon: 'github', link: 'https://github.com/webx-ui/webx-ui' }],
    search: { provider: 'local' },
    editLink: {
      pattern: 'https://github.com/webx-ui/webx-ui/edit/main/apps/docs/:path',
      text: 'Edit this page on GitHub',
    },
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2026 WebX UI',
    },
  },
  vite: {
    resolve: {
      alias: [
        {
          find: /^@webx-ui\/core$/,
          replacement: fileURLToPath(
            new URL('../../../packages/core/src/index.ts', import.meta.url),
          ),
        },
        {
          find: /^@webx-ui\/tokens$/,
          replacement: fileURLToPath(
            new URL('../../../packages/tokens/src/index.ts', import.meta.url),
          ),
        },
      ],
    },
  },
})
