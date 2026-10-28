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
    /** The cart's main view: where the lines are changed (decision of 28.10.2026). */
    public const array MODES = ['drawer', 'dialog'];

    /** The mini cart under the icon, or none: the icon opens the main view at once. */
    public const array PREVIEWS = ['dropdown', 'none'];

    /** The header the widget asks for the main view's HTML with: its mode. */
    public const string VIEW_HEADER = 'X-Webx-Cart-View';

    /** The header it asks for the mini cart's HTML with (`dropdown`), answered as `preview_html`. */
    public const string PREVIEW_HEADER = 'X-Webx-Cart-Preview';

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

    /** The mini cart the widget knows, the same way. */
    public static function preview(?string $preview): string
    {
        if ($preview === null || ! in_array($preview, self::PREVIEWS, true)) {
            throw new InvalidArgumentException('<x-webx-cart preview="'.$preview.'">: one of '.implode(', ', self::PREVIEWS).'.');
        }

        return $preview;
    }

    /**
     * `$view` — a mode of the main view, or `dropdown` for the mini cart, which has no stepper
     * and no remove button but the button to the main view. `$controls` false — the page that
     * came back from a form without JavaScript: no stepper and no remove button, which only the
     * script can send. `$notes` — what a refused buy button says in the panel, by line id
     * (the cart's own under `''`), as the warnings are.
     *
     * @param  array<string, list<array{message: string, notice: bool}>>  $notes
     */
    public function render(CartState $state, string $view, bool $controls = true, array $notes = []): string
    {
        $cart = $state->toArray();
        $mini = $view === 'dropdown';
        $warnings = self::warnings($cart);

        foreach ($notes as $line => $said) {
            $warnings[$line] = [...$warnings[$line] ?? [], ...$said];
        }

        return $this->views->make('webx-cart::widget', [
            'cart' => $cart,
            'mode' => $mini ? $view : self::mode($view),
            'controls' => $controls && ! $mini,
            'edit' => $mini,
            'checkout' => $this->checkout(),
            'warnings' => $warnings,
        ])->render();
    }

    /**
     * What a refused batch says in the panel: «already the maximum» on each line at its limit,
     * a notice; the rest of the refusal under the cart, a warning.
     *
     * @param  array<string, array<int, string>>  $others
     * @param  array<int, int>  $atLimit
     * @return array<string, list<array{message: string, notice: bool}>>
     */
    public static function notes(array $others, array $atLimit, string $locale): array
    {
        $notes = [];

        foreach (array_unique($atLimit) as $id) {
            $notes[(string) $id][] = ['message' => (string) __('webx-cart::widget.max_reached', [], $locale), 'notice' => true];
        }

        foreach (array_unique(array_merge(...array_values($others))) as $message) {
            $notes[''][] = ['message' => $message, 'notice' => false];
        }

        return $notes;
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
