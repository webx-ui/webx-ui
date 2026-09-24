# @webx-ui/module-reviews

## 0.1.0

### Minor Changes

- 8e61847: The first release of `@webx-ui/module-reviews`, the panel half of `webx-ui/module-reviews`.
  `reviews()` adds two sections under the Reviews group. Reviews shows the list and the form of one
  review side by side (`WxListDetail`); a row has the photo or the initials, the name, the stars,
  the languages it is seen in, and says when a published review is seen in none. You drag reviews
  into order: the whole list, or inside the one category the list is narrowed to. A new review is the
  first line of the list and becomes a record on its first save. The form is the described screen
  `reviews.form`. It saves with Ctrl+S and asks before unsaved words are dropped. On a phone the form
  fills the screen and has its own way back. Categories are the panel's shared category screens,
  without addresses (`reviewCategoriesOptions()`).
