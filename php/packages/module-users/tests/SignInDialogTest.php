<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Visitors\Events\VisitorSignedIn;
use WebxUi\Users\Http\ScriptController;
use WebxUi\Users\Mail\ResetPassword;
use WebxUi\Users\Models\ProviderSettings;
use WebxUi\Users\SignInDialog;
use WebxUi\Users\Social\ProviderKeys;

/**
 * The dialog of the sign-in for a checkout (spec §12.1, §12.2): printed once a page that opens it,
 * three views, its forms answering the dialog's script in place and a plain POST with the way
 * back; the reset and the social networks coming back to the page; nothing personal in it.
 */
final class SignInDialogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A step of a checkout: the button of the module, and a page of the site with a link only.
        Route::middleware('web')->get('/checkout', static fn (): string => Blade::render(
            '<html><body><main><x-webx-users::sign-in-button /><p>Your order</p></main></body></html>',
        ));
        Route::middleware('web')->get('/delivery', static fn (): string => Blade::render(
            '<html><body><main><a href="#webx-sign-in">Sign in</a></main></body></html>',
        ));
        Route::middleware('web')->get('/catalog', static fn (): string => Blade::render(
            '<html><body><main><p>Shoes</p></main></body></html>',
        ));
        // «Thank you» of a checkout: the registration with the order's address (orders §26).
        Route::middleware('web')->get('/thanks', static fn (): string => Blade::render(
            '<html><body><main><x-webx-users::sign-in-button view="register" email="buyer-a@example.com">Save</x-webx-users::sign-in-button><x-webx-users::sign-in-button view="register" email="not an address">Bad</x-webx-users::sign-in-button></main></body></html>',
        ));
        Route::middleware('web')->get('/both', static fn (): string => Blade::render(
            '<html><body><main><x-webx-users::sign-in-dialog /><x-webx-users::sign-in-button view="register">Join</x-webx-users::sign-in-button></main></body></html>',
        ));
    }

    #[Test]
    public function the_button_is_a_link_to_the_sign_in_with_the_way_back_and_brings_the_dialog(): void
    {
        $html = (string) $this->get('/checkout?step=contacts')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<a href="http://localhost/account/sign-in?back=%2Fcheckout%3Fstep%3Dcontacts" rel="nofollow" class="webx-users-sign-in-button" data-webx-sign-in="sign-in" data-webx-users-guest>Sign in</a>',
            $html,
        );
        $this->assertSame(1, substr_count($html, 'id="webx-sign-in"'));
        // Before </body>, after the page.
        $this->assertGreaterThan(strpos($html, 'Your order'), strpos($html, '<dialog'));
        $this->assertLessThan(strrpos($html, '</body>'), strpos($html, '<dialog'));
        $this->assertMatchesRegularExpression('~<script type="module" src="http://localhost/webx/users/sign-in\.js\?v=[0-9a-f]{16}"></script>~', $html);
    }

    #[Test]
    public function the_button_of_a_registration_brings_the_address_to_the_dialog_and_to_the_page(): void
    {
        $html = (string) $this->get('/thanks')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<a href="http://localhost/account/register?email=buyer-a%40example.com&amp;back=%2Fthanks" rel="nofollow" data-webx-sign-in-email="buyer-a@example.com" class="webx-users-sign-in-button" data-webx-sign-in="register" data-webx-users-guest>Save</a>',
            $html,
        );
        // Not an address — the button as it was without one.
        $this->assertStringContainsString('<a href="http://localhost/account/register?back=%2Fthanks" rel="nofollow" class="webx-users-sign-in-button" data-webx-sign-in="register" data-webx-users-guest>Bad</a>', $html);

        // Without JavaScript the page of the registration starts with it.
        $page = (string) $this->get('/account/register?email=buyer-a%40example.com&back=%2Fthanks')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<input[^>]*name="email"[^>]*value="buyer-a@example.com"~', $page);
        $this->assertDoesNotMatchRegularExpression('~name="email"[^>]*value="not~', (string) $this->get('/account/register?email=not')->getContent());
    }

    #[Test]
    public function the_dialog_has_three_views_the_sign_in_shown(): void
    {
        $html = (string) $this->get('/checkout')->getContent();
        $dialog = self::dialog($html);

        $this->assertStringContainsString('class="webx-dialog webx-dialog--sheet webx-users-sign-in"', $dialog);
        $this->assertStringContainsString('<h2 class="webx-dialog__title" id="webx-sign-in-title">Sign in</h2>', $dialog);
        $this->assertMatchesRegularExpression('~data-webx-sign-in-view="sign-in" data-webx-sign-in-title="Sign in"\s*>~', $dialog);
        $this->assertMatchesRegularExpression('~data-webx-sign-in-view="forgot" data-webx-sign-in-title="[^"]+"\s+hidden~', $dialog);
        $this->assertMatchesRegularExpression('~data-webx-sign-in-view="register" data-webx-sign-in-title="[^"]+"\s+hidden~', $dialog);

        // Each form of the dialog its own, sent to the page's own address, back to this page.
        foreach (SignInDialog::FORMS as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $dialog);
        }
        $this->assertStringContainsString('action="http://localhost/account/sign-in"', $dialog);
        $this->assertStringContainsString('action="http://localhost/account/forgot"', $dialog);
        $this->assertStringContainsString('action="http://localhost/account/register"', $dialog);
        $this->assertSame(3, substr_count($dialog, 'name="back" value="/checkout"'));
        $this->assertSame(2, substr_count($dialog, 'name="webx_dialog" value="1"'));
        $this->assertStringContainsString('autocomplete="current-password"', $dialog);
        $this->assertStringContainsString('autocomplete="new-password"', $dialog);

        // Between the views: links to the pages, which the script turns into switches.
        $this->assertStringContainsString('href="http://localhost/account/forgot?back=%2Fcheckout" data-webx-sign-in-show="forgot"', $dialog);
        $this->assertStringContainsString('href="http://localhost/account/register?back=%2Fcheckout" data-webx-sign-in-show="register"', $dialog);
        $this->assertSame(2, substr_count($dialog, 'href="http://localhost/account/sign-in?back=%2Fcheckout" data-webx-sign-in-show="sign-in"'));
        $this->assertStringNotContainsString(' open', substr($dialog, 0, (int) strpos($dialog, '>')));
    }

    #[Test]
    public function a_link_to_the_dialog_is_an_opener_and_a_page_without_one_gets_nothing(): void
    {
        $this->assertStringContainsString('id="webx-sign-in"', (string) $this->get('/delivery')->getContent());

        DB::enableQueryLog();
        $html = (string) $this->get('/catalog')->assertOk()->getContent();

        $this->assertStringNotContainsString('webx-sign-in', $html);
        $mine = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains((string) $query['query'], 'users'));
        $this->assertSame([], $mine, 'A page that does not open the dialog costs no query of the module.');
    }

    #[Test]
    public function a_dialog_a_template_printed_is_the_one_of_the_page(): void
    {
        $html = (string) $this->get('/both')->getContent();

        $this->assertSame(1, substr_count($html, 'id="webx-sign-in"'));
        $this->assertStringContainsString('data-webx-sign-in="register"', $html);
        $this->assertStringContainsString('>Join</a>', $html);
        $this->assertStringContainsString('href="http://localhost/account/register?back=%2Fboth"', $html);
    }

    #[Test]
    public function the_same_for_everybody(): void
    {
        $guest = self::withoutCsrf((string) $this->get('/checkout')->getContent());

        $this->actingAs($this->visitor(['email' => 'anna@example.test', 'first_name' => 'Anna']), 'site');
        $signedIn = self::withoutCsrf((string) $this->get('/checkout')->getContent());

        $this->assertSame($guest, $signedIn, 'The page around the dialog may be cached for everybody.');
        $this->assertStringNotContainsString('anna@', $signedIn);
    }

    #[Test]
    public function a_sign_in_in_place_answers_the_script_rather_than_a_redirect(): void
    {
        Event::fake([VisitorSignedIn::class]);
        $anna = $this->visitor(['email' => 'anna@example.test', 'first_name' => 'Anna', 'last_name' => 'Lind']);

        $this->postJson('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'back' => '/checkout', 'webx_dialog' => '1'])
            ->assertOk()
            ->assertExactJson([
                'ok' => true, 'heading' => null, 'heading_html' => null, 'message' => null, 'redirect' => null, 'html' => null,
                'visitor' => ['name' => 'Anna Lind', 'verified' => false],
            ]);

        $this->assertSame($anna->id, Auth::guard('site')->id());
        Event::assertDispatched(VisitorSignedIn::class, static fn (VisitorSignedIn $event): bool => $event->visitorId === $anna->id && $event->method === 'password');
    }

    #[Test]
    public function without_javascript_the_dialog_goes_back_to_the_page(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'back' => '/checkout', 'webx_dialog' => '1'])
            ->assertRedirect('/checkout');

        // The page of the sign-in sent in place still goes where it goes.
        Auth::guard('site')->logout();
        $this->postJson('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'back' => '/checkout'])
            ->assertOk()
            ->assertJsonPath('redirect', '/checkout')
            ->assertJsonMissingPath('visitor');
    }

    #[Test]
    public function a_wrong_pair_without_javascript_opens_the_dialog_again_on_the_sign_in(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        $this->from('/checkout')
            ->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => 'wrong', 'back' => '/checkout', 'webx_dialog' => '1', '_webx_form' => 'webx-sign-in-form'])
            ->assertRedirect('/checkout');

        $dialog = self::dialog((string) $this->get('/checkout')->getContent());

        $this->assertMatchesRegularExpression('~^<dialog[^>]* open\s*>~', $dialog);
        $this->assertStringContainsString('Wrong e-mail or password.', $dialog);
        $this->assertMatchesRegularExpression('~data-webx-sign-in-view="sign-in" data-webx-sign-in-title="Sign in"\s*>~', $dialog);
    }

    #[Test]
    public function a_registration_in_place_signs_in_and_answers_the_script(): void
    {
        Mail::fake();

        $this->postJson('/account/register', ['email' => 'new@example.test', 'password' => self::PASSWORD, 'back' => '/checkout', 'webx_dialog' => '1'])
            ->assertOk()
            ->assertJsonPath('redirect', null)
            ->assertJsonPath('visitor', ['name' => 'new', 'verified' => false]);

        $this->assertTrue(Auth::guard('site')->check());
    }

    #[Test]
    public function a_registration_sent_back_opens_the_dialog_on_the_registration(): void
    {
        $this->visitor(['email' => 'anna@example.test']);

        $this->from('/checkout')
            ->post('/account/register', ['email' => 'anna@example.test', 'password' => self::PASSWORD, 'back' => '/checkout', 'webx_dialog' => '1', '_webx_form' => 'webx-sign-in-register'])
            ->assertRedirect('/checkout');

        $dialog = self::dialog((string) $this->get('/checkout')->getContent());

        $this->assertMatchesRegularExpression('~^<dialog[^>]* open\s*>~', $dialog);
        $this->assertStringContainsString('<h2 class="webx-dialog__title" id="webx-sign-in-title">Create an account</h2>', $dialog);
        $this->assertMatchesRegularExpression('~data-webx-sign-in-view="register" data-webx-sign-in-title="[^"]+"\s*>~', $dialog);
        $this->assertMatchesRegularExpression('~data-webx-sign-in-view="sign-in" data-webx-sign-in-title="Sign in"\s+hidden~', $dialog);
    }

    #[Test]
    public function closed_registration_leaves_the_dialog_without_it(): void
    {
        config()->set('webx-users.registration', 'closed');

        $dialog = self::dialog((string) $this->get('/checkout')->getContent());

        $this->assertStringNotContainsString('data-webx-sign-in-view="register"', $dialog);
        $this->assertStringNotContainsString('/account/register', $dialog);
    }

    #[Test]
    public function the_link_of_a_reset_from_the_dialog_comes_back_to_the_page(): void
    {
        Mail::fake();
        $anna = $this->visitor(['email' => 'anna@example.test']);

        $this->postJson('/account/forgot', ['email' => 'anna@example.test', 'back' => '/checkout?step=contacts'])->assertOk()->assertJsonPath('redirect', null);

        $link = null;
        Mail::assertQueued(ResetPassword::class, static function (ResetPassword $mail) use (&$link): bool {
            $link = $mail->link;

            return true;
        });
        $this->assertIsString($link);
        $this->assertStringContainsString('?back=%2Fcheckout%3Fstep%3Dcontacts', $link);

        $page = (string) $this->get($link)->assertOk()->getContent();
        $this->assertStringContainsString('name="back" value="/checkout?step=contacts"', $page);

        $action = (string) preg_replace('~^.*action="([^"]+)".*$~s', '$1', $page);
        $this->post($action, ['password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1', 'back' => '/checkout?step=contacts'])
            ->assertRedirect('/checkout?step=contacts');
        $this->assertSame($anna->id, Auth::guard('site')->id());
    }

    #[Test]
    public function the_page_of_the_sign_in_passes_the_way_back_to_forgot_your_password(): void
    {
        $this->get('/account/sign-in?back=/checkout')
            ->assertSee('href="http://localhost/account/forgot?back=%2Fcheckout"', false);

        $this->get('/account/forgot?back=/checkout')
            ->assertSee('name="back" value="/checkout"', false)
            ->assertSee('href="http://localhost/account/sign-in?back=%2Fcheckout"', false);
    }

    #[Test]
    public function a_social_network_of_the_dialog_comes_back_to_the_page(): void
    {
        ProviderSettings::query()->create(['key' => 'google', 'enabled' => true, 'options' => ['client_id' => 'id-1']]);
        config()->set('webx-users.social.keys.google.client_secret', 'from-env');
        $this->app->make(ProviderKeys::class)->forget();

        $dialog = self::dialog((string) $this->get('/checkout')->getContent());

        $this->assertSame(2, substr_count($dialog, 'href="http://localhost/account/auth/google?back=%2Fcheckout"'));
        $this->assertStringNotContainsString('from-env', $dialog);
    }

    #[Test]
    public function the_script_is_served_for_good_and_small(): void
    {
        $response = $this->get('/webx/users/sign-in.js?v='.ScriptController::version('sign-in'))->assertOk();

        $this->assertStringContainsString('text/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('webx:users:signed-in', (string) $response->getContent());
        $this->assertNotSame(ScriptController::version(), ScriptController::version('sign-in'));
        $this->assertLessThan(4096, strlen(ScriptController::contents('sign-in')));
    }

    private static function withoutCsrf(string $html): string
    {
        return (string) preg_replace('~name="_token" value="[^"]*"~', '', $html);
    }

    private static function dialog(string $html): string
    {
        $start = strpos($html, '<dialog');
        $end = strpos($html, '</dialog>');
        self::assertNotFalse($start, 'The page has the dialog.');
        self::assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }
}
