# webx-ui/nested-set

A nested set for Eloquent: every node stores the bounds of its subtree, so a branch, a
breadcrumb trail or a whole tree is one indexed query. It is a library with no section, no
config and no tables of its own — the pages of `webx-ui/module-pages`, the catalogue's
categories, the menu items and the media folders are built on it. An address that follows the tree is
`webx-ui/routing`'s `TreePath`; read that guide for addresses.

## What it owns

- **Columns** from the Blueprint macro `$table->nestedSet()` (or `NestedSet::columns($table)`):
  `parent_id`, `lft`, `rgt`, `depth`, indexed. `lft`/`rgt` are signed on purpose — a move parks
  the subtree in negative bounds. `$table->dropNestedSet()` takes them out.
- **Trait** `HasNestedSet`:
  - placing and moving, each in a transaction, subtree and all: `appendTo()`, `prependTo()`,
    `insertBefore()`, `insertAfter()`, `up()`, `down()`, `saveAsRoot()`; a move fires the model
    event `moved` (routing listens to it and rewrites the branch's addresses);
  - detached nodes (bounds 0/0, in no tree): `saveDetached()`, `isDetached()`, scopes
    `detached()` / `placed()`;
  - reading: `parent`, `children`, `ancestors()`, `descendants()`, `siblings()`,
    `pathFromRoot()`, `isRoot()`, `isLeaf()`, `isChildOf()`, `isDescendantOf()`, scopes
    `roots()` / `ordered()`, `toTree()` for the shape `<wx-tree>` expects;
  - maintenance: `checkTreeIntegrity()`, `fixTree()` (both take a scope array).
- **`NestedSetException`**: moving into its own subtree, a missing / unsaved / detached target,
  placing across scopes, a soft delete the model did not opt into.

## Change it without forking

| You want                                    | Do this                                                                           |
| ------------------------------------------- | --------------------------------------------------------------------------------- |
| A tree in a table of your own               | `$table->nestedSet()` in the migration, `use HasNestedSet` on the model           |
| Other column names                          | override `getLftName()`, `getRgtName()`, `getParentIdName()`, `getDepthName()`    |
| Several trees in one table (per site, menu) | `getNestedSetScopeAttributes()` returning e.g. `['site_id']`                      |
| A record that exists before it has a place  | `saveDetached()`, later `appendTo($parent)`                                       |
| Soft deletes on a tree                      | `softDeletesInTree(): bool { return true; }` — and trash the descendants yourself |
| The whole tree for the front end            | `Model::toTree(Model::query()->ordered()->get())`                                 |

## Do not

- Do not edit anything in `vendor/webx-ui/nested-set`. Every row above is the supported way; if
  none fits, the package is missing a seam — say so instead of working around it.
- Do not move a node by setting `parent_id` and calling `save()`: the bounds of the subtree and
  of everything to its right stay where they were and the tree breaks. Use `appendTo()`,
  `insertAfter()` and the rest, which also fire `moved`.
- Do not write `lft`, `rgt` or `depth` by hand or with SQL. If a tree is already broken,
  `fixTree()` rebuilds them from `parent_id`, keeping the order.
- Do not add a position column of your own to reorder siblings: order is `lft`. Use `up()`, `down()`,
  `insertBefore()` / `insertAfter()`.
- Do not rely on model events of the descendants when deleting a node: the subtree goes in one
  query. Delete children one by one first if something listens to them.
- Do not add `SoftDeletes` to a tree model alone: a soft delete is refused unless the model says
  `softDeletesInTree()`, because reclaimed bounds would strand the children.
- Do not place a node next to one from another scope: it throws rather than merging two trees.

## Check your work

- `Model::checkTreeIntegrity()` returns `[]` for a healthy tree, otherwise one line per problem
  (`Page::checkTreeIntegrity(['site_id' => 1])` for a scoped one).
- After a move, `pathFromRoot()` of the moved node shows the new ancestors; with routing, its
  old address answers 301.
- A tree that fails the check: `Model::fixTree()` returns how many rows it put right.

## Read more

- [README.md](README.md) in this directory — the whole API with examples.
- The tree in use: https://webx-ui.github.io/webx-ui/guide/pages
- Addresses that follow the tree: https://webx-ui.github.io/webx-ui/guide/routing
