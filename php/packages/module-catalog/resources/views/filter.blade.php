{{--
    The filter as links, not a form (§10.2): every value has its address ready, and one a search
    engine should not follow says so with `rel="nofollow"`. It works without a script. A value
    that would leave nothing is grey and has no link.

    A partial per kind of facet — `filter.terms`, `filter.range`, `filter.toggle`, `filter.tree` —
    so a site that wants brands as logos overrides one of them and keeps the rest.
--}}
@if ($page->groups !== [])
    <aside class="webx-catalog-filter" aria-label="{{ __('webx-catalog::storefront.filter') }}">
        @foreach ($page->groups as $group)
            <fieldset class="webx-catalog-filter__group webx-catalog-filter__group--{{ $group->kind()->value }}">
                <legend>{{ $group->facet->label() }}</legend>
                @include($group->view(), ['group' => $group, 'page' => $page])
            </fieldset>
        @endforeach

        @if ($page->resetUrl !== null)
            <p><a href="{{ $page->resetUrl }}" rel="nofollow">{{ __('webx-catalog::storefront.reset') }}</a></p>
        @endif
    </aside>
@endif
