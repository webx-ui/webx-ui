---
'@webx-ui/php': patch
---

`webx-ui/module-blocks`: what a block keeps is what its field type keeps.

A described screen has always written through its types — `ScreenValues::validate()` looks a node's
type up, checks the value against `rules()` and casts it with `store()` — and a block's values are
screen nodes too, but nothing on the way in ever asked them. `Rendering\Values` had done the mirror
of it on the read side since the module shipped; `ContentValues` is the missing half, and both write
paths now go through it: the editor's save (`PageForm`, by way of `HasBlocks::storeBlocks()`) and the
agent's `blocks_set_content` / `blocks_edit_content`. A type that lowercases a colour, casts a number
out of the string a form sent, or runs pasted markup through an allowlist does that work in a block
from now on, and not on screens only.

What passed through untouched still does, and for the same reasons: a value whose key the block's
schema does not name, a value of a type nobody registered, and a nested tree of blocks, which is
walked as blocks rather than handed to a field type. The node itself is merged rather than rebuilt,
so `key`, `hidden` and whatever structural key comes next survive a save. The schema walk both
directions share is now one class, `Blocks\Schema`.

`ScreenValues::validate()` loses its `?? $value` fallback in the same pass: `null` is an answer a
type is allowed to give — an emptied colour, a date cleared — and the fallback put back the very
value the type had just refused.
