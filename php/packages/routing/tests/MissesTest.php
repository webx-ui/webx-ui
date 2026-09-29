<?php

declare(strict_types=1);

namespace WebxUi\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Routing\Misses;
use WebxUi\Routing\Tests\Fixtures\FormerAddresses;

/**
 * What a module says about an address the registry holds no row for (§8, step 7): asked only
 * when nothing matched, in the order registered, and a 404 when nobody answers.
 */
class MissesTest extends TestCase
{
    #[Test]
    public function a_miss_is_asked_only_when_the_registry_has_nothing(): void
    {
        $this->app->make(Misses::class)->register(FormerAddresses::class);
        $this->page('about');

        $this->get('/about')->assertOk();
        $this->get('/old-about')->assertStatus(301)->assertRedirect('/about');
        $this->get('/nothing-here')->assertNotFound();
    }
}
