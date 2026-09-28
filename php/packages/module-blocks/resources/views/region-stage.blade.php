{{--
    A region's draft on an address the registry does not know: the site's layout with nothing in
    it but a notice. The layout prints the region itself; `$place` is where it is drawn instead
    when the layout does not.
--}}
<x-dynamic-component :component="$layout">
    <div class="wx-region-stage-notice" data-wx-region-stage style="margin:16px;padding:12px 16px;border:1px dashed #b0b0b0;border-radius:6px;color:#555;font:14px/1.5 system-ui,sans-serif">{{ $notice }}</div>
    {!! $place !!}
</x-dynamic-component>
