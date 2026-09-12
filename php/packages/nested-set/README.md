# webx-ui/nested-set

Nested set (modified preorder tree traversal) for Eloquent models.

Every node stores the bounds of its own subtree, so reading a whole branch — or a breadcrumb
trail — is a single indexed query instead of one query per level. That is what makes a category
tree, a page tree or a folder tree usable in an admin panel where the whole tree is on screen.

Part of [WebX UI](https://github.com/webx-ui/webx-ui). Used on its own it needs nothing but
Eloquent.

## Requirements

- PHP 8.3+
- Laravel 13 (`illuminate/database`, `illuminate/support`)

## Install

```bash
composer require webx-ui/nested-set
```

## Schema

```php
use Illuminate\Database\Schema\Blueprint;

Schema::create('categories', function (Blueprint $table): void {
    $table->id();
    $table->string('name');
    $table->nestedSet(); // parent_id, lft, rgt, depth + indexes
    $table->timestamps();
});
```

Without the Laravel service provider, call the helper directly:

```php
use WebxUi\NestedSet\NestedSet;

NestedSet::columns($table);
```

`lft` and `rgt` are **signed** integers on purpose: moving a subtree parks it in negative bounds
before putting it back, and an unsigned column would reject that halfway through the move.

## Model

```php
use Illuminate\Database\Eloquent\Model;
use WebxUi\NestedSet\HasNestedSet;

class Category extends Model
{
    use HasNestedSet;
}
```

Different column names? Override `getLftName()`, `getRgtName()`, `getParentIdName()` or
`getDepthName()`.

## Placing nodes

A saved model with no placement becomes the last root. Everything else is explicit:

```php
$root = Category::create(['name' => 'Catalogue']);

$phones = new Category(['name' => 'Phones']);
$phones->appendTo($root);   // last child

$sale = new Category(['name' => 'Sale']);
$sale->prependTo($root);    // first child

$tablets = new Category(['name' => 'Tablets']);
$tablets->insertAfter($phones);

$phones->up();              // swap with the previous sibling
$phones->down();            // swap with the next one
$phones->saveAsRoot();      // lift a whole branch out to the top level
```

The same calls move a node that already exists, subtree and all. Each one runs in a transaction;
moving a node into its own subtree throws `NestedSetException`.

## Reading

```php
$node->parent;                  // relation
$node->children;                // relation, ordered
$node->ancestors()->get();      // root first
$node->descendants()->get();    // the whole branch below, ordered
$node->siblings()->get();
$node->pathFromRoot();          // ancestors + the node itself, for breadcrumbs

$node->isRoot();
$node->isLeaf();
$node->isChildOf($other);
$node->isDescendantOf($other);

Category::query()->roots()->ordered()->get();
```

To hand a whole tree to the front end in one query:

```php
$tree = Category::toTree(Category::query()->ordered()->get());
```

Roots come back with their `children` relation filled in, recursively — which is the shape
`<wx-tree>` and the WebX UI table tree expect.

## Several trees in one table

```php
class Page extends Model
{
    use HasNestedSet;

    public function getNestedSetScopeAttributes(): array
    {
        return ['site_id'];
    }
}
```

Bounds are then counted per site, and placing a node from one site next to a node from another
throws instead of silently merging the trees.

## Maintenance

```php
Category::checkTreeIntegrity();  // [] when healthy, otherwise one line per problem
Category::fixTree();             // rebuild lft/rgt/depth from parent_id, returns rows fixed
```

`fixTree()` keeps the current order and rebuilds orphans as roots. Both take a scope array
(`Page::fixTree(['site_id' => 1])`) for scoped models.

## Deleting

Deleting a node deletes its subtree and closes the gap it leaves. The descendants are removed
with one query, so their model events do **not** fire — delete them one by one first if you rely
on those.

Soft deletes are not supported: a soft-deleted node would stay in the tree while its bounds were
reclaimed. A model using `SoftDeletes` throws unless the delete is a `forceDelete()`.

## Licence

MIT.
