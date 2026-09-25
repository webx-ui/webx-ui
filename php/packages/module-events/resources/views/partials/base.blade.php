{{--
    Three rules, and no more than that: the package's views are the least markup that works, and a
    site replaces them — but without `max-width` a photo of 1200px pushes a phone's page out to
    1248px, and that is what the first person to open an event on a phone would see.
--}}
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font: 16px/1.6 system-ui, sans-serif; color: #1f2328; background: #fff; }
    img { max-width: 100%; height: auto; display: block; }
</style>
