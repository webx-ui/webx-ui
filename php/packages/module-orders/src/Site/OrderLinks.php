<?php

declare(strict_types=1);

namespace WebxUi\Orders\Site;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator as Urls;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Localization\Locales;

/**
 * Where the buyer reads an order (spec §13.1): a signed link without an end — the letters, the
 * «Thank you» page of the checkout — in the language of the order, behind its prefix where the
 * site puts the language in the path. The signature is `APP_KEY`'s: a new key breaks the old
 * links, which is accepted (decision 32).
 */
final readonly class OrderLinks
{
    /** The page's route; `.localised` after it is its copy behind the language prefix. */
    public const string PAGE = 'webx.orders.order';

    /** «My orders» of the account, when `webx-ui/module-users` is installed (§13.2). */
    public const string ACCOUNT = 'webx.orders.account.index';

    public const string ACCOUNT_ORDER = 'webx.orders.account.show';

    public function __construct(
        private UrlGenerator $url,
        private Router $router,
        private Config $config,
        private Locales $locales,
    ) {}

    /** The signed page of the order, or empty while the site has no such page. */
    public function page(string $number, string $locale): string
    {
        if (! $this->router->has(self::PAGE)) {
            return '';
        }

        /** @var Urls $url */
        $url = $this->url;

        if ($this->prefixed($locale) && $this->router->has(self::PAGE.'.localised')) {
            return $url->signedRoute(self::PAGE.'.localised', [OneSpellingPerAddress::PARAMETER => $locale, 'number' => $number]);
        }

        return $url->signedRoute(self::PAGE, ['number' => $number]);
    }

    /** Whether an address in this language carries it in front — the rule of `OneSpellingPerAddress`. */
    private function prefixed(string $locale): bool
    {
        if ((string) $this->config->get('webx-localization.strategy', 'prefix') !== 'prefix' || ! $this->locales->has($locale)) {
            return false;
        }

        return $locale !== $this->locales->defaultCode() || (bool) $this->config->get('webx-localization.prefix_default', false);
    }
}
