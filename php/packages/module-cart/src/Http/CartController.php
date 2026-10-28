<?php

declare(strict_types=1);

namespace WebxUi\Cart\Http;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\CartRefusal;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Rendering\Panel;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;

/**
 * The five doors of the API (§6.1). Every answer is the whole state of the cart — the widget
 * redraws from it and never keeps its own copy — and `POST` adds what it wrote, `added`.
 *
 * A request that does not ask for JSON is a form without JavaScript (`<x-webx-cart::add>`): it
 * goes back to its page with the outcome in the `webx-cart` flash, as every form of the site
 * does (FormResponse, §17.4 of the widgets spec); a refusal goes back with the errors, as
 * Laravel answers any form.
 *
 * The widget asks with `X-Webx-Cart-View: drawer|dialog` and gets the main view's panel as `html`
 * beside the state, with `X-Webx-Cart-Preview: dropdown` the mini cart's as `preview_html`
 * (§9.2); without the headers there is none.
 *
 * A refused batch into a cart that is there is answered with the cart too (422, the errors and the
 * state with its panels): a buy button opens the cart and says it there — on the line already at
 * its maximum as a notice, nothing else changed (decision of 28.10.2026). A form without
 * JavaScript at the maximum goes back with the cart open and the same notice.
 */
final class CartController
{
    /** The session key a form without JavaScript gets its outcome back under. */
    public const string FLASH = 'webx-cart';

    public function __construct(
        private readonly CartService $service,
        private readonly CartHandle $handle,
        private readonly CartCookie $cookie,
        private readonly Calculator $calculator,
        private readonly Panel $panel,
    ) {}

    /** The state; without a cart an empty one, and nothing is written. */
    public function show(Request $request): JsonResponse|RedirectResponse
    {
        return $this->answer($request, $this->handle->cart(), $this->handle->locale());
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $items = $request->input('items');
        $locale = $this->handle->locale();
        $cart = $this->handle->cart();

        try {
            $result = $this->service->add($cart, is_array($items) ? array_values($items) : [], $locale, $this->handle->owner());
        } catch (ValidationException $refusal) {
            return $cart !== null ? $this->refused($request, $refusal, $cart, $locale) : throw $refusal;
        }

        $cookie = $result->token !== null ? $this->cookie->make($result->token, $request) : null;

        return $this->answer($request, $result->cart, $locale, ['added' => $result->added], $cookie);
    }

    public function update(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $cart = $this->cart();
        $locale = $this->handle->locale();
        $qty = $request->input('qty');
        $options = $request->input('options');

        if ($qty !== null && filter_var($qty, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['qty' => [(string) __('webx-cart::errors.qty', ['max' => (int) config('webx-cart.qty_max', 999)], $locale)]]);
        }

        if ($options !== null && ! is_array($options)) {
            throw ValidationException::withMessages(['options' => [(string) __('webx-cart::errors.options', [], $locale)]]);
        }

        /** @var array<string, mixed>|null $options */
        $this->service->update($cart, (int) $id, $qty === null ? null : (int) $qty, $options, $locale);

        return $this->answer($request, $cart, $locale);
    }

    /** A stored line by its number; a generated one by its key is declined (§6.4). */
    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $cart = $this->cart();
        $locale = $this->handle->locale();

        if (ctype_digit($id)) {
            $this->service->remove($cart, (int) $id);
        } else {
            $this->service->decline($cart, $id, $locale);
        }

        return $this->answer($request, $cart, $locale);
    }

    /** The lines, the declined rules and the cookie. */
    public function clear(Request $request): JsonResponse|RedirectResponse
    {
        $cart = $this->handle->cart();

        if ($cart !== null) {
            $this->service->clear($cart);
        }

        return $this->answer($request, null, $this->handle->locale(), [], $this->cookie->forget());
    }

    /** A refused batch, answered with the cart it was refused into and the notes for its panel. */
    private function refused(Request $request, ValidationException $refusal, Cart $cart, string $locale): JsonResponse|RedirectResponse
    {
        $atLimit = $refusal instanceof CartRefusal ? $refusal->atLimit : [];
        $others = $refusal instanceof CartRefusal ? $refusal->others() : $refusal->errors();
        $notes = Panel::notes($others, $atLimit, $locale);

        if (! $request->expectsJson()) {
            if ($atLimit === [] || $others !== []) {
                throw $refusal;
            }

            return Redirect::back()->with(self::FLASH, ['notes' => $notes]);
        }

        $state = $this->calculator->calculate($cart, $locale);

        return new JsonResponse([
            'message' => $refusal->getMessage(),
            'errors' => $refusal->errors(),
            ...$state->toArray(),
            ...$this->html($request, $state, $notes),
        ], $refusal->status);
    }

    /**
     * The panels the widget asked for by its headers.
     *
     * @param  array<string, list<array{message: string, notice: bool}>>  $notes
     * @return array<string, string>
     */
    private function html(Request $request, CartState $state, array $notes = []): array
    {
        $view = $request->header(Panel::VIEW_HEADER);
        $html = [];

        if (is_string($view) && in_array($view, Panel::MODES, true)) {
            $html['html'] = $this->panel->render($state, $view, notes: $notes);
        }

        if ($request->header(Panel::PREVIEW_HEADER) === 'dropdown') {
            $html['preview_html'] = $this->panel->render($state, 'dropdown', notes: $notes);
        }

        return $html;
    }

    /** The visitor's cart, or 404: no cart has no line to change. */
    private function cart(): Cart
    {
        return $this->handle->cart() ?? throw (new ModelNotFoundException)->setModel(CartItem::class);
    }

    /** @param  array<string, mixed>  $extra */
    private function answer(Request $request, ?Cart $cart, string $locale, array $extra = [], ?Cookie $cookie = null): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            $response = Redirect::back()->with(self::FLASH, $extra);
        } else {
            // The lines are read afresh: the service wrote through the same model.
            $state = $cart !== null ? $this->calculator->calculate($cart, $locale) : $this->calculator->empty($locale);
            $response = new JsonResponse([...$state->toArray(), ...$extra, ...$this->html($request, $state)]);
        }

        return $cookie !== null ? $response->withCookie($cookie) : $response;
    }
}
