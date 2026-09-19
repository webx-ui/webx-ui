{{--
    Three rules, and no more than that.

    These five views are the least markup that works, and a site is expected to replace them —
    but "unstyled" and "broken" are not the same thing. Without `max-width` a cover of 1200px
    pushes a phone's page out to 1248px wide and every line of text goes off the screen with it,
    which is what the first person to open the blog on a phone sees.
--}}
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: #1f2328; background: #fff; }
    img { max-width: 100%; height: auto; display: block; }
</style>
