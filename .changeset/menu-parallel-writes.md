---
'@webx-ui/php': patch
---

nested-set, module-menu: parallel writes to one tree no longer end in a 500 and a broken tree. Every placement holds its tree (`lockTree()`) before it reads a bound, reads bounds with a lock rather than from the transaction's snapshot, and is run again after a deadlock. In the menu, `menu_add_link`, `menu_update_link`, `menu_move_link`, `menu_remove_link` and the panel's add, move and delete go through `Menus::writing()`: the menu's row is held for the whole write, the insert and the move to the asked position are one transaction, and a write that keeps losing the race answers "the menu is being changed by another request — send it again" instead of an internal server error.
