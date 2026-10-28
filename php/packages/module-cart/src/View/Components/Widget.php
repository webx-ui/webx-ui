<?php

declare(strict_types=1);

namespace WebxUi\Cart\View\Components;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\View\Component;
use WebxUi\Cart\CartHandle;
use WebxUi\Cart\Http\CartController;
use WebxUi\Cart\Rendering\Assets;
use WebxUi\Cart\Rendering\Panel;

/**
 * `<x-webx-cart />`, `<x-webx-cart mode="dialog" preview="none" />` — the cart's trigger with its
 * counter, the main view and the mini cart (§9.1, decision of 28.10.2026). The main view is where
 * the lines change: a drawer (`<x-webx-dialog drawer>` of the widgets) or a dialog. The mini cart
 * is a dropdown under the icon — the lines, the total, «Edit cart» to the main view and the
 * checkout —, or none, and the icon opens the main view at once. Both are the theme's: props in
 * its header, else `webx-cart.widget.mode` and `webx-cart.widget.preview`.
 *
 * The page prints the trigger, the counter from `cart()` and empty panels; the script fills them
 * from `GET /api/cart` when one opens. A page that came back from a buy button without
 * JavaScript (the `webx-cart` flash) prints the main view open and full instead (§9.5), with the
 * notes of a refusal when the item was already there at its maximum.
 */
final class Widget extends Component
{
    /** The panel's id: one cart on a page, and a copy of it (the header's mobile menu) is the same one. */
    public const string ID = 'webx-cart';

    public string $resolvedMode;

    public string $resolvedPreview;

    public function __construct(
        private readonly CartHandle $cart,
        private readonly Panel $panel,
        private readonly Assets $assets,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Views $views,
        Config $config,
        ?string $mode = null,
        ?string $preview = null,
        public ?string $title = null,
    ) {
        $configured = $config->get('webx-cart.widget.mode');
        $this->resolvedMode = Panel::mode($mode ?? (is_string($configured) ? $configured : 'drawer'));
        $configured = $config->get('webx-cart.widget.preview');
        $this->resolvedPreview = Panel::preview($preview ?? (is_string($configured) ? $configured : 'dropdown'));
    }

    public function render(): View
    {
        $flash = $this->request->hasSession() ? $this->request->session()->get(CartController::FLASH) : null;
        $returned = $this->request->hasSession() && $this->request->session()->has(CartController::FLASH);
        /** @var array<string, list<array{message: string, notice: bool}>> $notes */
        $notes = is_array($flash) && is_array($flash['notes'] ?? null) ? $flash['notes'] : [];

        return $this->views->make('webx-cart::cart', [
            'mode' => $this->resolvedMode,
            'preview' => $this->resolvedPreview,
            'id' => self::ID,
            'heading' => $this->title ?? __('webx-cart::widget.title'),
            'count' => $this->cart->count(),
            'returned' => $returned,
            'content' => $returned ? $this->panel->render($this->cart->state(), $this->resolvedMode, controls: false, notes: $notes) : null,
            'api' => rtrim($this->request->getBasePath().'/'.trim((string) config('webx-cart.path', 'api/cart'), '/'), '/'),
            'csrf' => $this->router->has('webx-widgets.csrf') ? route('webx-widgets.csrf', [], false) : null,
            'script' => $this->assets->script(),
        ]);
    }
}
