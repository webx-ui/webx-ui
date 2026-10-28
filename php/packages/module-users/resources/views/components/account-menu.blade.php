{{--
    The menu of the account (spec §11.2, §11.5): the sections in position order — the module's own
    and the ones modules registered —, the page's own lit, the badges, and «Sign out» last. The
    composition `layout.account` puts it in its `account.menu` (node `users.account-menu`); a page
    in another layout gets it from <x-webx-users::account>.

    A badge is asked for here and nowhere else: one query of the module that owns it.

    Override: <layer>/views/vendor/webx-users/components/account-menu.blade.php.
    Classes: webx-account__menu, __entry, __item (--<id>, --sign-out), __label, __badge; is-active, is-locked (a section
    for a confirmed e-mail, the visitor's is not).
--}}
@php
    $visitor = app(\WebxUi\Admin\Visitors\Visitors::class)->current();
    $menu = app(\WebxUi\Users\Account\AccountMenu::class);
    $urls = app(\WebxUi\Users\Urls::class);
@endphp
@if ($visitor !== null)
    <ul class="webx-account__menu">
        @foreach ($menu->for($visitor) as $section)
            @php
                $active = $menu->active($section, request());
                $badge = $section->badge === null ? null : ($section->badge)($visitor);
            @endphp
            <li class="webx-account__entry">
                <a href="{{ $urls->named($section->route) }}" @class(['webx-account__item', 'webx-account__item--'.$section->id, 'is-active' => $active, 'is-locked' => $section->verified && ! $visitor->verified]) data-icon="{{ $section->icon }}" @if ($active) aria-current="page" @endif>
                    <span class="webx-account__label">{{ __($section->label) }}</span>
                    @if ($badge !== null && $badge !== '')
                        <span class="webx-account__badge">{{ $badge }}</span>
                    @endif
                </a>
            </li>
        @endforeach
        <li class="webx-account__entry">
            <form method="post" action="{{ $urls->to('sign-out') }}" class="webx-account__sign-out">
                @csrf
                <button type="submit" class="webx-account__item webx-account__item--sign-out">
                    <span class="webx-account__label">{{ __('webx-users::account.menu.sign_out') }}</span>
                </button>
            </form>
        </li>
    </ul>
@endif
