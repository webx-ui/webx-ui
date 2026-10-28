<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Users\Models\User;
use WebxUi\Users\Social\SocialProviders;

/**
 * The test provider of a machine of development: registered only where `APP_ENV` is `local`,
 * shown only when switched on, and its page leads into the module's own flow.
 */
final class DevProviderTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['env'] = 'local';
    }

    #[Test]
    public function it_is_hidden_until_switched_on(): void
    {
        $this->assertNotNull($this->app->make(SocialProviders::class)->find('dev'));

        $this->get('/account/auth/dev')->assertNotFound();
        $this->get('/account/auth/dev/pretend')->assertNotFound();
    }

    #[Test]
    public function its_page_signs_a_new_visitor_in_through_the_ordinary_callback(): void
    {
        config()->set('webx-users.social.keys.dev.enabled', true);

        $location = (string) $this->get('/account/auth/dev')->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/account/auth/dev/pretend?state=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->get($location)->assertOk()->assertSee('Test sign-in')->assertSee($query['state']);

        $this->post('/account/auth/dev/callback', [
            'state' => $query['state'],
            'email' => 'dev@example.test',
            'first_name' => 'Dev',
            'verified' => '1',
        ])->assertRedirect();

        $user = User::query()->where('email', 'dev@example.test')->sole();
        $this->assertAuthenticatedAs($user, 'site');
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('dev', $user->identities()->sole()->provider);
    }
}
