{{--
    An order as the buyer reads it (spec §13.1) — the signed page and «My orders» print the same:
    the date, the public status with its description, the marks, the scale of the public statuses,
    the lines, the money, the delivery and the payment as one line each, the contacts. After an edit
    (§40.1) — «Items changed» with its date and the balance under the money. Never the internal
    status, never the manager's note, never the reason of an edit.

    $order — `WebxUi\Orders\Site\OrderPage::of()`.

    Override: <layer>/views/vendor/webx-orders/order/body.blade.php.
    Classes: webx-order, __head, __date, __status (--<code>), __description, __marks, __mark (--<key>),
    __scale, __step (is-done, is-current), __notice, __section, __heading, __items, __item, __image,
    __changed, __name, __options, __parts, __qty, __total, __sums, __sum (is-strong),
    __balance (--due, --over), __facts, __fact.
--}}
<div class="webx-order">
    <div class="webx-order__head">
        <p class="webx-order__date">{{ __('webx-orders::site.order.placed', ['date' => $order['placed_at']->copy()->locale(app()->getLocale())->isoFormat('LL')]) }}</p>
        @if ($order['status'] !== null)
            <p class="webx-order__status webx-order__status--{{ $order['status']['code'] }}">{{ $order['status']['name'] }}</p>
            @if ($order['status']['description'] !== null)
                <p class="webx-order__description">{{ $order['status']['description'] }}</p>
            @endif
        @endif
        @if ($order['marks'] !== [])
            <ul class="webx-order__marks">
                @foreach ($order['marks'] as $mark)
                    <li class="webx-order__mark webx-order__mark--{{ $mark['key'] }}">{{ $mark['label'] }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    @if (count($order['scale']) > 1)
        <ol class="webx-order__scale" aria-label="{{ __('webx-orders::site.order.scale') }}">
            @foreach ($order['scale'] as $step)
                <li @class(['webx-order__step', 'is-done' => $step['state'] === 'done', 'is-current' => $step['state'] === 'current']) @if ($step['state'] === 'current') aria-current="step" @endif>{{ $step['name'] }}</li>
            @endforeach
        </ol>
    @endif

    @if ($order['awaiting_payment'])
        <p class="webx-order__notice" role="status">{{ __('webx-orders::site.order.awaiting_payment') }}</p>
    @endif

    <section class="webx-order__section">
        <h2 class="webx-order__heading">{{ __('webx-orders::site.order.items') }}</h2>
        @if ($order['changed_at'] !== null)
            <p class="webx-order__changed">{{ __('webx-orders::site.order.changed', ['date' => $order['changed_at']->copy()->locale(app()->getLocale())->isoFormat('LL')]) }}</p>
        @endif
        <ul class="webx-order__items">
            @foreach ($order['items'] as $item)
                <li class="webx-order__item">
                    @if (is_string($item['image']) && $item['image'] !== '')
                        <img class="webx-order__image" src="{{ $item['image'] }}" alt="" loading="lazy" width="56" height="56">
                    @endif
                    <div class="webx-order__name">
                        <span>{{ $item['name'] }}</span>
                        @if ($item['options'])
                            <span class="webx-order__options">{{ $item['options'] }}</span>
                        @endif
                        @if ($item['parts'] !== [])
                            <ul class="webx-order__parts">
                                @foreach ($item['parts'] as $part)
                                    <li>{{ $part['name'] }} × {{ $part['qty'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <span class="webx-order__qty">{{ $item['qty'] }} × {{ $item['price'] }}</span>
                    <span class="webx-order__total">{{ $item['total'] }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="webx-order__sums">
            <dt>{{ __('webx-orders::site.order.subtotal') }}</dt>
            <dd>{{ $order['totals']['subtotal'] }}</dd>
            @foreach ($order['adjustments'] as $adjustment)
                <dt>{{ $adjustment['label'] }}</dt>
                <dd>{{ $adjustment['amount'] }}</dd>
            @endforeach
            @if ($order['totals']['tenders'] !== null)
                <dt>{{ __('webx-orders::site.order.total') }}</dt>
                <dd>{{ $order['totals']['total'] }}</dd>
                <dt>{{ __('webx-orders::site.order.tenders') }}</dt>
                <dd>−{{ $order['totals']['tenders'] }}</dd>
            @endif
            <dt class="is-strong">{{ __('webx-orders::site.order.payable') }}</dt>
            <dd class="is-strong">{{ $order['totals']['payable'] }}</dd>
        </dl>
        @if ($order['balance'] !== null)
            <p class="webx-order__balance webx-order__balance--{{ $order['balance']['kind'] }}" role="status">{{ $order['balance']['label'] }}</p>
        @endif
    </section>

    @if ($order['blocks'] !== [])
        <section class="webx-order__section">
            <dl class="webx-order__facts">
                @foreach ($order['blocks'] as $block)
                    <div class="webx-order__fact webx-order__fact--{{ $block['key'] }}">
                        <dt>{{ $block['label'] }}</dt>
                        <dd>{{ $block['line'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    <section class="webx-order__section">
        <h2 class="webx-order__heading">{{ __('webx-orders::site.order.contacts') }}</h2>
        <dl class="webx-order__facts">
            <div class="webx-order__fact">
                <dt>{{ __('webx-orders::site.order.customer') }}</dt>
                <dd>{{ collect([$order['customer']['name'], $order['customer']['phone'], $order['customer']['email']])->filter()->implode(', ') }}</dd>
            </div>
            @if ($order['recipient'] !== null)
                <div class="webx-order__fact">
                    <dt>{{ __('webx-orders::site.order.recipient') }}</dt>
                    <dd>{{ collect([$order['recipient']['name'], $order['recipient']['phone']])->filter()->implode(', ') }}</dd>
                </div>
            @endif
            @if ($order['comment'] !== null)
                <div class="webx-order__fact">
                    <dt>{{ __('webx-orders::site.order.comment') }}</dt>
                    <dd>{{ $order['comment'] }}</dd>
                </div>
            @endif
        </dl>
    </section>
</div>
