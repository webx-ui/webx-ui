<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\Handoffs;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Online\PayPage;
use WebxUi\Payments\Payments;

/**
 * The buyer's doors of an online payment (spec §4, §8): the page of payment of an order and its
 * «Pay» behind the order's signed link — a wrong or missing signature is a 404, the page does not
 * say that such a number exists —, and the step to the gateway by the attempt's uuid.
 */
final readonly class PayController
{
    public function __construct(
        private PayPage $page,
        private PaymentLinks $links,
        private Redirector $redirect,
    ) {}

    public function show(Request $request, Views $views): View
    {
        $order = $this->order($request);
        $error = $request->session()->get(Handoffs::FLASH);

        return $views->make('webx-payments::pay.page', [
            'page' => $this->page->of($order, is_string($error) ? $error : null),
        ]);
    }

    /** «Pay»: a new attempt with the order's own online method, then the step to the gateway. */
    public function attempt(Request $request, Payments $payments): RedirectResponse
    {
        $order = $this->order($request);
        $block = is_array($order->payment) ? $order->payment : [];
        $method = is_string($block['method'] ?? null) ? $this->page->online((string) $block['method']) : null;

        if ($method === null) {
            return $this->redirect->to($this->links->page($order), 303);
        }

        try {
            $payment = $payments->begin($order, $method, 'pay-page');
        } catch (BeginRefused $refused) {
            return $this->redirect->to($this->links->page($order), 303)->with(Handoffs::FLASH, $refused->getMessage());
        }

        return $this->redirect->to($this->links->go($payment), 303);
    }

    public function go(Request $request, Handoffs $handoffs, string $uuid): Response
    {
        $payment = Payment::query()->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;

        return $handoffs->go($payment, $request);
    }

    private function order(Request $request): Order
    {
        // The language prefix is part of the path, and the path is signed: one link a language.
        if (! $request->hasValidSignature()) {
            throw new NotFoundHttpException;
        }

        return Order::query()->where('number', (string) $request->route('number'))->first() ?? throw new NotFoundHttpException;
    }
}
