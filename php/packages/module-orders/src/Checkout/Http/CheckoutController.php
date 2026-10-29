<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\CheckoutAnswers;
use WebxUi\Orders\Checkout\CheckoutAntispam;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\CheckoutPreview;
use WebxUi\Orders\Checkout\CheckoutSettings;
use WebxUi\Orders\Checkout\CheckoutSummary;
use WebxUi\Orders\Checkout\CheckoutTerms;
use WebxUi\Orders\Checkout\Events\CheckoutPayableChanged;
use WebxUi\Orders\Checkout\Events\CheckoutPlaced;
use WebxUi\Orders\Checkout\Events\CheckoutStepSaved;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlacedOrders;
use WebxUi\Orders\Checkout\PlaceKeys;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;
use WebxUi\Orders\Checkout\PlacingListener;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\Checkout\Summary\CheckoutSummaryParts;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Notifications\OrderEvents;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\PayableChanged;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Orders\Site\OrderPage;
use WebxUi\Orders\Site\VisitorOrders;
use WebxUi\Users\SignIn;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * The checkout page (spec §19–§26): one page, a form a step, and the column of the totals with the
 * confirmation beside them; «Thank you» after it. Without JavaScript every form is a POST that
 * comes back to the page; with it the shell of the widgets' forms sends the same forms in place
 * and gets JSON (`FormResponse`).
 *
 * Only the visitor's own cart: `CartHandle`'s, never an id from the address or the body. Showing
 * the page writes nothing — a draft comes with the first saved step (§21.1).
 */
