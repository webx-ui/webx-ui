{{--
    The page of «gateway A» (spec §21.2): a stand-in for a gateway's own site on a development
    site — what it shows and the buttons a real gateway's buyer would have, and the failures a test
    asks for. English only: it is the «gateway's», not the shop's.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Gateway A — test payment</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.5 system-ui, sans-serif; color: #1f2328; background: #f6f7f9; }
        .fake-gateway__banner { margin: 0; padding: 8px 16px; background: #8a1c1c; color: #fff; font-weight: 600; text-align: center; }
        .fake-gateway { max-width: 480px; margin: 0 auto; padding: 24px 16px; }
        .fake-gateway__card { padding: 16px; border-radius: 8px; background: #fff; border: 1px solid #d0d7de; }
        .fake-gateway__amount { font-size: 28px; font-weight: 700; margin: 8px 0; }
        .fake-gateway__meta { margin: 0; color: #57606a; font-size: 14px; overflow-wrap: anywhere; }
        .fake-gateway__buttons { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
        .fake-gateway__buttons form { margin: 0; }
        .fake-gateway__buttons button { padding: 8px 12px; border-radius: 6px; border: 1px solid #8c959f; background: #fff; font: inherit; cursor: pointer; }
        .fake-gateway__buttons button[value="pay"] { background: #1f6feb; border-color: #1f6feb; color: #fff; }
        .fake-gateway__flash { padding: 8px 12px; border-radius: 6px; background: #dafbe1; }
    </style>
</head>
<body>
<p class="fake-gateway__banner" role="status">Test gateway — no money moves</p>
<main class="fake-gateway">
    @if (session('webx-payments.fake'))
        <p class="fake-gateway__flash">{{ session('webx-payments.fake') }}</p>
    @endif
    <div class="fake-gateway__card">
        <p class="fake-gateway__meta">Gateway A · {{ $state['type'] }} · payment {{ $state['id'] }}</p>
        <p class="fake-gateway__amount">{{ $amount }}</p>
        <p class="fake-gateway__meta">Status at the gateway: <strong data-status>{{ $state['status'] }}</strong>{{ $state['capture'] ? '' : ' · hold' }}</p>
        <div class="fake-gateway__buttons">
            @foreach ($buttons as $button)
                <form method="post" action="{{ route(\WebxUi\Payments\Testing\FakeGatewaySite::ACTION, ['uuid' => $state['uuid'], 'action' => $button]) }}">
                    <button type="submit" name="button" value="{{ $button }}">{{ [
                        'pay' => 'Pay',
                        'decline' => 'Decline',
                        'cancel' => 'Cancel',
                        'abandon' => 'Leave without paying',
                        'pay-later' => 'Pay, notify later',
                        'repeat-notify' => 'Repeat the notification',
                        'bad-signature' => 'Bad signature',
                        'foreign' => 'Foreign payment',
                        'hold' => 'Hold',
                        'approve' => 'Approve',
                    ][$button] ?? $button }}</button>
                </form>
            @endforeach
        </div>
        @if ($back)
            <p><a href="{{ $back }}">Back to the shop</a></p>
        @endif
    </div>
</main>
</body>
</html>
