<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Users\Social\SocialProviders;

/**
 * Outside a local machine there is no such provider, whatever `.env` says.
 */
final class DevProviderElsewhereTest extends TestCase
{
    #[Test]
    public function it_is_not_registered(): void
    {
        config()->set('webx-users.social.keys.dev.enabled', true);

        $this->assertNull($this->app->make(SocialProviders::class)->find('dev'));
        $this->get('/account/auth/dev')->assertNotFound();
        $this->get('/account/auth/dev/pretend')->assertNotFound();
    }
}
