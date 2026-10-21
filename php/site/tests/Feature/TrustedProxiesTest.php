<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Outside `web` on purpose: trusting the proxy is global middleware, and the answer has
        // to come out the same without a session or a CSRF token in the way.
        Route::get('/_test/proxy', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'url' => url('/cms'),
            'asset' => asset('build/app.js'),
        ]);
        Route::get('/_test/proxy-redirect', fn () => redirect('/cms'));
    }

    public function test_a_proxy_on_a_private_network_makes_links_and_redirects_https(): void
    {
        $this->assertScheme('https', '10.0.0.5');
        $this->assertScheme('https', '172.18.0.3');
        $this->assertScheme('https', '192.168.1.20');
    }

    public function test_a_public_address_is_not_believed(): void
    {
        $this->assertScheme('http', '203.0.113.10');
    }

    private function assertScheme(string $scheme, string $from): void
    {
        $probe = $this->fromProxy($from)->getJson('/_test/proxy')->assertOk();

        $this->assertSame($scheme === 'https', $probe->json('secure'), "isSecure() behind {$from}");
        $this->assertStringStartsWith("{$scheme}://", $probe->json('url'), "url() behind {$from}");
        $this->assertStringStartsWith("{$scheme}://", $probe->json('asset'), "asset() behind {$from}");

        $location = $this->fromProxy($from)->get('/_test/proxy-redirect')->assertRedirect()
            ->headers->get('Location');
        $this->assertStringStartsWith("{$scheme}://", $location, "redirect behind {$from}");
    }

    private function fromProxy(string $address): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $address])
            ->withHeader('X-Forwarded-Proto', 'https');
    }
}
