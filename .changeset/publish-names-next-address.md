---
'@webx-ui/php': patch
'@webx-ui/module-admin': patch
'@webx-ui/module-pages': patch
'@webx-ui/module-blog': patch
'@webx-ui/module-events': patch
'@webx-ui/module-services': patch
'@webx-ui/module-recipes': patch
'@webx-ui/module-vacancies': patch
---

The publish question names the address the record will have after publishing. A slug renamed in
the draft moved the address only on publishing, while the question still named the old one; rows
now carry `next_path` (`PanelAddress::afterPublishing()`), and the question adds that the old
address will lead to the new one.
