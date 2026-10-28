<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Users\UserVisitors;

/**
 * The seam of the panel answered by the module (spec §4): the signed-in visitor, a look-up by id
 * and by exact address, and where a guest of a page behind `webx.visitor` is sent.
 */
final class VisitorsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'webx.visitor'])->get('/orders', static fn (): string => 'my orders');
        Route::middleware(['web'])->get('/who', static fn (Visitors $visitors): string => $visitors->current()?->name() ?? 'guest');
    }

    #[Test]
    public function the_module_answers_the_seam_whichever_provider_ran_first(): void
    {
        $this->assertInstanceOf(UserVisitors::class, $this->app->make(Visitors::class));
    }

    #[Test]
    public function the_current_visitor_is_the_one_signed_in_on_the_site_guard(): void
    {
        $this->get('/who')->assertSee('guest');

        $anna = $this->visitor(['email' => 'anna@example.test', 'first_name' => 'Anna', 'phone' => '+800 1234 5678']);
        $this->actingAs($anna, 'site');

        $this->get('/who')->assertSee('Anna');

        $visitor = $this->app->make(Visitors::class)->current();
        $this->assertInstanceOf(Visitor::class, $visitor);
        $this->assertSame($anna->id, $visitor->id);
        $this->assertSame('anna@example.test', $visitor->email);
        $this->assertFalse($visitor->verified);
        $this->assertSame('+800 1234 5678', $visitor->phone);
        $this->assertSame([], $visitor->groups);
    }

    #[Test]
    public function a_visitor_is_found_by_id_and_by_the_exact_address_only(): void
    {
        $anna = $this->visitor(['email' => 'anna@example.test']);
        $anna->forceFill(['email_verified_at' => now()])->save();
        $visitors = $this->app->make(Visitors::class);

        $this->assertSame($anna->id, $visitors->find($anna->id)?->id);
        $this->assertTrue($visitors->find($anna->id)->verified);
        $this->assertNull($visitors->find($anna->id + 1));
        $this->assertSame($anna->id, $visitors->findByEmail('  ANNA@example.test ')?->id);
        // Never a search.
        $this->assertNull($visitors->findByEmail('anna'));
        $this->assertNull($visitors->findByEmail('%@example.test'));
        $this->assertNull($visitors->findByEmail(''));
    }

    #[Test]
    public function a_guest_of_a_page_behind_the_seam_goes_to_sign_in_and_comes_back(): void
    {
        $this->get('/orders?page=2')->assertRedirect('http://localhost/account/sign-in?back=%2Forders%3Fpage%3D2');
        $this->getJson('/orders')->assertUnauthorized()->assertJson(['code' => 'visitor.guest']);

        $this->actingAs($this->visitor(), 'site');
        $this->get('/orders')->assertOk()->assertSee('my orders');
    }

    #[Test]
    public function the_way_to_sign_in_keeps_the_language_of_the_page(): void
    {
        $this->app->setLocale('pl');

        $this->assertSame('http://localhost/pl/account/sign-in?back=%2Fpl%2Fsklep', $this->app->make(Visitors::class)->signInUrl('/pl/sklep'));
        $this->assertSame('http://localhost/pl/account/sign-in', $this->app->make(Visitors::class)->signInUrl('https://evil.test/'));
    }
}
