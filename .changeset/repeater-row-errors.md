---
'@webx-ui/php': patch
---

A refused row of a repeater is named by its path — `contacts.phones.1.number` — instead of "Row 2: …" under the whole list. The panel marks that row, opens it and shows the words under the field; an agent reads the row from the key. Field types made of items implement `ChecksItems`; the Contacts tab's own checks answer the same way.
