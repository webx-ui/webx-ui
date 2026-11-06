<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\Checks;
use WebxUi\Payments\Online\Handoffs;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Online\PayPage;
use WebxUi\Payments\Online\Returns;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;

/**
 * The buyer's doors of an online payment (spec §4, §8): the page of payment of an order and its
 * «Pay» behind the order's signed link — a wrong or missing signature is a 404, the page does not
 * say that such a number exists —, the step to the gateway and the way back by the attempt's
 * uuid, and the JSON the page «we are checking» polls.
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
        $status = $request->session()->get(Returns::FLASH);

        return $views->make('webx-payments::pay.page', [
            'page' => $this->page->of($order, is_string($error) ? $error : null, is_string($status) ? $status : null),
        ]);
    }

    /**
     * «Pay»: a new attempt with the order's own online method, then the step to the gateway. A
     * top-up of a paid order (decision 29) may take another online method — the one posted, if it
     * is offered; the order's block stays: it tells of the first payment.
     */
    public function attempt(Request $request, Payments $payments): RedirectResponse
    {
        $order = $this->order($request);
        $block = is_array($order->payment) ? $order->payment : [];
        $method = is_string($block['method'] ?? null) ? $this->page->online((string) $block['method']) : null;

        if ($order->paid_at !== null && $request->filled('method')) {
            $method = $this->offered($order, $request->string('method')->toString());
        }

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

    /**
     * «Continue» under the list of methods (§8.5, groups spec §6.8): the method switched, then the
     * gateway — an offline one has none, the page says what comes next. The block of a paid order
     * tells of its first payment and stays. The form sends a group and a method, read by §6.4.
     */
    public function method(Request $request, Payments $payments): RedirectResponse
    {
        $order = $this->order($request);

        if ($order->paid_at !== null) {
            return $this->redirect->to($this->links->page($order), 303);
        }

        $key = self::word($request->input('method'));
        $group = self::word($request->input('group'));
        $option = $this->page->picked($order, $group, $key);

        if ($option === null) {
            $word = $key !== null || $group !== null ? 'method_gone' : 'choose_method';

            return $this->redirect->to($this->links->page($order), 303)->with(Handoffs::FLASH, (string) trans('webx-payments::buyer.errors.'.$word, [], (string) $order->locale));
        }

        try {
            $payment = $payments->switchMethod($order, $option->method, 'pay-page');
        } catch (BeginRefused $refused) {
            return $this->redirect->to($this->links->page($order), 303)->with(Handoffs::FLASH, $refused->getMessage());
        }

        return $this->redirect->to($payment !== null ? $this->links->go($payment) : $this->links->page($order), 303);
    }

    public function go(Request $request, Handoffs $handoffs, string $uuid): Response
    {
        $payment = Payment::query()->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;

        return $handoffs->go($payment, $request);
    }

    /** The buyer back from the gateway (§8.3), GET or POST. */
    public function back(Request $request, Returns $returns, string $uuid): RedirectResponse
    {
        return $returns->back($request, $uuid);
    }

    /**
     * What the page «we are checking» polls (§8.4): the attempt's status from its row; the gateway
     * is asked at most once in 15 s. The uuid is the only key — 26 random letters.
     */
    public function status(Checks $checks, string $uuid): JsonResponse
    {
        $payment = Payment::query()->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;
        $checks->recheck($payment, 'pay-page');
        $payment->refresh();

        return new JsonResponse([
            'status' => $payment->status->value,
            'final' => ! in_array($payment->status, [PaymentStatus::Created, PaymentStatus::Pending], true),
            // Nothing is coming: a script stops waiting, the page says what the buyer may do now.
            'not_started' => $payment->notStarted(),
            'declined' => $payment->declined(),
        ]);
    }

    /** A field of the form as sent: an empty one or an array says nothing. */
    private static function word(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** An online method the page offers a top-up of this order now; null — anything else. */
    private function offered(Order $order, string $key): ?Method
    {
        foreach ($this->page->onlineOptions($order) as $option) {
            if ($option->method->key === $key) {
                return $option->method;
            }
        }

        return null;
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