final readonly class CheckoutController
{
    /** The id of the confirmation's form: a page that comes back from it prints its answer there. */
    public const string PLACE_FORM = 'checkout-place';

    /** The limiter of orders a cart makes in an hour (§28); the attempts are the route's. */
    private const string ORDERS_LIMIT = 'webx-orders-checkout-orders|';

    public function __construct(
        private CheckoutContexts $contexts,
        private CheckoutSteps $steps,
        private CheckoutSlots $slots,
        private CheckoutSummaryParts $parts,
        private CheckoutSummary $summary,
        private CheckoutSettings $settings,
        private CheckoutTerms $terms,
        private CheckoutLinks $links,
        private CheckoutAntispam $antispam,
        private PlaceKeys $keys,
        private PlacedOrders $placed,
        private Views $views,
        private Money $money,
        private Dispatcher $events,
    ) {}

    /** `GET /checkout`. */
    public function show(): View
    {
        $context = $this->contexts->current();

        if ($context->isEmpty()) {
            return $this->views->make('webx-orders::checkout.empty', [
                'home' => (string) (config('webx-orders.checkout.empty_url') ?: url('/')),
            ]);
        }

        return $this->views->make('webx-orders::checkout.page', [
            'steps' => $this->sections($context),
            'warnings' => $this->summary->warnings($context),
            'summary' => $this->column($context),
            'summaryUrl' => $this->links->to(CheckoutLinks::SUMMARY, $context->locale),
            'parts' => $this->parts->render($context),
        ]);
    }

    /** `GET /checkout/summary` — the column alone, for the script to put in place of the old one (§23.2). */
    public function summary(): View
    {
        return $this->views->make('webx-orders::checkout.summary', $this->column($this->contexts->current()));
    }

    /** `POST /checkout/steps/{step}` — keep a step in the draft and come back to the page. */
    public function step(Request $request): FormResponse|JsonResponse|RedirectResponse
    {
        $context = $this->contexts->current();
        $key = (string) $request->route('step');

        if ($this->antispam->foreign($request)) {
            return $this->refusal($request, $context, CheckoutAnswers::ORIGIN, 403);
        }

        if ($context->isEmpty() || $context->cart === null) {
            return FormResponse::redirect($this->links->page($context->locale));
        }

        $step = $this->steps->find($key, $context) ?? throw new NotFoundHttpException;

        // A bot is told it worked, and nothing is kept (§28).
        if ($this->antispam->trapped($request)) {
            Log::info('webx-orders: the checkout\'s honeypot was filled', ['step' => $key, 'ip' => $request->ip()]);

            return $this->stepSaved($request, $context, $key);
        }

        $draft = $context->draft ?? new CheckoutDraft(['cart_id' => $context->cart->id]);

        $step->save($context, $request->all(), $draft);
        $draft->last_step = $key;
        $draft->save();
        $this->contexts->forget();
        $this->events->dispatch(new CheckoutStepSaved($context->cart->id, $key));

        return $this->stepSaved($request, $context, $key);
    }

    /**
     * `POST /checkout/place` (§24.1): the antispam, the key of the page, every step checked for
     * the order, the order put together by the steps and placed with the amount the buyer saw —
     * and once more with a smaller one, when that is what it came to (§24.4).
     *
     * @throws ValidationException what a step or the agreement still lacks
     */
    public function place(Request $request, Orders $orders, PlacingListener $listener): FormResponse|JsonResponse|RedirectResponse
    {
        $context = $this->contexts->current();

        // 1. The antispam (§28): a bot that filled the hidden field is answered as if it worked.
        if ($this->antispam->foreign($request)) {
            return $this->refusal($request, $context, CheckoutAnswers::ORIGIN, 403);
        }

        if ($this->antispam->trapped($request)) {
            Log::info('webx-orders: the checkout\'s honeypot was filled', ['step' => 'confirm', 'ip' => $request->ip()]);

            return FormResponse::redirect($this->links->page($context->locale));
        }

        if ($this->antispam->tooFast($request)) {
            return $this->refusal($request, $context, CheckoutAnswers::RETRY, 422);
        }

        // 2. The key: a repeat of a confirmation that made an order is that order (§24.3).
        $key = (string) $request->input('place_key', '');
        $repeat = $this->madeWith($key, $context, $orders);

        if ($repeat !== null) {
            $this->placed->remember((string) $repeat->number);

            return FormResponse::redirect($this->links->thanks((string) $repeat->number, $repeat->locale));
        }

        if ($context->isEmpty() || $context->cart === null) {
            return FormResponse::redirect($this->links->page($context->locale));
        }

        if (! $this->keys->current($context->draft, $key)) {
            return $this->conflict($request, $context, CheckoutAnswers::STALE, (string) __('webx-orders::checkout.errors.stale'));
        }

        // 3. The steps, before the transaction: nothing is written while one of them lacks something.
        $this->ready($context, $request);

        $limit = self::ORDERS_LIMIT.$context->cart->id;

        if (RateLimiter::tooManyAttempts($limit, max(1, (int) config('webx-orders.checkout.throttle.orders_per_hour', 5)))) {
            return $this->refusal($request, $context, CheckoutAnswers::TOO_MANY, 429);
        }

        $draft = $context->draft ?? new CheckoutDraft(['cart_id' => $context->cart->id]);
        $this->terms->accept($draft, $context->locale);
        $draft->place_key = PlaceKeys::hash($key);
        $draft->save();

        // 4. The order as the steps put it together; the payment step, if any, says where to go after it.
        $builder = new PlaceOrderBuilder;
        $payment = null;

        foreach ($this->steps->for($context) as $step) {
            $step->contribute($context, $builder);

            if ($step instanceof SlotStep && $step->key() === CheckoutSlots::PAYMENT) {
                $payment = $step;
            }
        }

        $expected = is_numeric($request->input('expected_payable')) ? (int) $request->input('expected_payable') : null;
        $decrease = null;

        // 5. Placed; less than the buyer saw — placed again with that, the same key (§24.4). Inside
        // the transaction the listener writes the agreement and clears the draft (§21.2).
        $listener->expect($draft);

        try {
            try {
                $order = $orders->place($builder->build($context, $expected, $key));
            } catch (PayableChanged $e) {
                $this->diverged($context->cart->id, $e);

                if ($expected === null || $e->state->payable >= $expected) {
                    throw $e;
                }

                $decrease = $expected - $e->state->payable;

                try {
                    $order = $orders->place($builder->build($context, $e->state->payable, $key));
                } catch (PayableChanged $again) {
                    $this->diverged($context->cart->id, $again);

                    throw $again;
                }
            }
        } catch (PayableChanged $e) {
            return $this->conflict($request, $context, CheckoutAnswers::PAYABLE_CHANGED, (string) __('webx-orders::checkout.errors.payable-changed', [
                'was' => $this->format($expected, $context->currency),
                'now' => $this->format($e->state->payable, $context->currency),
            ]));
        } catch (CartNotOrderable $e) {
            return $this->conflict($request, $context, CheckoutAnswers::NOT_ORDERABLE, CheckoutPreview::notOrderable($e));
        } catch (OrderRefused $e) {
            return $this->conflict($request, $context, CheckoutAnswers::REFUSED, $e->getMessage());
        } finally {
            $listener->expect(null);
        }

        // 6. After the commit: the next column prints a new key, «Thank you» opens for this session —
        // the buyer comes back there from a gateway too — and the payment step may send to one first.
        RateLimiter::hit($limit, 3600);
        $this->keys->forget();
        $this->placed->remember((string) $order->number, $decrease);
        $this->contexts->forget();
        $this->events->dispatch(new CheckoutPlaced($order->id, $context->cart->id));
        $gateway = $payment?->next($order);

        return FormResponse::redirect($gateway ?? $this->links->thanks((string) $order->number, $order->locale));
    }

    /**
     * `GET /checkout/thanks/{number}` (§26): only for the session that placed the order. The
     * signed-in owner of it is sent to its page; anybody else is told there is no such page.
     */
    public function thanks(Request $request, Container $container, OrderPage $page, OrderLinks $orderLinks, OrderChannels $channels): View|RedirectResponse
    {
        $number = (string) $request->route('number');
        $order = Order::query()->where('number', $number)->first() ?? throw new NotFoundHttpException;

        if (! $this->placed->has($number)) {
            $visitor = $container->bound(Visitors::class) ? $container->make(Visitors::class)->current() : null;
            $url = $orderLinks->page($number, $order->locale);

            if ($visitor !== null && $order->user_id === $visitor->id && $url !== '') {
                return Redirect::to($url);
            }

            throw new NotFoundHttpException;
        }

        $decrease = $this->placed->decrease($number);
        $visitor = $container->bound(Visitors::class) ? $container->make(Visitors::class)->current() : null;

        return $this->views->make('webx-orders::checkout.thanks', [
            'order' => $page->of($order, app()->getLocale()),
            'email' => $order->customer_email !== null && $channels->reachesBuyer(OrderEvents::PLACED) ? $order->customer_email : null,
            'decrease' => $decrease !== null ? $this->format($decrease, $order->currency) : null,
            'link' => $orderLinks->page($number, $order->locale),
            'pay' => $order->awaiting_payment ? $this->payAgain($order) : null,
            'analytics' => ['number' => $number, 'payable' => $order->payable, 'currency' => $order->currency],
            // «Save your orders in an account» — a guest, where visitors can register (§26).
            'register' => $visitor === null && $order->customer_email !== null && VisitorOrders::installed() && SignIn::registrationOpen()
                ? $order->customer_email
                : null,
        ]);
    }

    /**
     * «Pay» for an order that still awaits payment (§26): the payment slot's address, or null —
     * no slot, or it failed to answer, and the page says the order awaits payment alone.
     */
    private function payAgain(Order $order): ?string
    {
        try {
            return $this->slots->payment()?->payAgainUrl(OrderData::of($order));
        } catch (Throwable $e) {
            Log::warning("webx-orders: the checkout's slot «payment» failed in payAgainUrl()", ['exception' => $e]);

            return null;
        }
    }

    /**
     * The steps as the page prints them: the first one not filled is open, the filled ones before
     * it are done (§20.1); the theme decides which of the two shows a form.
     *
     * @return list<array{key: string, number: int, title: string, html: string, summary: string|null, done: bool, open: bool, slot: bool}>
     */
    private function sections(CheckoutContext $context): array
    {
        $sections = [];
        $opened = false;

        foreach ($this->steps->for($context) as $index => $step) {
            $summary = $step->summary($context);
            $done = $summary !== null && $step->check($context, Purpose::Checkout) === [];
            $open = ! $done && ! $opened;
            $opened = $opened || $open;

            $sections[] = [
                'key' => $step->key(),
                'number' => $index + 1,
                'title' => $step->title(),
                'html' => $step->render($context)->toHtml(),
                'summary' => $summary,
                'done' => $done,
                'open' => $open,
                'slot' => $step instanceof SlotStep,
            ];
        }

        return $sections;
    }

    /**
     * The column of the totals with the confirmation's form: the amount, the key of a repeat and
     * the antispam's fields (§23.2). The draft, when there is one, remembers the amount shown and
     * the key's hash — a read never makes one.
     *
     * @return array<string, mixed>
     */
    private function column(CheckoutContext $context): array
    {
        $column = $this->summary->of($context);
        $key = $this->keys->issue($context->draft);

        if ($context->draft !== null) {
            $context->draft->shown_payable = $context->state->payable;

            if ($context->draft->isDirty()) {
                $context->draft->save();
            }
        }

        return [
            ...$column,
            'accepted' => $column['terms'] === CheckoutSettings::TERMS_CHECKBOX && $this->terms->accepted($context->draft, $context->locale),
            'action' => $this->links->to(CheckoutLinks::PLACE, $context->locale),
            'form' => self::PLACE_FORM,
            'place_key' => $key,
            'timestamp' => $this->antispam->timestamp(),
            'honeypot' => CheckoutAntispam::HONEYPOT,
        ];
    }

    /**
     * Every step ready for the order and the terms agreed with, or the errors of all of them by
     * field — nothing is written before this passes (§24.1).
     *
     * @throws ValidationException
     */
    private function ready(CheckoutContext $context, Request $request): void
    {
        $errors = [];

        foreach ($this->steps->for($context) as $step) {
            foreach ($step->check($context, Purpose::Order) as $error) {
                $errors[$error->field ?? $step->key()][] = $error->message;
            }
        }

        if ($this->settings->terms() === CheckoutSettings::TERMS_CHECKBOX && ! $request->boolean('terms')) {
            $errors['terms'][] = (string) __('webx-orders::checkout.errors.terms');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** The amount on the page and the count of the confirmation went apart (§30): the funnel counts it. */
    private function diverged(int $cartId, PayableChanged $e): void
    {
        $this->events->dispatch(new CheckoutPayableChanged($cartId, $e->expected, $e->state->payable));
    }

    /** The order a key made, when it was this cart's (§24.3) — anybody else's key is no answer. */
    private function madeWith(string $key, CheckoutContext $context, Orders $orders): ?Order
    {
        if ($context->cart === null || ! PlaceKeys::wellFormed($key)) {
            return null;
        }

        $order = $orders->findByIdempotencyKey($key);

        return $order !== null && $order->cart_id === $context->cart->id ? $order : null;
    }

    private function stepSaved(Request $request, CheckoutContext $context, string $key): FormResponse|RedirectResponse
    {
        // Without JavaScript straight to the page: the next step is open there.
        return $request->expectsJson()
            ? FormResponse::ok()
            : Redirect::to($this->links->page($context->locale).'#checkout-step-'.$key);
    }

    /**
     * No order, and why (§24.2): to the script a 409 with a code and the fresh column, to a plain
     * form the page again with the message under the confirmation.
     */
    private function conflict(Request $request, CheckoutContext $context, string $code, string $message, int $status = 409): JsonResponse|RedirectResponse
    {
        $this->contexts->forget();
        $fresh = $this->contexts->current();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'ok' => false,
                'code' => $code,
                'message' => $message,
                'html' => $this->views->make('webx-orders::checkout.summary', $this->column($fresh))->render(),
            ], $status);
        }

        return Redirect::to($this->links->page($context->locale))->with(FormResponse::FLASH, [
            'form' => self::PLACE_FORM,
            'heading' => null,
            'heading_html' => null,
            'message' => '<p>'.e($message).'</p>',
            'html' => null,
        ]);
    }

    /** Turned away by the antispam or a limit (§28): «Try again», nothing written. */
    private function refusal(Request $request, CheckoutContext $context, string $code, int $status): JsonResponse|RedirectResponse
    {
        $message = (string) __($code === CheckoutAnswers::TOO_MANY ? 'webx-orders::checkout.errors.too-many' : 'webx-orders::checkout.errors.retry');

        return $this->conflict($request, $context, $code, $message, $status);
    }

    private function format(mixed $minor, ?string $currency): string
    {
        return is_numeric($minor) && $currency !== null
            ? $this->money->format((int) $minor, $currency)
            : (string) $minor;
    }
}
