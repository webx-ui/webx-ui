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
            { text: 'Dialogs from code', link: '/guide/modals' },
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
            { text: 'ButtonGroup', link: '/components/button-group' },
            { text: 'Icon', link: '/components/icon' },
            { text: 'Typography', link: '/components/typography' },
            { text: 'Badge', link: '/components/badge' },
            { text: 'Indicator', link: '/components/indicator' },
            { text: 'Alert', link: '/components/alert' },
            { text: 'Card', link: '/components/card' },
            { text: 'Tabs', link: '/components/tabs' },
            { text: 'Accordion', link: '/components/accordion' },
            { text: 'Actions', link: '/components/actions' },
            { text: 'Dropdown', link: '/components/dropdown' },
            { text: 'Popover', link: '/components/popover' },
            { text: 'Tooltip', link: '/components/tooltip' },
            { text: 'Dialog', link: '/components/dialog' },
            { text: 'Drawer', link: '/components/drawer' },
            { text: 'EntityCard', link: '/components/entity-card' },
            { text: 'FileCard', link: '/components/file-card' },
            { text: 'Avatar', link: '/components/avatar' },
            { text: 'Image', link: '/components/image' },
            { text: 'Segmented', link: '/components/segmented' },
          ],
        },
        {
          text: 'Feedback',
          items: [
            { text: 'Toast', link: '/components/toast' },
            { text: 'Popconfirm', link: '/components/popconfirm' },
            { text: 'Loading', link: '/components/loading' },
            { text: 'Skeleton', link: '/components/skeleton' },
            { text: 'Progress', link: '/components/progress' },
            { text: 'Empty', link: '/components/empty' },
            { text: 'Result', link: '/components/result' },
          ],
        },
        {
          text: 'Layout',
          items: [
            { text: 'Layout', link: '/components/layout' },
            { text: 'Grid', link: '/components/grid' },
            { text: 'ListDetail', link: '/components/list-detail' },
            { text: 'Space', link: '/components/space' },
            { text: 'Divider', link: '/components/divider' },
            { text: 'Scrollbar', link: '/components/scrollbar' },
            { text: 'Affix', link: '/components/affix' },
          ],
        },
        {
          text: 'Navigation',
          items: [
            { text: 'Menu', link: '/components/menu' },
            { text: 'Breadcrumb', link: '/components/breadcrumb' },
          ],
        },
        {
          text: 'Form',
          items: [
            { text: 'Form', link: '/components/form' },
            { text: 'Input', link: '/components/input' },
            { text: 'Textarea', link: '/components/textarea' },
            { text: 'InputNumber', link: '/components/input-number' },
            { text: 'Select', link: '/components/select' },
            { text: 'Autocomplete', link: '/components/autocomplete' },
            { text: 'Cascader', link: '/components/cascader' },
            { text: 'TreeSelect', link: '/components/tree-select' },
            { text: 'Checkbox', link: '/components/checkbox' },
            { text: 'Radio', link: '/components/radio' },
            { text: 'Switch', link: '/components/switch' },
            { text: 'DatePicker', link: '/components/date-picker' },
            { text: 'DateRangePicker', link: '/components/date-range-picker' },
            { text: 'ColorPicker', link: '/components/color-picker' },
            { text: 'TagsInput', link: '/components/tags-input' },
            { text: 'Slider', link: '/components/slider' },
            { text: 'Transfer', link: '/components/transfer' },
            { text: 'Rate', link: '/components/rate' },
            { text: 'RichText', link: '/components/rich-text' },
            { text: 'Upload', link: '/components/upload' },
          ],
        },
        {
          text: 'Data',
          items: [
            { text: 'Table', link: '/components/table' },
            { text: 'Tree', link: '/components/tree' },
            { text: 'Pagination', link: '/components/pagination' },
            { text: 'Statistic', link: '/components/statistic' },
            { text: 'Descriptions', link: '/components/descriptions' },
            { text: 'Timeline', link: '/components/timeline' },
            { text: 'Steps', link: '/components/steps' },
            { text: 'Kanban', link: '/components/kanban' },
            { text: 'SelectionArea', link: '/components/selection-area' },
            { text: 'SortableList', link: '/components/sortable-list' },
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
