<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Orders\Site\OrderLinks;

/**
 * The addresses of the checkout (spec §19) in the language of the page: behind the language prefix
 * where the site puts it in the path, as the buyer's page of an order does. `checkout` is the name
 * the cart's button looks for.
 */
final readonly class CheckoutLinks
{
    public const string PAGE = 'checkout';

    public const string STEP = 'checkout.steps';

    public const string SUMMARY = 'checkout.summary';

    public const string PLACE = 'checkout.place';

    public const string THANKS = 'checkout.thanks';

    /** Every route of the checkout; `.localised` after each is its copy behind the language prefix. */
    public const array ROUTES = [self::PAGE, self::STEP, self::SUMMARY, self::PLACE, self::THANKS];

    public function __construct(
        private UrlGenerator $url,
        private Router $router,
        private OrderLinks $orders,
    ) {}

    /** @param  array<string, mixed>  $parameters */
    public function to(string $route, string $locale, array $parameters = []): string
    {
        if ($this->orders->prefixed($locale) && $this->router->has($route.'.localised')) {
            return $this->url->route($route.'.localised', [OneSpellingPerAddress::PARAMETER => $locale, ...$parameters]);
        }

        return $this->url->route($route, $parameters);
    }

    public function page(string $locale): string
    {
        return $this->to(self::PAGE, $locale);
    }

    /** «Thank you» for an order (§26), in the order's language. */
    public function thanks(string $number, string $locale): string
    {
        return $this->to(self::THANKS, $locale, ['number' => $number]);
    }

    public function step(string $key, string $locale): string
    {
        return $this->to(self::STEP, $locale, ['step' => $key]);
    }
}
