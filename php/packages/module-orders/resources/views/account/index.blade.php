{{--
    «My orders» (spec §13.2): the visitor's orders, newest first — the number, the date, the public
    status, the amount and how many lines; an order opens as the signed page shows it. Orders made
    as a guest are not here until a manager links them.

    $rows — `WebxUi\Orders\Site\OrderPage::rows()` with `url`; $pages — the paginator.

    Override: <layer>/views/vendor/webx-orders/account/index.blade.php.
    Classes: webx-account-orders, __list, __row (is-cancelled), __number, __date, __status (--<code>),
    __lines, __amount, __empty, __pages.
--}}
<x-webx-users::account :title="__('webx-orders::site.account.title')">
    @if ($rows === [])
        <p class="webx-account-orders__empty">{{ __('webx-orders::site.account.empty') }}</p>
    @else
        <ul class="webx-account-orders__list">
            @foreach ($rows as $row)
                <li>
                    <a href="{{ $row['url'] }}" @class(['webx-account-orders__row', 'is-cancelled' => $row['cancelled']])>
                        <span class="webx-account-orders__number">{{ __('webx-orders::site.order.title', ['number' => $row['number']]) }}</span>
                        <span class="webx-account-orders__date">{{ $row['placed_at']->copy()->locale(app()->getLocale())->isoFormat('LL') }}</span>
                        @if ($row['status'] !== null)
                            <span class="webx-account-orders__status webx-account-orders__status--{{ $row['status_code'] }}">{{ $row['status'] }}</span>
                        @endif
                        <span class="webx-account-orders__lines">{{ __('webx-orders::site.account.lines', ['count' => $row['lines']]) }}</span>
                        <span class="webx-account-orders__amount">{{ $row['payable'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        @if ($pages->hasPages())
            <nav class="webx-account-orders__pages" aria-label="{{ __('webx-orders::site.account.pages') }}">
                @if ($pages->previousPageUrl() !== null)
                    <a href="{{ $pages->previousPageUrl() }}" rel="prev">{{ __('webx-orders::site.account.newer') }}</a>
                @endif
                @if ($pages->nextPageUrl() !== null)
                    <a href="{{ $pages->nextPageUrl() }}" rel="next">{{ __('webx-orders::site.account.older') }}</a>
                @endif
            </nav>
        @endif
    @endif
</x-webx-users::account>
