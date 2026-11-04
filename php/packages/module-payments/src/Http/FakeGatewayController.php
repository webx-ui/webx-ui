<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Testing\FakeGatewayNotification;
use WebxUi\Payments\Testing\FakeGatewayNotifier;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * The page of «gateway A» (spec §21.2) — where the buyer lands from /pay/go — and its buttons.
 * 404 unless the fake gateway is on and the site is not in production.
 *
 * `open` takes the form a `form` or `unsigned` type hands off (its fields as the browser posts
 * them); the other buttons change the payment at the gateway, post its notification to the site
 * (`FakeGatewayNotifier`, the path of §9.2) and send the buyer back to /pay/return — or, for the
 * test buttons (repeat, bad signature, foreign), stay on the page and say what the shop answered.
 */
final readonly class FakeGatewayController
{
    /** The buttons after which the buyer leaves the gateway's page for the shop's return address. */
    public const array BACK = ['pay', 'decline', 'cancel', 'abandon', 'pay-later', 'hold', 'approve'];

    public const array ACTIONS = ['pay', 'decline', 'cancel', 'abandon', 'pay-later', 'repeat-notify', 'bad-signature', 'foreign', 'hold', 'approve'];

    public function __construct(
        private Container $app,
        private FakeGatewayServer $server,
        private Router $router,
        private Redirector $redirect,
        private FakeGatewayNotifier $notifier,
    ) {}

    public function show(Views $views, string $uuid): View
    {
        $this->guard();
        $state = $this->server->payment($uuid) ?? throw new NotFoundHttpException;

        return $views->make('webx-payments::fake.page', [
            'state' => $state,
            'amount' => GatewayAmount::toDecimal((int) $state['amount'], (string) $state['currency']).' '.$state['currency'],
            'buttons' => $this->buttons($state),
            'back' => $this->back($state),
        ]);
    }

    public function act(Request $request, string $uuid, string $action): RedirectResponse
    {
        $this->guard();

        if ($action === 'open') {
            return $this->open($request, $uuid);
        }

        $state = $this->server->payment($uuid) ?? throw new NotFoundHttpException;

        if (! in_array($action, $this->buttons($state), true)) {
            throw new NotFoundHttpException;
        }

        $state = $this->server->act($uuid, $action) ?? $state;
        $answer = match ($action) {
            'pay', 'decline', 'hold', 'repeat-notify' => $this->notifier->send($uuid),
            'bad-signature' => $this->notifier->send($uuid, 'signature'),
            'foreign' => $this->notifier->send($uuid, 'foreign'),
            'pay-later' => $this->later($uuid),
            default => null,
        };

        // Like a real gateway's page: after paying, declining or cancelling the buyer goes back to the shop.
        $back = in_array($action, self::BACK, true) ? $this->back($state) : null;

        if ($back !== null) {
            return $this->redirect->to($back, 303);
        }

        $done = "Gateway A: «{$action}» done.".($answer !== null ? " The shop answered the notification with HTTP {$answer->getStatusCode()}." : '');

        return $this->redirect->route(FakeGatewaySite::PAGE, ['uuid' => $uuid], 303)->with('webx-payments.fake', $done);
    }

    /** The form of a `form` or `unsigned` type, as a real gateway's page takes it. */
    private function open(Request $request, string $uuid): RedirectResponse
    {
        $data = $request->input('data');

        if (is_string($data)) {
            if (! hash_equals(FakeGatewayServer::sign($data), (string) $request->input('signature'))) {
                abort(403, 'Gateway A: the form is not signed with the test key');
            }

            $fields = json_decode((string) base64_decode($data, true), true);
            $type = 'form';
            $return = is_array($fields) ? ($fields['result_url'] ?? null) : null;
            $notify = is_array($fields) ? ($fields['server_url'] ?? null) : null;
        } else {
            $fields = $request->only(['order_id', 'amount', 'currency', 'success_url', 'phone']);
            $type = 'unsigned';
            $return = $fields['success_url'] ?? null;
            $notify = null;
        }

        if (! is_array($fields) || ($fields['order_id'] ?? null) !== $uuid || ! is_string($fields['amount'] ?? null) || ! is_string($fields['currency'] ?? null)) {
            abort(422, 'Gateway A: the form does not describe this payment');
        }

        if ($this->server->payment($uuid) === null) {
            $this->server->open($uuid, $type, GatewayAmount::fromDecimal($fields['amount'], $fields['currency']), $fields['currency'], $type === 'form' ? 'wait' : 'CREATED', [
                'return_url' => $return,
                'notify_url' => $notify,
                'phone' => $fields['phone'] ?? null,
            ]);
        }

        return $this->redirect->route(FakeGatewaySite::PAGE, ['uuid' => $uuid], 303);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private function buttons(array $state): array
    {
        $buttons = ['pay', 'decline', 'cancel', 'abandon', 'pay-later', 'repeat-notify', 'bad-signature', 'foreign'];

        if ($state['type'] === 'card' && ! $state['capture']) {
            $buttons[] = 'hold';
        }

        if ($state['type'] === 'form') {
            $buttons[] = 'approve';
        }

        return $buttons;
    }

    /** «Pay, notify later»: the notification from the queue, half a minute after the buyer is back. */
    private function later(string $uuid): null
    {
        FakeGatewayNotification::dispatch($uuid)->delay(now()->addSeconds(FakeGatewayNotification::DELAY_SECONDS));

        return null;
    }

    /** @param  array<string, mixed>  $state */
    private function back(array $state): ?string
    {
        return $this->router->has(PaymentLinks::RETURN) && is_string($state['return_url'] ?? null) ? $state['return_url'] : null;
    }

    private function guard(): void
    {
        if (! FakeGatewaySite::on($this->app)) {
            throw new NotFoundHttpException;
        }
    }
}
