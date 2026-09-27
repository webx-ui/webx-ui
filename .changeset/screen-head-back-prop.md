---
'@webx-ui/module-admin': patch
---

`WxScreenHead` declares `back` as a string, a route object or a boolean at runtime. The router's type could not be resolved by the compiler, so the prop was checked as a boolean alone, and every editor passing its list's path warned "Expected Boolean, got String".
