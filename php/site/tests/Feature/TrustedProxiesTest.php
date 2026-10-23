<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    private const PUBLIC_PROXY = '203.0.113.10';

    private const VISITOR = '198.51.100.7';

    protected function setUp(): void
    {
        parent::setUp();

        // Outside `web` on purpose: trusting the proxy is global middleware, and the answer has
        // to come out the same without a session or a CSRF token in the way.
        Route::get('/_test/proxy', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
            'url' => url('/cms'),
            'asset' => asset('build/app.js'),
        ]);
        Route::get('/_test/proxy-redirect', fn () => redirect('/cms'));
    }

    protected function tearDown(): void
    {
        $this->setTrustedProxiesEnv(null);

        parent::tearDown();
    }

    public function test_a_proxy_on_a_private_network_makes_links_and_redirects_https(): void
    {
        $this->assertBelieved('10.0.0.5');
        $this->assertBelieved('172.18.0.3');
        $this->assertBelieved('192.168.1.20');
        $this->assertBelieved('127.0.0.1');
    }

    public function test_a_public_address_is_not_believed(): void
    {
        $this->assertNotBelieved(self::PUBLIC_PROXY);
    }

    public function test_trusted_proxies_from_the_env_replace_the_default(): void
    {
        $this->setTrustedProxiesEnv(self::PUBLIC_PROXY.', 10.1.0.0/16');

        $this->assertBelieved(self::PUBLIC_PROXY);
        $this->assertBelieved('10.1.2.3');
        $this->assertNotBelieved('192.168.1.20');
    }

    private function assertBelieved(string $from): void
    {
        $this->assertProbe($from, 'https', self::VISITOR);
    }

    private function assertNotBelieved(string $from): void
    {
        $this->assertProbe($from, 'http', $from);
    }

    private function assertProbe(string $from, string $scheme, string $ip): void
    {
        $probe = $this->fromProxy($from)->getJson($this->plainUrl('/_test/proxy'))->assertOk();

        $this->assertSame($scheme === 'https', $probe->json('secure'), "isSecure() behind {$from}");
        $this->assertSame($ip, $probe->json('ip'), "ip() behind {$from}");
        $this->assertStringStartsWith("{$scheme}://", $probe->json('url'), "url() behind {$from}");
        $this->assertStringStartsWith("{$scheme}://", $probe->json('asset'), "asset() behind {$from}");

        $location = $this->fromProxy($from)->get($this->plainUrl('/_test/proxy-redirect'))->assertRedirect()
            ->headers->get('Location');
        $this->assertStringStartsWith("{$scheme}://", $location, "redirect behind {$from}");
    }

    /**
     * The request itself on plain http: a relative path is resolved against APP_URL, and on a
     * site whose `.env` says https:// the request would be secure before any proxy was asked.
     */
    private function plainUrl(string $path): string
    {
        return 'http://localhost'.$path;
    }

    private function fromProxy(string $address): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $address])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->withHeader('X-Forwarded-For', self::VISITOR);
    }

    /** The env variable as `.env` would give it, and the config file read again over it. */
    private function setTrustedProxiesEnv(?string $value): void
    {
        if ($value === null) {
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        } else {
            $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $value;
        }

        if (isset($this->app)) {
            $this->app['config']->set('trustedproxy', require $this->app->configPath('trustedproxy.php'));
        }
    }
}
