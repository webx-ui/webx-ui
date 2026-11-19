<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Localization\LocalizationServiceProvider;
use WebxUi\Themes\ThemeServiceProvider;
use WebxUi\Widgets\WidgetsServiceProvider;

/**
 * The panel the API draws speaks the language of the page that asked, not the application's.
 *
 * A site whose application language is English and whose own languages are Russian (the main one,
 * no prefix) and Ukrainian (`/uk/`): the address of the API has no language, so the panel used to
 * come back in English on both pages, and its checkout button led a Ukrainian page to `/checkout`.
 */
final class PageLanguageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withCredentials();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            ThemeServiceProvider::class,
            WidgetsServiceProvider::class,
            CartServiceProvider::class,
        ];
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.locale', 'en');
        $app['config']->set('webx-localization.locales', [['code' => 'ru', 'default' => true], ['code' => 'uk']]);
        $app['config']->set('webx-localization.strategy', 'prefix');
    }

    /** The checkout as the orders module declares it: once plain, once behind the language. */
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('checkout', static fn (): string => 'checkout')->name('checkout');
        $router->get('{'.OneSpellingPerAddress::PARAMETER.'}/checkout', static fn (): string => 'checkout')->name('checkout.localised');
    }

    #[Test]
    public function the_panel_of_a_ukrainian_page_is_ukrainian_and_leads_to_its_checkout(): void
    {
        $response = $this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]], [
            'X-Webx-Locale' => 'uk',
            'X-Webx-Cart-View' => 'drawer',
        ])->assertOk();

        $this->assertSame('uk', $response->json('locale'));
        $html = (string) $response->json('html');
        $this->assertStringContainsString('href="http://localhost/uk/checkout">Оформити замовлення</a>', $html);
        $this->assertStringNotContainsString('Checkout', $html);
    }

    #[Test]
    public function without_the_header_it_is_the_sites_main_language_not_the_applications(): void
    {
        $response = $this->postJson('/api/cart/items', ['items' => [['type' => 'test.product', 'ref' => 1]]], [
            'X-Webx-Cart-View' => 'drawer',
        ])->assertOk();

        $this->assertSame('ru', $response->json('locale'));
        $html = (string) $response->json('html');
        $this->assertStringContainsString('href="http://localhost/checkout">Оформить заказ</a>', $html);
        $this->assertStringNotContainsString('Checkout', $html);
    }

    #[Test]
    public function a_language_the_site_does_not_have_falls_to_the_main_one(): void
    {
        $html = (string) $this->getJson('/api/cart', ['X-Webx-Locale' => 'de', 'X-Webx-Cart-View' => 'drawer'])->assertOk()->json('html');

        $this->assertStringContainsString('Корзина пуста', $html);
    }
}
