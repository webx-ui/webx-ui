<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Localization\Contracts\PageInLanguage;
use WebxUi\Localization\Doctor\FirstVisitCheck;
use WebxUi\Localization\NoPageInLanguage;

/**
 * ROUTING §18: the first visit of a person to a page in the default language is sent, once, to
 * the same page in the chosen one; the cookie remembers it, the switcher's choice is stronger,
 * robots and everything that is not a page somebody reads are left alone.
 */
final class FirstVisitTest extends TestCase
{
    private const string BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'https://shop.test');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('webx-admin.path', 'cms');
        $app['config']->set('webx-localization.locales', [
            ['code' => 'ru', 'default' => true],
            ['code' => 'uk'],
        ]);
        $app['config']->set('webx-localization.first_visit.locale', 'uk');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The same page in the other language: the path under its prefix — except one page that
        // has nothing there but the language's home page.
        $this->app->instance(PageInLanguage::class, new class implements PageInLanguage
        {
            public function url(Request $request, string $locale): ?string
            {
                $path = trim($request->path(), '/');

                if ($path === 'only-home' || str_starts_with($path, $locale.'/') || $path === $locale) {
                    return null;
                }

                return 'https://shop.test/'.$locale.($path === '' ? '' : '/'.$path);
            }
        });

        Route::middleware(['webx.locale'])->group(function (): void {
            Route::post('form', fn (): string => 'sent');
            Route::get('json', fn (): array => ['locale' => app()->getLocale()]);
            Route::get('missing', fn () => abort(404));
            Route::get('moved', fn () => redirect('/elsewhere', 301));
            Route::get('file.txt', fn () => response('text', 200, ['Content-Type' => 'text/plain']));
            Route::get('{path?}', fn (): string => '<p>'.app()->getLocale().'</p>')->where('path', '.*');
        });
    }

    #[Test]
    public function the_first_visit_of_a_person_goes_to_the_same_page_in_the_chosen_language(): void
    {
        $response = $this->page('/cameras/dome?page=2&utm_source=ad');

        $response->assertStatus(302);
        $response->assertRedirect('https://shop.test/uk/cameras/dome?page=2&utm_source=ad');
        $response->assertCookie('webx_lang', 'uk', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $cookie = $this->cookieOf($response);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertGreaterThan(time() + 360 * 86400, $cookie->getExpiresTime());
    }

    #[Test]
    public function the_home_page_goes_to_the_home_page_of_the_language(): void
    {
        $this->page('/')->assertRedirect('https://shop.test/uk');
    }

    #[Test]
    public function with_the_cookie_nobody_is_sent_anywhere_again(): void
    {
        // Whatever it says: somebody who chose the default language stays on it.
        foreach (['uk', 'ru', 'anything'] as $value) {
            $response = $this->withUnencryptedCookie('webx_lang', $value)->page('/cameras');

            $response->assertOk()->assertSee('<p>ru</p>', false);
            $this->assertNull($this->cookieOf($response, required: false));
        }
    }

    #[Test]
    public function a_page_already_in_the_language_is_left_alone(): void
    {
        $response = $this->page('/uk/cameras');

        $response->assertOk()->assertSee('<p>uk</p>', false);
        $this->assertNull($this->cookieOf($response, required: false));
    }

    #[Test]
    public function a_page_with_nothing_but_the_home_page_in_the_language_is_left_alone(): void
    {
        $response = $this->page('/only-home');

        $response->assertOk();
        $this->assertNull($this->cookieOf($response, required: false));
    }

    /** @return iterable<string, array{string}> */
    public static function robots(): iterable
    {
        yield 'Googlebot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
        yield 'Google-InspectionTool' => ['Mozilla/5.0 (compatible; Google-InspectionTool/1.0;)'];
        yield 'AdsBot' => ['AdsBot-Google (+http://www.google.com/adsbot.html)'];
        yield 'Storebot' => ['Mozilla/5.0 (X11; Linux x86_64; Storebot-Google/1.0) AppleWebKit/537.36'];
        yield 'Bing' => ['Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'];
        yield 'Yandex' => ['Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)'];
        yield 'link preview' => ['TelegramBot (like TwitterBot)'];
        yield 'facebook' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'];
        yield 'SEO' => ['Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'];
        yield 'AI' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)'];
        yield 'the site audit' => ['WebxAudit/1.0'];
        yield 'some crawler' => ['ExampleCrawler 2.0'];
        yield 'no agent' => [''];
    }

    #[Test]
    #[DataProvider('robots')]
    public function a_robot_sees_every_language_at_its_own_address(string $agent): void
    {
        $response = $this->withHeaders(['User-Agent' => $agent, 'Accept' => 'text/html'])->get('/cameras');

        $response->assertOk()->assertSee('<p>ru</p>', false);
        $this->assertNull($this->cookieOf($response, required: false));
    }

    #[Test]
    public function a_phone_whose_name_has_bot_in_it_is_a_person(): void
    {
        $agent = 'Mozilla/5.0 (Linux; Android 12; Cubot KingKong 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36';

        $this->withHeaders(['User-Agent' => $agent, 'Accept' => 'text/html'])->get('/cameras')
            ->assertRedirect('https://shop.test/uk/cameras');
    }

    #[Test]
    public function what_is_not_a_page_somebody_opens_is_left_alone(): void
    {
        $headers = ['User-Agent' => self::BROWSER, 'Accept' => 'text/html'];

        $this->assertLeftAlone($this->withHeaders($headers)->post('/form'));
        $this->assertLeftAlone($this->withHeaders(['User-Agent' => self::BROWSER])->getJson('/cameras'));
        $this->assertLeftAlone($this->withHeaders(['User-Agent' => self::BROWSER, 'Accept' => '*/*'])->get('/cameras'));
        $this->assertLeftAlone($this->withHeaders($headers + ['X-Requested-With' => 'XMLHttpRequest'])->get('/cameras'));
        $this->assertLeftAlone($this->withHeaders($headers + ['Sec-Purpose' => 'prefetch;prerender'])->get('/cameras'));
        $this->assertLeftAlone($this->withHeaders($headers + ['Purpose' => 'prefetch'])->get('/cameras'));
    }

    #[Test]
    public function the_panel_and_the_listed_paths_are_left_alone(): void
    {
        foreach (['/cms', '/cms/pages', '/api/products', '/sitemap.xml', '/sitemap-products.xml', '/robots.txt', '/feeds/merchant.xml', '/.well-known/security.txt'] as $path) {
            $this->assertLeftAlone($this->page($path), $path);
        }
    }

    #[Test]
    public function a_parameter_of_the_skip_list_leaves_the_request_alone(): void
    {
        foreach (['no_redirect', 'preview', 'signature'] as $parameter) {
            $this->assertLeftAlone($this->page('/cameras?'.$parameter.'=1'), $parameter);
        }
    }

    #[Test]
    public function an_answer_that_is_not_a_page_is_passed_on_as_it_is(): void
    {
        $this->assertLeftAlone($this->page('/missing'), 'missing', 404);
        $this->assertLeftAlone($this->page('/moved'), 'moved', 301);
        $this->assertLeftAlone($this->page('/json'), 'json');
        $this->assertLeftAlone($this->page('/file.txt'), 'file');
    }

    #[Test]
    public function the_switcher_s_choice_is_kept_and_its_parameter_dropped(): void
    {
        $response = $this->page('/uk/cameras?webx_lang=ru&page=2');

        $response->assertRedirect('https://shop.test/uk/cameras?page=2');
        $response->assertCookie('webx_lang', 'ru', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Back on the default language with the cookie: nothing sends the person away again.
        $this->withUnencryptedCookie('webx_lang', 'ru')->page('/cameras')->assertOk()->assertSee('<p>ru</p>', false);
    }

    #[Test]
    public function a_choice_of_a_language_the_site_does_not_have_keeps_nothing(): void
    {
        $response = $this->page('/cameras?webx_lang=de');

        $response->assertRedirect('https://shop.test/cameras');
        $this->assertNull($this->cookieOf($response, required: false));
    }

    #[Test]
    public function a_robot_that_followed_the_switcher_only_loses_the_parameter(): void
    {
        $response = $this->withHeaders(['User-Agent' => 'Googlebot/2.1', 'Accept' => 'text/html'])->get('https://shop.test/uk/cameras?webx_lang=ru');

        $response->assertRedirect('https://shop.test/uk/cameras');
    }

    #[Test]
    public function the_language_can_be_the_browser_s(): void
    {
        config()->set('webx-localization.first_visit.locale', 'header');

        $this->page('/cameras', ['Accept-Language' => 'uk-UA,uk;q=0.9,en;q=0.8'])
            ->assertRedirect('https://shop.test/uk/cameras');

        // A browser in the default language is already where it wants to be.
        $this->assertLeftAlone($this->page('/cameras', ['Accept-Language' => 'ru-RU,ru;q=0.9']), 'ru');

        // A language the site does not have: served as it is, and nothing remembered.
        $this->assertLeftAlone($this->page('/cameras', ['Accept-Language' => 'de-DE,de;q=0.9']), 'de');
    }

    #[Test]
    public function switched_off_nothing_changes(): void
    {
        config()->set('webx-localization.first_visit.locale', null);

        $this->assertLeftAlone($this->page('/cameras'));

        // Not even the parameter: a site that never turned this on has no such links.
        $response = $this->page('/cameras?webx_lang=uk');
        $response->assertOk();
        $this->assertNull($this->cookieOf($response, required: false));
    }

    #[Test]
    public function with_one_address_for_every_language_it_stays_silent(): void
    {
        config()->set('webx-localization.strategy', 'header');

        $this->assertLeftAlone($this->page('/cameras'));
    }

    #[Test]
    public function a_language_the_site_does_not_have_switches_it_off(): void
    {
        config()->set('webx-localization.first_visit.locale', 'de');

        $this->assertLeftAlone($this->page('/cameras'));
    }

    #[Test]
    public function without_the_addresses_of_the_site_nobody_is_sent_anywhere(): void
    {
        $this->app->instance(PageInLanguage::class, new NoPageInLanguage);

        $this->assertLeftAlone($this->page('/cameras'));
    }

    #[Test]
    public function the_cookie_is_not_encrypted(): void
    {
        $this->assertTrue(app(EncryptCookies::class)->isDisabled('webx_lang'));
    }

    #[Test]
    public function the_doctor_says_why_it_cannot_work(): void
    {
        // A fake that knows the addresses, a language the site has: nothing to say.
        $this->assertCount(0, $this->doctor());

        config()->set('webx-localization.first_visit.locale', null);
        $this->assertCount(0, $this->doctor());

        config()->set('webx-localization.first_visit.locale', 'de');
        config()->set('webx-localization.strategy', 'header');
        $this->app->instance(PageInLanguage::class, new NoPageInLanguage);

        $found = $this->doctor();
        $this->assertCount(3, $found);
        $this->assertStringContainsString('not `prefix`', $found[0]);
        $this->assertStringContainsString('WEBX_LOCALE_FIRST_VISIT=de', $found[1]);
        $this->assertStringContainsString('webx-ui/widgets', $found[2]);
    }

    /** @return list<string> What `webx:doctor` says about the first visit. */
    private function doctor(): array
    {
        return array_map(
            static fn (Diagnosis $diagnosis): string => $diagnosis->detail,
            app(FirstVisitCheck::class)->run(),
        );
    }

    /**
     * A person's browser asking for a page.
     *
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    private function page(string $uri, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers + [
            'User-Agent' => self::BROWSER,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ])->get('https://shop.test'.$uri);
    }

    /** @param TestResponse<Response> $response */
    private function assertLeftAlone(TestResponse $response, string $case = '', int $status = 200): void
    {
        $this->assertSame($status, $response->getStatusCode(), $case);
        $this->assertNull($this->cookieOf($response, required: false), $case);
    }

    /** @param TestResponse<Response> $response */
    private function cookieOf(TestResponse $response, bool $required = true): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'webx_lang') {
                return $cookie;
            }
        }

        $this->assertFalse($required, 'No webx_lang cookie.');

        return null;
    }
}
