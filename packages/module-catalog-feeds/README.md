# @webx-ui/module-catalog-feeds

Product feeds for the catalogue of the [WebX UI](https://github.com/webx-ui/webx-ui) admin panel.

The other half is the Composer package `webx-ui/module-catalog-feeds`, which owns the feeds and
their API. The section appears in the panel when both halves are installed, beside
`@webx-ui/module-catalog`. Today it shows the list of feeds; the form comes in a later version.

## Install

```bash
npm install @webx-ui/module-catalog-feeds
```

```ts
import { createAdmin } from '@webx-ui/module-admin'
import { catalog } from '@webx-ui/module-catalog'
import { catalogFeeds } from '@webx-ui/module-catalog-feeds'
import '@webx-ui/module-catalog/style.css'
import '@webx-ui/module-catalog-feeds/style.css'

createAdmin({
  basePath: '/cms',
  modules: [...catalog(), ...catalogFeeds()],
}).mount()
```

## License

MIT
