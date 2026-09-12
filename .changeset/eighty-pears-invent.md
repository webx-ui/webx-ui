---
'@webx-ui/php': minor
---

`webx:panel` wires the panel's front end into the application that hosts it: it writes the
entry file, adds it to the Vite inputs, points `webx-admin.vite` at it, and names the npm
packages to install. Where it cannot recognise a Vite configuration it says which line to add
rather than rewriting a build it does not understand.
