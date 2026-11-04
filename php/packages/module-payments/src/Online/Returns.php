<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\PlacedOrders;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeDecision;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\Exchange\SignatureCheck;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;

/**
 * GET|POST /pay/return/{uuid} (spec §8.3): the buyer back from the gateway's page, success and
 * failure alike. What the gateway added to the address or the form is journaled as it came
 * (`browser/return`, without cookies) and never read for the status (decision 26): the status is
 * the gateway's API's, asked under the attempt's lock — or the row's, when a notification was
 * first.
 *
 * Paid or held — the «Thank you» of the checkout for an order this session placed, else the
 * order's signed page, with «Payment received»; anything else — the page of payment, which shows
 * the reason, «we are checking» or «Payment not completed».
 */
final readonly class Returns
{
    public const string FLASH = 'webx-payments.status';

    public function __construct(
        private ExchangeJournal $journal,
        private Methods $methods,
        private MethodCredentials $credentials,
        private GatewayCalls $calls,
        private Checks $checks,
        private PaymentLinks $links,
        private OrderLinks $orderLinks,
        private CheckoutLinks $checkoutLinks,
        private Redirector $redirect,
    ) {}

    public function back(Request $request, string $uuid): RedirectResponse
    {
        $started = CarbonImmutable::now();
        $trace = ExchangeContext::newTrace();
        $payment = Payment::query()->where('uuid', $uuid)->first();
        [$gateway, $secrets] = $this->secrets($payment, $trace);

        // 0. Before anything, even before the attempt is known: what the buyer came back with.
        $id = $this->journal->record(new ExchangeEntry(
            kind: ExchangeKind::Return,
            trace: $trace,
            gateway: $gateway,
            methodKey: $payment?->method_key,
            paymentId: $payment?->id,
            orderId: $payment?->order_id,
            source: 'return',
            httpMethod: $request->getMethod(),
            url: $request->fullUrl(),
            ip: $request->ip(),
            requestHeaders: self::withoutCookies($request),
            requestBody: $request->isMethod('GET') ? null : $request->getContent(),
            startedAt: $started,
        ), $secrets);

        if ($payment === null) {
            $this->decide($id, new ExchangeDecision(Outcome::Unmatched, signature: SignatureCheck::None, message: 'No attempt has this uuid'), $secrets);

            throw new NotFoundHttpException;
        }

        $before = $payment->status;
        $decision = null;

        if (in_array($before, [PaymentStatus::Created, PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
            try {
                $result = $this->checks->check($payment, 'return', $trace, complete: true, wait: 5);
                $decision = $result !== null
                    ? new ExchangeDecision($result->outcome, $payment->id, $payment->order_id, SignatureCheck::None, $result->reported?->value, $result->before->value, $result->after->value, $result->message)
                    : null;
            } catch (Throwable $e) {
                // The gateway failed or did not answer: the status is not touched, the page says «checking».
                Log::warning('webx-payments: the return could not ask the gateway', ['payment' => $payment->uuid, 'exception' => $e::class]);
                $decision = new ExchangeDecision(Outcome::Error, $payment->id, $payment->order_id, SignatureCheck::None, null, $before->value, $before->value, mb_strcut($e->getMessage(), 0, 500));
            }
        }

        $payment->refresh();
        $this->decide($id, $decision ?? new ExchangeDecision(
            Outcome::Same, $payment->id, $payment->order_id, SignatureCheck::None, null, $before->value, $payment->status->value,
            $before === $payment->status ? 'The status was known before the return' : 'Settled by another request',
        ), $secrets);

        return $this->toBuyer($request, $payment);
    }

    private function toBuyer(Request $request, Payment $payment): RedirectResponse
    {
        /** @var Order $order */
        $order = $payment->order()->firstOrFail();
        $locale = (string) $order->locale;

        if (! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Authorized], true)) {
            return $this->redirect->to($this->links->page($order) ?: '/', 303);
        }

        $words = $payment->status === PaymentStatus::Paid ? 'webx-payments::buyer.pay.received' : 'webx-payments::buyer.pay.accepted';
        $placed = $request->hasSession() && (new PlacedOrders($request->session()))->has((string) $order->number);
        $to = $placed ? $this->checkoutLinks->thanks((string) $order->number, $locale) : '';
        $to = $to !== '' ? $to : $this->orderLinks->page((string) $order->number, $locale);

        // «Thank you» of the checkout says it in its own words' place; the order's page and the
        // page of payment read this module's flash.
        return $this->redirect->to($to !== '' ? $to : ($this->links->page($order) ?: '/'), 303)
            ->with($placed ? PlacedOrders::NOTICE : self::FLASH, (string) trans($words, [], $locale));
    }

    /** @return array{0: string|null, 1: ExchangeSecrets} the gateway and the keys to wipe */
    private function secrets(?Payment $payment, string $trace): array
    {
        try {
            $method = $payment !== null ? $this->methods->any($payment->method_key) : null;
            $type = $payment !== null ? $this->calls->type($payment->type) : null;

            if ($method === null || $type === null) {
                return [null, ExchangeSecrets::none()];
            }

            $http = $this->calls->http($type, $method, $this->credentials->for($method), ExchangeKind::Status, 'return', $payment, $trace);

            return [$http->context()->gateway, $http->secrets()];
        } catch (Throwable) {
            return [null, ExchangeSecrets::none()];
        }
    }

    private function decide(?int $id, ExchangeDecision $decision, ExchangeSecrets $secrets): void
    {
        if ($id !== null) {
            $this->journal->decide($id, $decision, $secrets);
        }
    }

    /** @return array<string, list<string|null>> */
    private static function withoutCookies(Request $request): array
    {
        $headers = $request->headers->all();
        unset($headers['cookie']);

        return $headers;
    }
}
