<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Visitors\AnswersUnverified;
use WebxUi\Admin\Visitors\Events\VisitorDeleting;
use WebxUi\Admin\Visitors\Events\VisitorRegistered;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Admin\Visitors\Events\VisitorSignedOut;
use WebxUi\Admin\Visitors\NullVisitors;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;

/**
 * The seam other modules ask «who is the visitor» through (WEBX_UI_MODULE_USERS.md §4), on a site
 * with no visitors module and with a stand-in for one.
 */
final class VisitorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('webx.visitor')->get('/favourites', static fn (): string => 'Favourites');
        Route::middleware('webx.visitor:verified')->post('/favourites', static fn (): string => 'Saved');
    }

    #[Test]
    public function without_a_visitors_module_everybody_is_a_guest(): void
    {
        $visitors = $this->app->make(Visitors::class);

        $this->assertInstanceOf(NullVisitors::class, $visitors);
        $this->assertNull($visitors->current());
        $this->assertNull($visitors->find(1));
        $this->assertNull($visitors->findByEmail('somebody@example.com'));
        $this->assertNull($visitors->signInUrl('/favourites'));
    }

    #[Test]
    public function without_a_visitors_module_a_route_for_visitors_is_not_there(): void
    {
        $this->get('/favourites')->assertNotFound();
        $this->getJson('/favourites')->assertNotFound();
        $this->postJson('/favourites')->assertNotFound();
    }

    #[Test]
    public function a_binding_made_before_the_package_keeps_its_place(): void
    {
        $visitors = new FakeVisitors(null);
        $this->app->instance(Visitors::class, $visitors);
        (new AdminServiceProvider($this->app))->register();

        $this->assertSame($visitors, $this->app->make(Visitors::class));
    }

    #[Test]
    public function a_guest_is_sent_to_sign_in_and_brought_back(): void
    {
        $this->bindVisitors(null);

        $this->get('/favourites?page=2')->assertRedirect('/sign-in?back='.urlencode('http://localhost/favourites?page=2'));
        $this->getJson('/favourites')->assertUnauthorized()->assertExactJson(['code' => 'visitor.guest']);
        $this->postJson('/favourites')->assertUnauthorized()->assertExactJson(['code' => 'visitor.guest']);
    }

    #[Test]
    public function an_unconfirmed_visitor_passes_where_only_signing_in_is_asked(): void
    {
        $this->bindVisitors($this->visitor(verified: false));

        $this->get('/favourites')->assertOk()->assertSee('Favourites');
    }

    #[Test]
    public function an_unconfirmed_visitor_is_stopped_where_a_confirmed_e_mail_is_asked(): void
    {
        $this->bindVisitors($this->visitor(verified: false));

        $this->postJson('/favourites')->assertForbidden()->assertExactJson(['code' => 'visitor.unverified']);
        $this->post('/favourites')->assertForbidden();
    }

    #[Test]
    public function the_visitors_module_shows_its_own_page_to_an_unconfirmed_visitor(): void
    {
        $this->app->instance(Visitors::class, new FakeVisitorsWithPage($this->visitor(verified: false)));

        $this->post('/favourites')->assertForbidden()->assertSee('Confirm your e-mail');
        $this->postJson('/favourites')->assertForbidden()->assertExactJson(['code' => 'visitor.unverified']);
    }

    #[Test]
    public function a_confirmed_visitor_passes_both(): void
    {
        $this->bindVisitors($this->visitor(verified: true));

        $this->get('/favourites')->assertOk()->assertSee('Favourites');
        $this->post('/favourites')->assertOk()->assertSee('Saved');
    }

    #[Test]
    public function a_requirement_other_than_verified_is_a_mistake_of_whoever_wrote_the_route(): void
    {
        $this->withoutExceptionHandling();
        Route::middleware('webx.visitor:admin')->get('/odd', static fn (): string => 'Odd');
        $this->bindVisitors($this->visitor(verified: true));

        $this->expectException(InvalidArgumentException::class);
        $this->get('/odd');
    }

    #[Test]
    public function the_directives_print_by_who_is_there(): void
    {
        $template = '@visitor in @else out @endvisitor|@visitorVerified sure @else unsure @endvisitorVerified';

        $this->assertSame('out | unsure', $this->squash(Blade::render($template)));

        $this->bindVisitors($this->visitor(verified: false));
        $this->assertSame('in | unsure', $this->squash(Blade::render($template)));

        $this->bindVisitors($this->visitor(verified: true));
        $this->assertSame('in | sure', $this->squash(Blade::render($template)));
    }

    #[Test]
    public function a_visitor_is_named_by_their_name_or_their_address(): void
    {
        $this->assertSame('Alex Doe', $this->visitor(true, 'Alex', 'Doe')->name());
        $this->assertSame('Alex', $this->visitor(true, 'Alex')->name());
        $this->assertSame('Doe', $this->visitor(true, null, 'Doe')->name());
        $this->assertSame('alex.doe', $this->visitor(true)->name());
        $this->assertTrue($this->visitor(true)->inGroup('wholesale'));
        $this->assertFalse($this->visitor(true)->inGroup('retail'));
    }

    /**
     * A listener on a queue gets the event serialised, so every event carries plain values only.
     */
    #[Test]
    public function the_events_survive_a_queue(): void
    {
        $events = [
            new VisitorSignedIn(7, 'google'),
            new VisitorSignedOut(7),
            new VisitorRegistered(7, 'password'),
            new VisitorDeleting(7),
        ];

        foreach ($events as $event) {
            $this->assertEquals($event, unserialize(serialize($event)));
            $this->assertSame(7, unserialize(serialize($event))->visitorId);
        }

        $visitor = $this->visitor(true, 'Alex', 'Doe');
        $this->assertEquals($visitor, unserialize(serialize($visitor)));
    }

    private function bindVisitors(?Visitor $visitor): void
    {
        $this->app->instance(Visitors::class, new FakeVisitors($visitor));
    }

    private function visitor(bool $verified, ?string $firstName = null, ?string $lastName = null): Visitor
    {
        return new Visitor(
            id: 7,
            email: 'alex.doe@example.com',
            verified: $verified,
            firstName: $firstName,
            lastName: $lastName,
            groups: ['wholesale'],
        );
    }

    private function squash(string $html): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($html));
    }
}

/** A visitors module as the seam sees one: somebody is signed in, or nobody, and a page to sign in on. */
class FakeVisitors implements Visitors
{
    public function __construct(private readonly ?Visitor $visitor) {}

    public function current(): ?Visitor
    {
        return $this->visitor;
    }

    public function find(int $id): ?Visitor
    {
        return $this->visitor?->id === $id ? $this->visitor : null;
    }

    public function findByEmail(string $email): ?Visitor
    {
        return $this->visitor?->email === $email ? $this->visitor : null;
    }

    public function signInUrl(?string $back = null): ?string
    {
        return '/sign-in'.($back === null ? '' : '?back='.urlencode($back));
    }
}

final class FakeVisitorsWithPage extends FakeVisitors implements AnswersUnverified
{
    public function unverifiedResponse(Request $request): Response
    {
        return new HttpResponse('Confirm your e-mail', 403);
    }
}
