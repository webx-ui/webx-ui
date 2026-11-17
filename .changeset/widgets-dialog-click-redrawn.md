---
'@webx-ui/php': patch
---

widgets: a click inside a dialog scrolled as a page (`dialog.scroll: page`) no longer closes it when what was clicked is redrawn on the click. Picking a day in the calendar of a «Date and time» field, a day that is not available, or turning the month closed the form's dialog: the calendar draws its days anew before the click reaches the dialog, and the day pressed was in no box any more. Inside or outside is now decided by the path the event took and at the press, not by the node afterwards; the ×, Esc and a click on the backdrop close it as before.
