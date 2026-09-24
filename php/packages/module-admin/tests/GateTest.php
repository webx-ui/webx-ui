<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Checks\SiteGate;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Admin\Gate\CloseSite;
use WebxUi\Admin\Gate\Credentials;
use WebxUi\Admin\Gate\Openings;

final class GateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/about', static fn (): string => 'About us');
        Route::get('/promo/spring', static fn (): string => 'Spring');
    }

    #[Test]
    public function it_is_global_middleware_and_off_until_the_environment_says_otherwise(): void
    {
        $kernel = $this->app->make(HttpKernel::class);
        $this->assertInstanceOf(Kernel::class, $kernel);
        $this->assertTrue($kernel->hasMiddleware(CloseSite::class));

        $this->get('/about')->assertOk()->assertSee('About us');
    }

    #[Test]
    public function a_closed_site_asks_for_the_pair_and_takes_only_a_right_one(): void
    {
        $this->close('client:s3cret:with-colon, tester:other');

        $refused = $this->get('/about');
        $refused->assertStatus(401);
        $refused->assertHeader('WWW-Authenticate', 'Basic realm="Laravel", charset="UTF-8"');
        $refused->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $refused->assertHeader('Cache-Control', 'no-store, private');
        $refused->assertHeader('Vary', 'Authorization');
        $refused->assertSee('This site is not open yet');
        $refused->assertDontSee('About us');

        $this->withBasic('client', 'wrong')->get('/about')->assertStatus(401);
        $this->withBasic('nobody', 'other')->get('/about')->assertStatus(401);

        $passed = $this->withBasic('client', 's3cret:with-colon')->get('/about');
        $passed->assertOk()->assertSee('About us');
        $passed->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertContains('Authorization', array_map('trim', explode(',', (string) $passed->headers->get('Vary'))));

        $this->withBasic('tester', 'other')->get('/about')->assertOk();
    }

    #[Test]
    public function an_address_with_no_route_is_behind_the_password_too(): void
    {
        // The case a gate in the `web` group gets wrong: the router throws before any group runs.
        $this->close('client:secret');

        $this->get('/no-such-page')->assertStatus(401);
        $this->withBasic('client', 'secret')->get('/no-such-page')->assertNotFound();
    }

    #[Test]
    public function the_panel_its_json_and_well_known_are_not(): void
    {
        $this->close('client:secret');

        Route::get('/api/cms/ping', static fn (): string => 'pong');
        Route::get('/.well-known/acme-challenge/token', static fn (): string => 'challenge');

        $this->get('/cms')->assertOk();
        $this->get('/cms/pages/1')->assertOk();
        $this->get('/api/cms/ping')->assertOk()->assertSee('pong');
        $this->get('/.well-known/acme-challenge/token')->assertOk();

        // A prefix is a path segment, not the start of a word.
        Route::get('/cmsx', static fn (): string => 'not the panel');
        $this->get('/cmsx')->assertStatus(401);
    }

    #[Test]
    public function the_site_and_other_packages_open_what_they_name(): void
    {
        $this->close('client:secret');
        $this->app['config']->set('webx-admin.gate.except', ['promo/*']);

        $this->get('/promo/spring')->assertOk();
        $this->get('/about')->assertStatus(401);

        $this->app->make(Openings::class)->allow(static fn (Request $request): bool => $request->path() === 'about');

        $this->get('/about')->assertOk();
    }

    #[Test]
    public function switched_on_with_nobody_named_it_lets_nobody_in(): void
    {
        $this->close('');

        $this->get('/about')->assertStatus(401);
        $this->withBasic('', '')->get('/about')->assertStatus(401);
        $this->withBasic('client', ':')->get('/about')->assertStatus(401);

        $this->assertSame([], $this->app->make(Credentials::class)->pairs());
    }

    #[Test]
    public function the_doctor_says_when_it_is_on_and_when_it_cannot_be(): void
    {
        $this->assertDiagnosis(null);

        $this->close('');
        $this->assertDiagnosis(Diagnosis::FAIL, 'lets no one in');

        $this->close('client:secret');
        $this->assertDiagnosis(Diagnosis::OK, '1 pair');

        // A config published before the gate existed has no `gate` key, and the switch is dead.
        // Nothing to say to a site that never asked for one — its `--strict` deploys go on.
        $config = $this->app['config']->get('webx-admin');
        unset($config['gate']);
        $this->app['config']->set('webx-admin', $config);

        $this->assertDiagnosis(null);
        $this->get('/about')->assertOk();

        // The site that asked, and believes it is closed, is told it is not.
        putenv('WEBX_SITE_GATE=true');

        try {
            $this->assertDiagnosis(Diagnosis::FAIL, 'open to everyone');
        } finally {
            putenv('WEBX_SITE_GATE');
        }
    }

    private function assertDiagnosis(?string $state, string $saying = ''): void
    {
        $found = $this->app->make(SiteGate::class)->run();

        if ($state === null) {
            $this->assertSame([], $found);

            return;
        }

        $this->assertCount(1, $found);
        $this->assertSame($state, $found[0]->state);
        $this->assertStringContainsString($saying, $found[0]->detail);
    }

    private function close(string $users): void
    {
        $this->app['config']->set('webx-admin.gate.enabled', true);
        $this->app['config']->set('webx-admin.gate.users', $users);
    }

    private function withBasic(string $name, string $password): static
    {
        return $this->withHeader('Authorization', 'Basic '.base64_encode($name.':'.$password));
    }
}
