<?php

declare(strict_types=1);

namespace WebxUi\Cart\Rendering;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Routing\Router;
use InvalidArgumentException;
use WebxUi\Cart\Totals\CartState;

/**
 * What the widget's panel holds (§9.2): the lines, the chain of totals and the checkout button, or
 * the empty cart — drawn by the module's views, so a theme changes the markup through the door
 * every module has, and the script only puts the server's HTML in place.
 *
 * The page prints the trigger and an empty panel; this is what `GET /api/cart` adds as `html` when
 * the widget asks with `X-Webx-Cart-View`, and what a page that came back from a form without
 * JavaScript prints open (§9.5). Not on every page: it is the full count.
 */
final class Panel
{
    public const array MODES = ['drawer', 'dialog', 'dropdown'];

    /** The header the widget asks for the panel's HTML with: the mode it is in. */
    public const string VIEW_HEADER = 'X-Webx-Cart-View';

    public function __construct(
        private readonly Views $views,
        private readonly Config $config,
        private readonly Router $router,
        private readonly UrlGenerator $url,
    ) {}

    /** A mode the widget knows; anything else is a typo in a theme, said aloud. */
    public static function mode(?string $mode): string
    {
        if ($mode === null || ! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException('<x-webx-cart mode="'.$mode.'">: one of '.implode(', ', self::MODES).'.');
        }

        return $mode;
    }

    /**
     * `$controls` false — the page that came back from a form without JavaScript: no stepper and
     * no remove button, which only the script can send.
     */
    public function render(CartState $state, string $mode, bool $controls = true): string
    {
        $cart = $state->toArray();

        return $this->views->make('webx-cart::widget', [
            'cart' => $cart,
            'mode' => self::mode($mode),
            'controls' => $controls && $mode !== 'dropdown',
            'checkout' => $this->checkout(),
            'warnings' => self::warnings($cart),
        ])->render();
    }

    /**
     * Where the checkout button leads: the setting, else the site's route named `checkout` — the
     * checkout module will take it — else nowhere, and there is no button.
     */
    public function checkout(): ?string
    {
        $url = $this->config->get('webx-cart.checkout_url');

        if (is_string($url) && $url !== '') {
            return $url;
        }

        return $this->router->has('checkout') ? $this->url->route('checkout') : null;
    }

    /**
     * The warnings by the line they are about; the cart's own under `''`. A price that moved is
     * news rather than a problem (`notice`); the rest is a warning.
     *
     * @param  array<string, mixed>  $cart
     * @return array<string, list<array{message: string, notice: bool}>>
     */
    private static function warnings(array $cart): array
    {
        $by = [];

        foreach (is_array($cart['warnings'] ?? null) ? $cart['warnings'] : [] as $warning) {
            if (! is_array($warning)) {
                continue;
            }

            // The line's own state says these two already: «no longer available», «cannot be bought now».
            if (in_array($warning['code'] ?? null, ['line.gone', 'line.unavailable'], true)) {
                continue;
            }

            $by[(string) ($warning['line'] ?? '')][] = [
                'message' => (string) ($warning['message'] ?? ''),
                'notice' => in_array($warning['code'] ?? null, ['price.down', 'price.up'], true),
            ];
        }

        return $by;
    }
}
