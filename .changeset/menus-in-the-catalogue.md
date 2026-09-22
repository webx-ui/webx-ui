---
'@webx-ui/php': minor
---

A new site can be built with menus, and the skeleton's header hands over to one

`webx:setup` offers the modules `Setup\Catalogue` knows by name, and a name it does not know is
a refusal rather than a `composer require` of whatever turns up — so a section missing from that
list is one a new site cannot install at all. `webx-ui/module-menu` joins it, and joins it among
the defaults: the header of a site is not an optional part of it.

The skeleton's own header was the example that module was written to end. It asks `menu('header')`
first now and keeps the page tree underneath it, for the day between creating a site and filling
its menu in: a header that is empty on the day a site is created reads as broken rather than as
waiting.
