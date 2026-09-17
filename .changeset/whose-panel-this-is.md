---
'@webx-ui/module-admin': minor
'@webx-ui/php': minor
---

The panel wears the client's logo

The corner used to hold `WEBX_ADMIN_TITLE`, a name from a deploy file, which made every
installation look like the same borrowed tool. Settings gets a **Branding** tab with two
pictures, and the frame wears them: `branding.logo` in the corner of the open sidebar at 28 px
tall, `branding.mark` on the 56 px rail, above the button that opens the sidebar again.

Two pictures rather than one and a cropping rule — a wordmark cut to a square is its first two
letters, and only the client knows what their mark is. A mark left empty leaves the rail
exactly as it was.

The name does not leave. `general.project-name`, the localized field that has sat on the
`General` tab since the section was written without anybody reading it, now becomes
`manifest.title`: the text in the corner when there is no logo, the logo's `alt` when there
is, and the deployed title again when it is cleared.

On the server this is one binding — `WebxUi\Admin\Contracts\BrandingSource`, answered by
`module-settings`. `module-admin` neither knows nor requires the section that holds a logo, and
a panel with no source bound is the panel as it always was. The picture fields are `wx-media`,
so `module-media` is what turns them into addresses; without it the values stay library paths
the frame cannot read and the corner keeps its name, the same tolerance `module-seo` has for
its `og:image`.
