{{--
    `<x-layout>`: the document every public page is printed in — the composition `layout`
    (COMPOSITIONS §6). Its tree is theme-default's compositions/layout/default.json; modules add
    their pieces to it and themes patch it by id, compiled into one Blade view a handle. A page
    names its handle — `<x-layout handle="blog.article">` — and gets that variant, or `default`.

    A layer above that has its own components/layout.blade.php is above this file in the chain
    and prints instead, as before; `<x-webx-composition::place>` brings the modules' pieces into it.

    The include touches the comment: a line break here would be one more in every page.
--}}@include(composition_view('layout', $attributes->get('handle')))
