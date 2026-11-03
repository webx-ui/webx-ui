<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator as Urls;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Payments\Models\Payment;

/**
 * The addresses of paying (spec §4): the order's page of payment behind a signed link without an
 * end, like the order's own page — in the order's language, behind its prefix where the site puts
 * the language in the path; the step to the gateway by the attempt's uuid; the way back and the
 * gateway's notifications, absolute, for the gateway.
 */
final readonly class PaymentLinks
{
    public const string PAGE = 'webx.payments.pay';

    public const string ATTEMPTS = 'webx.payments.attempts';

    public const string METHOD = 'webx.payments.method';

    public const string GO = 'webx.payments.go';

    public const string RETURN = 'webx.payments.return';

    public const string NOTIFY = 'webx.payments.notify';

    public function __construct(
        private UrlGenerator $url,
        private Router $router,
        private Config $config,
        private OrderLinks $orders,
    ) {}

    /** The signed page of payment of an order, or empty while the site has none. */
    public function page(Order $order): string
    {
        return $this->signed(self::PAGE, $order);
    }

    /** Where the page's «Pay» posts: signed like the page. */
    public function attempts(Order $order): string
    {
        return $this->signed(self::ATTEMPTS, $order);
    }

    /** Where «Pay online now» posts another method (§8.5): signed like the page. */
    public function method(Order $order): string
    {
        return $this->signed(self::METHOD, $order);
    }

    /** The step to the gateway (§8.2): the uuid is the key, nothing else is in the address. */
    public function go(Payment $payment): string
    {
        return $this->url->route(self::GO, ['uuid' => $payment->uuid]);
    }

    /** Where the gateway sends the buyer back (§8.3), success and failure alike. */
    public function returnUrl(Payment $payment): string
    {
        return $this->router->has(self::RETURN)
            ? $this->url->route(self::RETURN, ['uuid' => $payment->uuid])
            : $this->url->to($this->path().'/return/'.$payment->uuid);
    }

    /** Where the gateway posts its notifications for a method (§9.2): no language, no session. */
    public function notifyUrl(string $methodKey): string
    {
        return $this->router->has(self::NOTIFY)
            ? $this->url->route(self::NOTIFY, ['method' => $methodKey])
            : $this->url->to('payments/notify/'.rawurlencode($methodKey));
    }

    public function path(): string
    {
        return trim((string) $this->config->get('webx-payments.path', 'pay'), '/');
    }

    private function signed(string $route, Order $order): string
    {
        if (! $this->router->has($route)) {
            return '';
        }

        /** @var Urls $url */
        $url = $this->url;
        $number = (string) $order->number;

        if ($this->orders->prefixed((string) $order->locale) && $this->router->has($route.'.localised')) {
            return $url->signedRoute($route.'.localised', [OneSpellingPerAddress::PARAMETER => (string) $order->locale, 'number' => $number]);
        }

        return $url->signedRoute($route, ['number' => $number]);
    }
}
