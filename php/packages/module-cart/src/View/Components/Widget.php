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
 * `<x-webx-cart />`, `<x-webx-cart mode="dropdown" />` — the cart's trigger with its counter and
 * the panel it opens (§9.1): a drawer (`<x-webx-dialog drawer>` of the widgets), a dialog, or a
 * dropdown under the icon. The mode is the theme's: a prop in its header, else
 * `webx-cart.widget.mode`.
 *
 * The page prints the trigger, the counter from `cart()` and an empty panel; the script fills the
 * panel from `GET /api/cart` when it opens. A page that came back from a buy button without
 * JavaScript (the `webx-cart` flash) prints the panel open and full instead (§9.5).
 */
final class Widget extends Component
{
    /** The panel's id: one cart on a page, and a copy of it (the header's mobile menu) is the same one. */
    public const string ID = 'webx-cart';

    public string $resolvedMode;

    public function __construct(
        private readonly CartHandle $cart,
        private readonly Panel $panel,
        private readonly Assets $assets,
        private readonly Request $request,
        private readonly Router $router,
        private readonly Views $views,
        Config $config,
        ?string $mode = null,
        public ?string $title = null,
    ) {
        $configured = $config->get('webx-cart.widget.mode');
        $this->resolvedMode = Panel::mode($mode ?? (is_string($configured) ? $configured : 'drawer'));
    }

    public function render(): View
    {
        $returned = $this->request->hasSession() && $this->request->session()->has(CartController::FLASH);

        return $this->views->make('webx-cart::cart', [
            'mode' => $this->resolvedMode,
            'id' => self::ID,
            'heading' => $this->title ?? __('webx-cart::widget.title'),
            'count' => $this->cart->count(),
            'returned' => $returned,
            'content' => $returned ? $this->panel->render($this->cart->state(), $this->resolvedMode, controls: false) : null,
            'api' => rtrim($this->request->getBasePath().'/'.trim((string) config('webx-cart.path', 'api/cart'), '/'), '/'),
            'csrf' => $this->router->has('webx-widgets.csrf') ? route('webx-widgets.csrf', [], false) : null,
            'script' => $this->assets->script(),
        ]);
    }
}
