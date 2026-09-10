# @webx-ui/schema

JSON-driven rendering for WebX UI admin screens. **Work in progress** — at this stage the package
only fixes the contracts (`SchemaNode`, `DataAdapter`, `Paginated`, registries); the renderer
component lands in a later milestone.

```ts
import type { DataAdapter, SchemaNode } from '@webx-ui/schema'

const page: SchemaNode = {
  type: 'card',
  props: { title: 'Pages' },
  children: [{ type: 'table', props: { resource: 'pages' } }],
}
```

The package is backend-agnostic: Laravel specifics (`LengthAwarePaginator`, 422 validation errors,
sort/filter query parameters) belong in `@webx-ui/adapter-laravel`.
