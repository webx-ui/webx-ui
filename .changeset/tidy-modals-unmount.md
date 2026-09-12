---
'@webx-ui/core': patch
---

A modal that closes now takes its node away exactly once. An unmount hook that throws came
back through the host's `onErrorCaptured`, which unmounted again over a half-gone tree —
turning a single error into a stack overflow.
