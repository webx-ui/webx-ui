<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Http;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\CheckoutLinks;
use WebxUi\Orders\Checkout\CheckoutSettings;
use WebxUi\Orders\Checkout\CheckoutSummary;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Checkout\PlaceOrderBuilder;
use WebxUi\Orders\Checkout\Steps\CheckoutSteps;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\PayableChanged;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * The checkout page (spec §19–§24): one page, a form a step, and the column of the totals with the
 * confirmation. Without JavaScript every form is a POST that comes back to the page; with it the
 * shell of the widgets' forms sends the same forms in place and gets JSON (`FormResponse`).
 *
 * Only the visitor's own cart: `CartHandle`'s, never an id from the address or the body. Showing
 * the page writes nothing — a draft comes with the first saved step (§21.1).
 */
final readonly class CheckoutController
{
    /** The id of the confirmation's form: a page that comes back from it prints its answer there. */
    public const string PLACE_FORM = 'checkout-place';

    public function __construct(
        private CheckoutContexts $contexts,
        private CheckoutSteps $steps,
        private CheckoutSummary $summary,
        private CheckoutSettings $settings,
        private CheckoutLinks $links,
        private Views $views,
        private Money $money,
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
        ]);
    }

    /** `GET /checkout/summary` — the column alone, for the script to put in place of the old one (§23.2). */
    public function summary(): View
    {
        return $this->views->make('webx-orders::checkout.summary', $this->column($this->contexts->current()));
    }

    /** `POST /checkout/steps/{step}` — keep a step in the draft and come back to the page. */
    public function step(Request $request): FormResponse|RedirectResponse
    {
        $context = $this->contexts->current();
        $key = (string) $request->route('step');

        if ($context->isEmpty() || $context->cart === null) {
            return FormResponse::redirect($this->links->page($context->locale));
        }

        $step = $this->steps->find($key, $context) ?? throw new NotFoundHttpException;
        $draft = $context->draft ?? new CheckoutDraft(['cart_id' => $context->cart->id]);

        $step->save($context, $request->all(), $draft);
        $draft->last_step = $key;
        $draft->save();
        $this->contexts->forget();

        // Without JavaScript straight to the page: the next step is open there.
        return $request->expectsJson()
            ? FormResponse::ok()
            : Redirect::to($this->links->page($context->locale).'#checkout-step-'.$key);
    }

    /**
     * `POST /checkout/place` (§24.1): every step checked for the order, the order put together by
     * the steps and placed with the amount the buyer saw.
     *
     * @throws ValidationException what a step or the agreement still lacks
     */
    public function place(Request $request, Orders $orders, OrderLinks $orderLinks): FormResponse|JsonResponse|RedirectResponse
    {
        $context = $this->contexts->current();

        if ($context->isEmpty()) {
            return FormResponse::redirect($this->links->page($context->locale));
        }

        $this->ready($context, $request);

        $builder = new PlaceOrderBuilder;

        foreach ($this->steps->for($context) as $step) {
            $step->contribute($context, $builder);
        }

        $expected = $request->input('expected_payable');

        try {
            $order = $orders->place($builder->build($context, is_numeric($expected) ? (int) $expected : null));
        } catch (PayableChanged $e) {
            return $this->conflict($request, $context, 'checkout.payable_changed', (string) __('webx-orders::checkout.errors.payable-changed', [
                'was' => $this->format($expected, $context),
                'now' => $this->format($e->state->payable, $context),
            ]));
        } catch (CartNotOrderable $e) {
            return $this->conflict($request, $context, 'checkout.cart_not_orderable', implode(' ', array_map(
                static fn ($warning): string => $warning->message,
                $e->warnings,
            )) ?: (string) __('webx-orders::checkout.errors.not-orderable'));
        } catch (OrderRefused $e) {
            return $this->conflict($request, $context, 'checkout.refused', $e->getMessage());
        }

        return FormResponse::redirect($orderLinks->page((string) $order->number, $order->locale) ?: $this->links->page($context->locale));
    }

    /**
     * The steps as the page prints them: the first one not filled is open, the filled ones before
     * it are done (§20.1); the theme decides which of the two shows a form.
     *
     * @return list<array{key: string, number: int, title: string, html: string, summary: string|null, done: bool, open: bool}>
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
            ];
        }

        return $sections;
    }

    /**
     * The column of the totals with the confirmation's form. The amount it shows is what the
     * draft remembers as shown, when there is a draft — a read never makes one (§23.2).
     *
     * @return array<string, mixed>
     */
    private function column(CheckoutContext $context): array
    {
        $column = $this->summary->of($context);

        if ($context->draft !== null && $context->draft->shown_payable !== $context->state->payable) {
            $context->draft->shown_payable = $context->state->payable;
            $context->draft->save();
        }

        return [
            ...$column,
            'action' => $this->links->to(CheckoutLinks::PLACE, $context->locale),
            'form' => self::PLACE_FORM,
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

    /**
     * No order, and why (§24.2): to the script a 409 with a code and the fresh column, to a plain
     * form the page again with the message under the confirmation.
     */
    private function conflict(Request $request, CheckoutContext $context, string $code, string $message): JsonResponse|RedirectResponse
    {
        $this->contexts->forget();
        $fresh = $this->contexts->current();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'ok' => false,
                'code' => $code,
                'message' => $message,
                'html' => $this->views->make('webx-orders::checkout.summary', $this->column($fresh))->render(),
            ], 409);
        }

        return Redirect::to($this->links->page($context->locale))->with(FormResponse::FLASH, [
            'form' => self::PLACE_FORM,
            'heading' => null,
            'heading_html' => null,
            'message' => '<p>'.e($message).'</p>',
            'html' => null,
        ]);
    }

    private function format(mixed $minor, CheckoutContext $context): string
    {
        return is_numeric($minor) && $context->currency !== null
            ? $this->money->format((int) $minor, $context->currency)
            : (string) $minor;
    }
}
