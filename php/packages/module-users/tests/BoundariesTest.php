<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Auth\AuthServiceProvider;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Users\Models\User;

/**
 * The lines the module never crosses (spec §1, §18): a visitor is no administrator, leaving the
 * site does not leave the panel, and nothing of a shop is in the package.
 */
final class BoundariesTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AuthServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        // A page of the panel and the administrator's sign-in, as the browser of one person who
        // is both an editor of the site and a customer of it would meet them.
        Route::middleware(['web', 'cms.auth'])->get('/test/panel', static fn (): string => 'in the panel');
        Route::middleware('web')->post('/test/admin-sign-in', static function (): string {
            $admin = CmsUser::query()->sole();
            Auth::guard((string) config('webx-auth.guard'))->login($admin);

            return 'signed in';
        });
    }

    #[Test]
    public function the_administrator_stays_in_the_panel_after_the_visitor_signs_out(): void
    {
        CmsUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.test', 'password' => 'secret-secret', 'is_super' => true, 'is_active' => true]);
        $this->visitor(['email' => 'anna@example.test']);

        $this->post('/test/admin-sign-in')->assertOk();
        $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD])->assertRedirect();
        $this->assertTrue(Auth::guard('site')->check());

        $this->post('/account/sign-out')->assertRedirect();

        // The next request reads both guards afresh from the one session.
        Auth::forgetGuards();
        $this->get('/test/panel')->assertOk()->assertSee('in the panel');
        Auth::forgetGuards();
        $this->assertFalse(Auth::guard('site')->check());
    }

    #[Test]
    public function a_visitor_is_no_one_to_the_panel(): void
    {
        $this->assertNotContains(HasPermissions::class, class_implements(User::class) ?: []);

        $this->visitor(['email' => 'anna@example.test']);
        $this->post('/account/sign-in', ['email' => 'anna@example.test', 'password' => self::PASSWORD])->assertRedirect();

        Auth::forgetGuards();
        $this->get('/test/panel')->assertUnauthorized();
    }

    #[Test]
    public function the_guard_of_the_site_is_added_and_the_default_one_left_alone(): void
    {
        $this->assertSame(['driver' => 'session', 'provider' => 'webx-users'], config('auth.guards.site'));
        $this->assertSame(User::class, config('auth.providers.webx-users.model'));
        $this->assertSame('web', config('auth.defaults.guard'));
    }

    #[Test]
    public function no_package_of_a_shop_is_required_or_suggested(): void
    {
        /** @var array{require: array<string, string>, require-dev?: array<string, string>, suggest?: array<string, string>} $composer */
        $composer = json_decode((string) file_get_contents(dirname(__DIR__).'/composer.json'), true);
        $shop = '/^webx-ui\/module-(cart|catalog|orders|checkout|wallet|certificates|configurator|tariffs)/';

        foreach (['require', 'require-dev', 'suggest'] as $section) {
            $this->assertSame([], preg_grep($shop, array_keys($composer[$section] ?? [])), "{$section} names a shop module");
        }

        $code = '';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__).'/src')) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $code .= file_get_contents($file->getPathname());
            }
        }

        $this->assertDoesNotMatchRegularExpression('/WebxUi\\\\(Cart|Catalog|Orders|Checkout|Wallet|Certificates)\\\\/', $code);
    }
}
