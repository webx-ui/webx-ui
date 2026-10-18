<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Auth\GenericUser;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Access\AccessPolicy;
use WebxUi\Admin\Access\Decision;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Tests\Fixtures\Holder;
use WebxUi\Admin\Tests\Fixtures\PagesModule;

/**
 * The one answer to «may they» (WEBX_UI_PERMISSIONS.md §4): super administrator, roles joined,
 * nothing that is not granted, a token never more than its owner, and room for limits by record.
 */
final class AccessPolicyTest extends TestCase
{
    #[Test]
    public function a_super_administrator_passes_and_anybody_else_holds_what_is_granted(): void
    {
        $policy = $this->policy();

        $this->assertTrue($policy->allows(new Holder(super: true), 'anything.at.all'));
        $this->assertTrue($policy->allows(new Holder(['pages.view']), 'pages.view'));
        $this->assertFalse($policy->allows(new Holder(['pages.view']), 'pages.manage'));

        // Several mean any of them, the way `cms.can:a,b` does.
        $this->assertTrue($policy->allows(new Holder(['pages.manage']), ['pages.view', 'pages.manage']));

        // Nothing named is nothing allowed.
        $this->assertFalse($policy->allows(new Holder(['pages.view']), []));
    }

    #[Test]
    public function a_refusal_says_why_and_what_would_have_let_them_in(): void
    {
        $decision = $this->policy()->decide(new Holder(['media.view']), ['pages.view', 'pages.manage']);

        $this->assertTrue($decision->denied());
        $this->assertSame(Decision::FORBIDDEN, $decision->reason);
        $this->assertSame(['pages.view', 'pages.manage'], $decision->required);

        $this->assertSame(Decision::UNAUTHENTICATED, $this->policy()->decide(null, 'pages.view')->reason);
        $this->assertSame(Decision::UNAUTHENTICATED, $this->policy()->decide(new GenericUser(['id' => 1]), 'pages.view')->reason);
    }

    #[Test]
    public function an_account_that_cannot_list_its_grants_is_asked_directly(): void
    {
        $this->assertTrue($this->policy()->allows(new Editor(['pages.view']), 'pages.view'));
        $this->assertFalse($this->policy()->allows(new Editor(['pages.view']), 'pages.manage'));
    }

    #[Test]
    public function a_token_is_the_intersection_of_its_scopes_and_its_owner(): void
    {
        $policy = $this->policy();

        // The owner may, the token may not.
        $narrow = new Holder(['pages.manage'], scopes: ['media:read']);
        $this->assertSame(Decision::TOKEN, $policy->decideCall($narrow, ['pages.manage'], ['pages:write'])->reason);

        // The token may, the owner may not.
        $weak = new Holder(['pages.view'], scopes: ['pages:write']);
        $this->assertSame(Decision::FORBIDDEN, $policy->decideCall($weak, ['pages.manage'], ['pages:write'])->reason);

        // Both.
        $this->assertTrue($policy->decideCall(new Holder(['pages.manage'], scopes: ['pages:write']), ['pages.manage'], ['pages:write'])->allowed);

        // Signed in by session: no token to narrow anything, the permission alone decides.
        $this->assertTrue($policy->decideCall(new Holder(['pages.manage']), ['pages.manage'], ['pages:write'])->allowed);

        // A super administrator's token is still only as wide as its scopes.
        $this->assertFalse($policy->decideCall(new Holder(super: true, scopes: ['media:read']), ['pages.manage'], ['pages:write'])->allowed);
    }

    #[Test]
    public function what_one_holds_and_another_does_not(): void
    {
        $policy = $this->policy();
        $manager = new Holder(['pages.view', 'admins.manage']);

        $this->assertSame(['pages.manage'], $policy->missing($manager, ['pages.view', 'pages.manage']));
        $this->assertSame([], $policy->missing(new Holder(super: true), ['pages.manage']));

        $this->assertTrue($policy->covers($manager, new Holder(['pages.view'])));
        $this->assertFalse($policy->covers($manager, new Holder(['pages.manage'])));
        $this->assertFalse($policy->covers($manager, new Holder(super: true)), 'Only a super administrator covers one.');
        $this->assertTrue($policy->covers(new Holder(super: true), new Holder(super: true)));
    }

    #[Test]
    public function a_limit_by_record_narrows_the_answer_without_any_door_changing(): void
    {
        $policy = $this->policy();
        $editor = new Holder(['pages.manage']);
        $mine = (object) ['author' => 1];
        $theirs = (object) ['author' => 2];

        // Nothing registered: a record changes nothing (§10).
        $this->assertTrue($policy->allows($editor, 'pages.manage', $theirs));

        $policy->constrain(static fn (HasPermissions $user, string $permission, object $record): bool => $record->author === 1);

        $this->assertTrue($policy->allows($editor, 'pages.manage', $mine));
        $this->assertFalse($policy->allows($editor, 'pages.manage', $theirs));
        // Asked without a record — a list, a screen — the permission alone answers.
        $this->assertTrue($policy->allows($editor, 'pages.manage'));
        // And a super administrator is above every limit.
        $this->assertTrue($policy->allows(new Holder(super: true), 'pages.manage', $theirs));
    }

    #[Test]
    public function the_permissions_a_role_may_list_are_those_installed_modules_declare(): void
    {
        $this->app->make(ModuleRegistry::class)->register(new PagesModule);
        $policy = $this->policy();

        $this->assertContains('pages.view', $policy->declared());
        $this->assertTrue($policy->isDeclared('pages.manage'));
        $this->assertFalse($policy->isDeclared('pages.publish'));
    }

    private function policy(): AccessPolicy
    {
        return $this->app->make(AccessPolicy::class);
    }
}
