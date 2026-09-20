---
'@webx-ui/core': patch
---

A badge's cross is easier to hit than it is to see

`WxBadge`'s close button is as big as the words it stands beside — twelve pixels, which is
right for what is drawn and small for what is pressed. It now reaches four pixels further on
every side, so the target is twenty while the cross stays twelve. Four and not more, because
badges sit six apart and a longer reach would take its neighbour's.
