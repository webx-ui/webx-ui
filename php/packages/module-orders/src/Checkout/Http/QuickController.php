<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Cart\Money;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\CheckoutAntispam;
use WebxUi\Orders\Checkout\CheckoutSettings;
use WebxUi\Orders\Checkout\QuickOrders;
use WebxUi\Orders\Checkout\Steps\ContactsStep;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Widgets\Forms\FormResponse;

/**
 * `POST /checkout/quick` — the dialog «Buy in one click» (spec §29): a phone, a name if the buyer
 * gives one, and the line of the button. The answer stays in the dialog — the shop's words and the
 * order's number and amount — with no page of thanks: the manager calls back, and writes the
 * delivery and the payment into the order (`patchBlock()`).
 *
 * Without the cart and the steps there is less to stop a robot, so the antispam is stricter than
 * the confirmation's (§28): the mark of time, the hidden field, the origin, and a few orders an
 * hour from one address and from one phone. A refusal says why under the form and writes nothing.
 */
final readonly class QuickController
{
    /** The key no field of the dialog has: a refusal of the whole form prints under it. */
    public const string FORM_ERROR = 'quick';

    /** The limiters of the orders an hour (§28): one address, one phone. */
    private const string LIMIT = 'webx-orders-quick|';

    /** How long a repeat of the same dialog is the same order: a double press, a resent form. */
    private const int REPEAT_SECONDS = 600;

    public function __construct(
        private CheckoutSettings $settings,
        private CheckoutAntispam $antispam,
        private QuickOrders $quick,
        private Orders $orders,
        private Money $money,
        private Container $container,
    ) {}

    /**
     * @throws ValidationException the fields, or why there is no order
     */
    public function __invoke(Request $request): FormResponse
    {
        // Off — no door: the button is not printed either.
        if (! $this->settings->quick()) {
            throw new NotFoundHttpException;
        }

        $locale = app()->getLocale();

        if ($this->antispam->foreign($request) || $this->antispam->tooFast($request)) {
            throw self::refusal('webx-orders::checkout.errors.retry');
        }

        // A robot is told it worked, and nothing is written.
        if ($this->antispam->trapped($request)) {
            Log::info('webx-orders: the one-click order\'s honeypot was filled', ['ip' => $request->ip()]);

            return FormResponse::message($this->settings->quickMessage($locale));
        }

        $data = $this->validated($request);
        $name = trim((string) ($data['name'] ?? ''));
        // An order wants a first name (§6.1); a buyer who gave none is «Buyer» in the order's language.
        $customer = (new Contact(
            $name !== '' ? $name : (string) __('webx-orders::checkout.quick.anonymous', [], $locale),
            null,
            (string) $data['phone'],
        ))->normalised();
        $line = [
            'type' => (string) $data['type'],
            'ref' => is_int($data['ref']) ? $data['ref'] : (string) $data['ref'],
            'qty' => (int) ($data['qty'] ?? 1),
            'options' => is_array($data['options'] ?? null) ? $data['options'] : [],
        ];

        $limits = [self::LIMIT.'ip|'.$request->ip(), self::LIMIT.'phone|'.$customer->phoneKey()];
        $max = max(1, (int) config('webx-orders.checkout.throttle.quick_per_hour', 3));

        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit, $max)) {
                throw self::refusal('webx-orders::checkout.errors.quick-too-many')->status(429);
            }
        }

        $key = $this->key($request, $line, (string) $customer->phoneKey());

        try {
            $order = $this->quick->place($line, $customer, $locale, $this->visitor(), $key);
        } catch (ValidationException $e) {
            // The line refused by the cart (gone, its options, the quantity) or the contact by the
            // order: the dialog has no field for most of it, so the words go under the form.
            throw ValidationException::withMessages([self::FORM_ERROR => array_values(array_map(strval(...), Arr::flatten($e->errors())))]);
        } catch (CartNotOrderable) {
            throw self::refusal('webx-orders::checkout.errors.quick-unavailable');
        } catch (OrderRefused $e) {
            throw ValidationException::withMessages([self::FORM_ERROR => [$e->getMessage()]]);
        } catch (UniqueConstraintViolationException $e) {
            // Two presses at once: the other one wrote the order of this key first.
            $order = $this->orders->findByIdempotencyKey($key) ?? throw $e;
        }

        if ($order->wasRecentlyCreated) {
            foreach ($limits as $limit) {
                RateLimiter::hit($limit, 3600);
            }
        }

        return FormResponse::message($this->settings->quickMessage($locale), $this->summary($order));
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(Request $request): array
    {
        $rules = [
            'type' => ['required', 'string', 'max:64'],
            'ref' => ['required', 'max:64'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'options' => ['nullable', 'array'],
            'phone' => ['required', 'string', 'max:32', 'regex:'.ContactsStep::PHONE],
            'name' => ['nullable', 'string', 'max:190'],
        ];

        if ($this->settings->terms() === CheckoutSettings::TERMS_CHECKBOX) {
            $rules['terms'] = ['accepted'];
        }

        $messages = [
            'phone.required' => (string) __('webx-orders::checkout.errors.required', ['field' => (string) __('webx-orders::checkout.fields.phone')]),
            'phone.regex' => (string) __('webx-orders::checkout.errors.phone'),
            'terms.accepted' => (string) __('webx-orders::checkout.errors.terms'),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        // The line is the button's, not the buyer's: a broken one is one refusal, not a field.
        if ($validator->errors()->hasAny(['type', 'ref', 'qty', 'options'])) {
            throw self::refusal('webx-orders::checkout.errors.quick-unavailable');
        }

        return $validator->validate();
    }

    /**
     * The key of a repeat (§24.3), from what was sent rather than from the page: a product's page
     * may be cached for everybody, and a key printed in it would make every buyer's order the
     * first one's. The same phone, line and address within ten minutes is the same order.
     *
     * @param  array<string, mixed>  $line
     */
    private function key(Request $request, array $line, string $phone): string
    {
        return 'quick:'.hash('sha256', implode('|', [
            (string) $request->ip(),
            $phone,
            json_encode($line, JSON_THROW_ON_ERROR),
            intdiv(time(), self::REPEAT_SECONDS),
        ]));
    }

    /** «Order 1024 for 120.00» under the shop's words. */
    private function summary(Order $order): string
    {
        return '<p>'.e((string) __('webx-orders::checkout.quick.order', [
            'number' => (string) $order->number,
            'amount' => $this->money->format($order->payable, $order->currency),
        ])).'</p>';
    }

    private function visitor(): ?int
    {
        return $this->container->bound(Visitors::class) ? $this->container->make(Visitors::class)->current()?->id : null;
    }

    private static function refusal(string $word): ValidationException
    {
        return ValidationException::withMessages([self::FORM_ERROR => [(string) __($word)]]);
    }
}
