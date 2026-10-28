{{-- The lines of the cart, or the children of a bundle under their parent (one level, decision 25). --}}
<ul @class(['webx-cart__lines', 'webx-cart__lines--children' => $children])>
    @foreach ($lines as $line)
        @include('webx-cart::widget.line', ['line' => $line, 'child' => $children])
    @endforeach
</ul>
