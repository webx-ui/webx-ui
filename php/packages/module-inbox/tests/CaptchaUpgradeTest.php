<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Settings\Settings;
use WebxUi\Settings\SettingsServiceProvider;
use WebxUi\Widgets\Captcha;

/**
 * A site updated with its captcha set up the way the inbox had it (ORDERS §28.1): the keys in the
 * environment under the inbox's names, the provider in the form, no settings of the site. After
 * `migrate` the form draws the same widget, refuses without an answer and takes a good one, the
 * Captcha tab shows the provider, and the environment is as it was; the rollback puts it back.
 */
final class CaptchaUpgradeTest extends TestCase
{
    private const string VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const array ENV = [
        'WEBX_INBOX_TURNSTILE_KEY' => 'before-site-key',
        'WEBX_INBOX_TURNSTILE_SECRET' => 'before-secret',
    ];

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SettingsServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The site's environment of before: only the inbox's names, read the way a booting site
        // reads its config files.
        foreach (self::ENV as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        config()->set('webx-widgets.captcha', (require dirname(__DIR__, 2).'/widgets/config/webx-widgets.php')['captcha']);
        config()->set('webx-inbox.captcha', (require dirname(__DIR__).'/config/webx-inbox.php')['captcha']);
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::ENV) as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        parent::tearDown();
    }

    #[Test]
    public function a_site_with_the_inbox_captcha_keeps_it_through_the_update_and_back(): void
    {
        $environment = $this->environment();
        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);
        $before = $this->widget();

        $this->assertStringContainsString('data-webx-captcha="turnstile"', $before);
        $this->assertStringContainsString('data-sitekey="before-site-key"', $before);
        $this->assertNull(app(Captcha::class)->provider());

        $this->migrate('up');

        // The Captcha tab shows the provider of the forms; the keys are still the environment's.
        $this->assertSame('turnstile', app(Settings::class)->get('captcha.provider'));
        $this->assertSame('turnstile', app(Captcha::class)->provider());
        $this->assertSame(['type' => 'checkbox', 'key' => 'before-site-key', 'secret' => true], app(Captcha::class)->status()['providers']['turnstile']);

        // The same widget, the same door.
        $this->assertSame($before, $this->widget());
        // The form keeps its own word: a provider there still means "on".
        $this->assertSame('turnstile', Form::query()->where('slug', 'contact')->firstOrFail()->option('antispam.captcha'));

        $this->postJson($this->intake(), $this->answers())
            ->assertStatus(422)
            ->assertJsonPath('errors.captcha.0', 'Please confirm you are not a robot and send the form again.');

        Http::fake([self::VERIFY => Http::response(['success' => true])]);

        $this->postJson($this->intake(), $this->answers(['cf-turnstile-response' => 'the-token']))->assertOk();
        $this->assertSame(1, Submission::query()->count());
        Http::assertSent(static fn (ClientRequest $request): bool => $request['secret'] === 'before-secret' && $request['response'] === 'the-token');

        // Nothing was written to the environment.
        $this->assertSame($environment, $this->environment());

        $this->migrate('down');

        $this->assertNull(app(Settings::class)->get('captcha.provider'));
        $this->assertNull(app(Captcha::class)->provider());
        $this->assertSame($before, $this->widget());
    }

    #[Test]
    public function a_provider_the_site_already_chose_is_not_overwritten(): void
    {
        app(Settings::class)->save(['captcha.provider' => 'recaptcha']);
        $this->form('contact', [], ['antispam.captcha' => 'turnstile']);

        $this->migrate('up');

        $this->assertSame('recaptcha', app(Settings::class)->get('captcha.provider'));

        // Not the one it wrote, so not the one it takes back.
        $this->migrate('down');

        $this->assertSame('recaptcha', app(Settings::class)->get('captcha.provider'));
    }

    #[Test]
    public function forms_of_two_providers_give_the_site_the_one_more_of_them_use(): void
    {
        Log::spy();
        $this->form('contact', [], ['antispam.captcha' => 'recaptcha']);
        $this->form('callback', [], ['antispam.captcha' => 'turnstile']);
        $this->form('quote', [], ['antispam.captcha' => 'turnstile']);
        $this->form('plain');
        $this->form('old', [], ['antispam.captcha' => 'recaptcha'])->update(['is_enabled' => false]);

        $this->migrate('up');

        $this->assertSame('turnstile', app(Settings::class)->get('captcha.provider'));
        Log::getFacadeRoot()->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, 'different captchas'))->once();
    }

    #[Test]
    public function a_site_whose_forms_want_no_captcha_gets_no_provider(): void
    {
        $this->form('contact');

        $this->migrate('up');

        $this->assertSame(0, DB::table('cms_settings')->where('key', 'captcha.provider')->count());
    }

    /** The captcha block of the form, the way the page prints it. */
    private function widget(): string
    {
        $this->app->forgetScopedInstances();
        Route::middleware('web')->get('/page', static fn (): string => Blade::render('<x-webx-inbox::form slug="contact" />'));
        $html = $this->get('/page')->assertOk()->getContent() ?: '';

        $this->assertSame(1, preg_match('~<div\s+class="webx-inbox-form__captcha".*?</p>\s*</div>~s', $html, $match), 'No captcha in the form.');

        return $match[0];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function answers(array $extra = []): array
    {
        return [
            'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            'webx_ts' => Crypt::encrypt((string) (time() - 60)),
            ...$extra,
        ];
    }

    /** @return array<string, string|false> */
    private function environment(): array
    {
        $file = base_path('.env');

        return [
            'file' => is_file($file) ? (string) hash_file('sha256', $file) : false,
            ...array_map(static fn (string $name): string|false => getenv($name), array_combine(array_keys(self::ENV), array_keys(self::ENV))),
        ];
    }

    private function migrate(string $direction): void
    {
        $migration = require dirname(__DIR__).'/database/migrations/2026_10_29_000000_move_captcha_provider_of_inbox_forms_to_the_site.php';

        if (! $migration instanceof Migration || ! method_exists($migration, $direction)) {
            self::fail("The migration has no {$direction}().");
        }

        $migration->{$direction}();
    }
}
