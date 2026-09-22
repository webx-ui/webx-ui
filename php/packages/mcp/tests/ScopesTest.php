<?php

declare(strict_types=1);

namespace WebxUi\Mcp\Tests;

use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Mcp\Scopes;
use WebxUi\Mcp\Tests\Fixtures\PassportUser;
use WebxUi\Mcp\Tests\Fixtures\TokenUser;

/**
 * What a token is allowed to reach.
 *
 * Every test here that passes has a twin that refuses. A gate of this kind fails silently in
 * one direction only — it starts saying yes to everything, and every call still works — so
 * proving the yes proves nothing on its own.
 */
final class ScopesTest extends TestCase
{
    #[Test]
    public function a_key_naming_module_scopes_is_read_scope_by_scope(): void
    {
        $reader = new TokenUser(['seo:read']);

        $this->assertTrue(Scopes::allows($reader, 'seo:read'));
        $this->assertFalse(Scopes::allows($reader, 'seo:write'));
        $this->assertFalse(Scopes::allows($reader, 'media:read'));
    }

    #[Test]
    public function an_oauth_token_carries_one_scope_for_the_whole_server(): void
    {
        // What a client is actually given: `laravel/mcp` offers `mcp:use` and nothing
        // module-shaped, so reading module scopes off such a token would refuse every call.
        $agent = PassportUser::bearing([Scopes::OAUTH]);

        $this->assertTrue(Scopes::allows($agent, 'seo:read'));
        $this->assertTrue(Scopes::allows($agent, 'seo:write'));
    }

    #[Test]
    public function a_passport_token_without_the_scope_is_refused(): void
    {
        $reader = PassportUser::bearing(['seo:read']);

        $this->assertTrue(Scopes::allows($reader, 'seo:read'));
        $this->assertFalse(Scopes::allows($reader, 'seo:write'));

        // And a token carrying nothing reaches nothing, rather than everything.
        $this->assertFalse(Scopes::allows(PassportUser::bearing([]), 'seo:read'));
    }

    #[Test]
    public function a_caller_without_a_token_is_not_asked_for_one(): void
    {
        // The stdio server has no caller at all, and a person signed in through a session
        // has a model that answers `tokenCan()` with no token behind it. Both are somebody
        // else's decision by the time they arrive here.
        $this->assertTrue(Scopes::allows(null, 'seo:write'));
        $this->assertTrue(Scopes::allows(new GenericUser(['id' => 1]), 'seo:write'));
        $this->assertTrue(Scopes::allows(new PassportUser(['id' => 1]), 'seo:write'));
    }
}
