@php
    /**
     * The footer region's fallback, and the dispatcher of the composition `footer`
     * (COMPOSITIONS §10.3): its tree is compositions/footer/default.json — the contacts, the
     * navigation and the copyright line are columns a theme patches by id.
     *
     * The navigation is `menu('footer')` when the menus module is there and the menu has entries;
     * otherwise just the line every footer has. "Cookie settings" is there either way: taking a
     * consent back must be as easy as giving it (WIDGETS §9.1). The contacts are the Contacts tab
     * of the settings (WIDGETS §12): every number, the e-mails, the main address and the
     * networks — nothing when the tab is empty or the module is not there.
     */
    $contacts = function_exists('contacts') ? contacts() : null;
    $address = $contacts?->primaryAddress();
    $items = function_exists('menu')
        ? menu('footer')->map(fn ($item) => ['label' => $item->label, 'attrs' => $item->attrs()])
        : collect();
@endphp
@include(composition_view('footer'))
