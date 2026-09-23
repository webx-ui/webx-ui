{{--
    The page the block editor draws a block on: the site's layout with an empty place in it.

    Nothing of the block is printed here. The panel loads this once and then swaps the block
    between the markers and its styles into the `<style>` on every keystroke — reloading the
    whole page each time would redraw the header and the footer under the editor's typing.
    The runtime is here so that a block's script, added by the panel, has `webx` to call.
--}}
<x-dynamic-component :component="$layout">
    <x-slot:head>
        <script src="{{ $runtime }}"></script>
        <style id="{{ $styles }}"></style>
    </x-slot:head>

    <!--wx:{{ $key }}--><!--/wx:{{ $key }}-->
</x-dynamic-component>
