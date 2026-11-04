<?php

declare(strict_types=1);

namespace WebxUi\Payments\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\Checkout\StepState;
use WebxUi\Payments\MethodGroupView;
use WebxUi\Payments\MethodOption;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Selections;

/**
 * The doors of the payment step (spec §10.2), the visitor's own: the cart of the checkout's
 * context — the cookie's, never an id from the address — and the delivery it has chosen, which
 * the client never sends. Without a cart — 404.
 *
 * `PUT selection` from the script answers JSON; a form without JavaScript goes back to the
 * checkout, the refusal under `payment` in the session, as Laravel answers any form. `DELETE
 * selection` is the script's alone: the chosen method cannot work on this device and its group has
 * no other (groups spec §4.3).
 */
final class StepController
{
    /** The header the script asks the redrawn step with. */
    public const string VIEW_HEADER = 'X-Webx-Payments-View';

    /** Where the step stands on the checkout's page, for a form that comes back. */
    public const string ANCHOR = 'checkout-step-payment';

    public function __construct(
        private readonly CheckoutContexts $contexts,
        private readonly PaymentsCheckoutSlot $slot,
        private readonly Selections $selections,
        private readonly Methods $methods,
    ) {}

    public function methods(Request $request): JsonResponse
    {
        $context = $this->context();

        return $this->answer($request, $context, $this->slot->state($context));
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $context = $this->context();
        $key = self::word($request->input('method'));
        $group = self::word($request->input('group'));
        $option = $this->methods->picked($this->slot->state($context)->options, $group, $key);

        if ($option === null) {
            $message = (string) trans('webx-payments::buyer.errors.'.($key !== null || $group !== null ? 'method_gone' : 'choose_method'), [], $context->locale);

            if (! $request->expectsJson()) {
                return Redirect::to(url()->previous().'#'.self::ANCHOR)->withErrors(['payment' => $message]);
            }

            return new JsonResponse(['message' => $message, 'errors' => ['method' => [$message]]], 422);
        }

        $this->selections->put((int) $context->cart?->id, $option->key());
        $this->slot->forget($context);

        if (! $request->expectsJson()) {
            return Redirect::to(url()->previous().'#'.self::ANCHOR);
        }

        return $this->answer($request, $context, $this->slot->state($context));
    }

    public function destroy(Request $request): JsonResponse
    {
        $context = $this->context();

        $this->selections->forget((int) $context->cart?->id);
        $this->slot->forget($context);

        return $this->answer($request, $context, $this->slot->state($context));
    }

    /** The methods, the choice and the line for the collapsed step, and the step drawn again if asked. */
    private function answer(Request $request, CheckoutContext $context, StepState $state): JsonResponse
    {
        $body = [
            'methods' => array_map(static fn (MethodOption $option): array => $option->toArray(), $state->options),
            'groups' => array_map(static fn (MethodGroupView $view): array => $view->toArray($context->locale), $this->methods->grouped($state->options)),
            'selected' => $state->chosen?->key(),
            'summary' => $state->chosen?->title,
            'hint' => $state->waiting ? (string) trans('webx-payments::buyer.step.delivery_first', [], $context->locale) : null,
        ];

        if ($request->headers->get(self::VIEW_HEADER) === 'step') {
            $body['html'] = $this->slot->render($context)->toHtml();
        }

        return new JsonResponse($body);
    }

    /** A field of the form as sent: an empty one or an array says nothing. */
    private static function word(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function context(): CheckoutContext
    {
        $context = $this->contexts->current();

        if ($context->cart === null) {
            throw new NotFoundHttpException;
        }

        return $context;
    }
}
