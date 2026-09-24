# Categories

The categories of a module — the rubrics of the blog, the sections of a list of services — are
shared code and separate data. Every module keeps its own table (`rubrics`, `service_categories`)
and its own link table, and borrows everything else from `webx-ui/module-admin` and
`@webx-ui/module-admin`: the model traits, the API, the list, the page of one category and the
field that files a record under them.

This page is for somebody writing a module. What an editor sees is described in the module's own
guide — [the blog's](/guide/blog#the-panel) is the first one built on it.

## What you get

- **The list**: every category on one screen, dragged into the order the site has them, with the
  address, the number of records inside, a mark on the hidden ones and a `···` per row. Deleting
  is refused while a category holds anything, and the row says how many.
- **The page of one**: a screen of the module's (`{module}.category-form`), one `Save` for all its
  tabs, and a refused field opens the tab it is on. It is a page and not a dialog because a
  category is a record: an address, a picture, SEO and the fields a project adds.
- **`wx-categories`**: the field on the record's form. A list of the chosen ones, dragged into
  order, with the first marked as the main one, and a box to add from the module's categories.
- **`wx-category-slug`**: the address field of the category page, with the module's prefix
  printed in front of what is typed.

## The server half

Two macros lay down the tables, and two traits read them:

```php
Schema::create('service_categories', function (Blueprint $table) {
    $table->id();
    $table->category();                     // title, slug, position, is_visible, extra, soft deletes
    $table->string('cover_id')->nullable(); // anything else is the module's own
});

Schema::create('service_category', function (Blueprint $table) {
    $table->categoryLinks('service', 'service_categories'); // position, item_position
});
```

The category model implements `Category` with `IsCategory` and says what it is once, in
`categoryKind()` — the screen, who reads and who writes, where its addresses start and what the
module calls things:

```php
final class ServiceCategory extends Model implements Category
{
    use HasExtra, HasTranslations, IsCategory, SoftDeletes;

    public static function categoryKind(): CategoryKind
    {
        return new CategoryKind(
            model: self::class,
            screen: 'services.category-form',
            view: ['services.view'],
            manage: 'services.manage',
            prefix: fn () => config('webx-services.prefix', 'services'),
            noun: 'category',
            plural: 'categories',
            items: 'services',   // the count travels as `services_count`
        );
    }

    public function items(): BelongsToMany { return $this->services(); }
}
```

The record uses `HasCategories` and names its relation (`categoryRelation()`); the first category
in the list is its main one.

`prefix: null` is a kind with no addresses at all — the [FAQ's](/guide/faq), whose categories
only pick and filter questions. The list then has no address line under each row, and the agent's
tools (`CategoryTools`) neither take a slug nor answer with one: a category is named by its id or
its title.

The API is one line in the module's route group, and the field is one line in its provider:

```php
// routes: GET/POST services/categories, GET/PUT/DELETE services/categories/{id}, reorder, restore
CategoryRoutes::register(ServiceCategory::class, 'categories');

// boot(): which table `"source": "services/categories"` means on a screen
$this->app->make(CategorySources::class)
    ->register('services/categories', ServiceCategory::class, 'webx-services::errors.unknown-category');
```

`CategorySources` is filled from the provider rather than from the route file on purpose: a
cached route table never runs the route file.

## The screen

The page of one category draws the module's screen, and the module registers it like any other
([Screens](/guide/screens)). Two things are worth copying from `blog.category-form`:

- the address field is `wx-category-slug`, so the prefix stands in front of it;
- a card with the public id `project-fields` and no children. It is where a site's patch puts
  its own fields, and while nothing is patched in the renderer does not draw it at all.

Whatever the screen draws and the model has no column for is kept in `extra` —
`$category->extra('menu-badge')` on the site.

The record's form files it under categories with `wx-categories`. `source` is the path the
categories answer at, under the panel's API; the words are props, so they can be the module's:

```json
{
  "id": "rubrics",
  "type": "wx-categories",
  "name": "rubrics",
  "label": "trans::webx-blog::screen.rubrics",
  "props": {
    "source": "blog/rubrics",
    "addText": "trans::webx-blog::article.rubric-add",
    "mainText": "trans::webx-blog::article.rubric-main"
  }
}
```

`main: false` drops the badge, for a module whose categories have no main one. The other words
are `removeText`, `emptyText` and `noneLeftText`.

## The panel half

`categoryRoutes()` mounts the list and the page from one description:

```ts
import { categoryRoutes, type AdminModule } from '@webx-ui/module-admin'

const categories: AdminModule = {
  id: 'service-categories',
  path: '/services/categories',
  routes: categoryRoutes({
    api: 'services/categories',
    path: '/services/categories',
    name: 'webx.services.categories',
    module: 'service-categories', // its title in the manifest is the heading
    screen: 'services.category-form',
    manage: 'services.manage',
    count: 'services_count',
    items: (id) => ({ path: '/services', query: { category: String(id) } }),
    words: {
      count: 'webx-services::category.count',
      'show-items': 'webx-services::category.show-services',
    },
  }),
}
```

Every word has a default under `webx-admin::categories.*` that says "category" and "entries". A
module overrides the ones that name its things — the blog passes its own for every line that says
"rubric" and "articles" — and keeps the panel's "Edit", "Cancel" and "Leave without saving?".

## The order of the records

A record has two orders: its place in the whole list (`position`) and its place inside each
category it is in (`item_position` on the link). An editor drags in the list they are looking at:
with no filter the drag moves the whole order, with one category chosen and nothing else it moves
that category's, and with a search or any other filter on there is nothing to drag — the rows on
screen are a selection, and the records between them are out of sight. A record added to a
category takes its place there by the whole order.

`useItemOrder()` is that rule and the sentence that explains it, for whatever list the module
draws:

```ts
import { useItemOrder } from '@webx-ui/module-admin'

const order = useItemOrder('services', () => ({
  q: query.value.q,
  category: query.value.category,
  filtered: query.value.status !== undefined,
}))

// order.sortable — draw the handles or not
// order.hint     — the line under the list
// order.move(ids) — POST services/reorder { ids, category? }
```

The server half is `CategoryRoutes::items(Service::class, 'services', 'services.manage')`. The
blog orders its articles by date and uses neither.
