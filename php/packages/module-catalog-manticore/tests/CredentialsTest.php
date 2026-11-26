<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Manticore\Doctor\ManticoreCheck;
use WebxUi\Catalog\Manticore\Manticore;
use WebxUi\Catalog\Manticore\ManticoreDenied;
use WebxUi\Catalog\Manticore\ManticoreEngine;
use WebxUi\Catalog\Manticore\ManticoreUnavailable;
use WebxUi\Catalog\Tests\TestCase;

/**
 * The login of a server with `auth = 1`, with no server at all: every request carries it when it
 * is set and none does when it is not; a refusal is reported as one, is not taken for an outage
 * and is not a reason to make a table again; and the password is in no message and no log line.
 */
final class CredentialsTest extends TestCase
{
    use UsesManticore;

    private const USERNAME = 'shop__dev';

    private const PASSWORD = 'Pa55-n0t-in-logs';

    /** @var list<MessageLogged> */
    private array $logged = [];

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->useManticore($app);
        $app['config']->set('webx-catalog-manticore.host', '127.0.0.1');
        $app['config']->set('webx-catalog-manticore.port', 9308);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(MessageLogged::class, function (MessageLogged $logged): void {
            $this->logged[] = $logged;
        });
    }

    #[Test]
    public function without_a_login_no_request_carries_one(): void
    {
        $this->answering();
        $this->talk();

        Http::assertSentCount(4);
        Http::assertNotSent(static fn (Request $request): bool => $request->hasHeader('Authorization'));
    }

    #[Test]
    public function with_a_login_every_request_carries_it(): void
    {
        $this->login();
        $this->answering();
        $this->talk();

        $basic = 'Basic '.base64_encode(self::USERNAME.':'.self::PASSWORD);
        Http::assertSentCount(4);
        Http::assertNotSent(static fn (Request $request): bool => ! $request->hasHeader('Authorization', $basic));
    }

    #[Test]
    public function a_wrong_login_is_reported_without_the_password_and_the_server_is_not_paused(): void
    {
        $this->login();
        Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]);
        $server = $this->app->make(Manticore::class);

        $denied = $this->denied(static fn () => $server->sql('SHOW VERSION'));
        $this->assertStringContainsString('['.self::USERNAME.']', $denied->getMessage());
        $this->assertStringContainsString('MANTICORE_PASSWORD', $denied->getMessage());
        $this->assertFalse($server->down());

        // Asked again at once — a login fixed is used without waiting — and logged once.
        $this->denied(static fn () => $server->sql('SHOW VERSION'));
        Http::assertSentCount(2);
        $this->assertCount(1, $this->logged);
        $this->assertPasswordNowhere($denied);
    }

    #[Test]
    public function a_server_that_asks_for_a_login_says_which_to_set(): void
    {
        Http::fake(['*' => Http::response('', 401)]);

        $denied = $this->denied(fn () => $this->app->make(Manticore::class)->sql('SHOW TABLES'));

        $this->assertStringContainsString('set MANTICORE_USERNAME and MANTICORE_PASSWORD', $denied->getMessage());
    }

    #[Test]
    public function a_login_without_rights_falls_back_on_the_database_and_the_doctor_says_why(): void
    {
        $this->login();
        Http::fake(['*' => Http::response(['error' => 'Forbidden'], 403)]);
        $product = $this->product('ThinkPad', $this->category('laptops'));
        $engine = $this->engine();

        $this->assertSame([$product->id], $engine->search(new CatalogQuery(locale: 'en'))->ids);
        $this->assertTrue($engine->fellBack());
        $this->assertFalse($this->app->make(Manticore::class)->down());

        $diagnoses = $this->app->make(ManticoreCheck::class)->run();
        $this->assertCount(1, $diagnoses);
        $this->assertTrue($diagnoses[0]->failed());
        $this->assertStringContainsString("read, write and schema on '{$this->prefix}_*'", $diagnoses[0]->detail);
        $this->assertStringNotContainsString(self::PASSWORD, $diagnoses[0]->detail);
        $this->assertPasswordNowhere();
    }

    #[Test]
    public function a_refusal_in_the_text_of_an_error_is_a_refusal_too(): void
    {
        $this->login();
        Http::fake(['*' => Http::response([['total' => 0, 'error' => "permission denied for user '".self::USERNAME."'", 'warning' => '']])]);

        $this->denied(fn () => $this->app->make(Manticore::class)->sql('SHOW TABLES'));
        $this->assertPasswordNowhere();
    }

    #[Test]
    public function a_table_the_login_does_not_see_is_reported_and_not_made_again(): void
    {
        $this->login();
        Http::fake(function (Request $request) {
            parse_str($request->body(), $form);

            // Manticore shows a login none of the tables it has no right to.
            return str_starts_with((string) ($form['query'] ?? ''), 'CREATE TABLE')
                ? Http::response([['total' => 0, 'error' => "table '{$this->prefix}_catalog_products_en': CREATE TABLE failed: table already exists", 'warning' => '']])
                : Http::response([['columns' => [], 'data' => [], 'total' => 0, 'error' => '', 'warning' => '']]);
        });

        $denied = $this->denied(fn () => $this->engine()->prepare([]));

        $this->assertStringContainsString("[{$this->prefix}_catalog_products_en]", $denied->getMessage());
        $this->assertStringContainsString('left as it is', $denied->getMessage());
        Http::assertNotSent(static function (Request $request): bool {
            parse_str($request->body(), $form);

            return str_starts_with((string) ($form['query'] ?? ''), 'DROP');
        });
    }

    #[Test]
    public function the_password_stays_out_of_an_outage(): void
    {
        $this->login();
        // A port nobody listens on: refused at once, with the login on the request.
        $this->app['config']->set('webx-catalog-manticore.port', 9);
        Http::allowStrayRequests();

        try {
            $this->app->make(Manticore::class)->sql('SHOW VERSION');
            $this->fail('Nobody listens on the port.');
        } catch (ManticoreUnavailable $unavailable) {
            $this->assertPasswordNowhere($unavailable);
        }

        $this->assertNotSame([], $this->logged);
    }

    private function login(): void
    {
        $this->app['config']->set('webx-catalog-manticore.username', self::USERNAME);
        $this->app['config']->set('webx-catalog-manticore.password', self::PASSWORD);
    }

    /** A server that takes everything: SQL in raw mode and `/bulk`. */
    private function answering(): void
    {
        Http::fake(static fn (Request $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/bulk')
            ? Http::response(['items' => [], 'current_line' => 1, 'skipped_lines' => 0, 'errors' => false, 'error' => ''])
            : Http::response([['columns' => [], 'data' => [], 'total' => 0, 'error' => '', 'warning' => '']]));
    }

    /** One statement, two side by side, one write: every way the package talks to the server. */
    private function talk(): void
    {
        $server = $this->app->make(Manticore::class);
        $server->sql('SHOW VERSION');
        $server->sqlMany(['SHOW TABLES', 'SHOW VERSION']);
        $server->bulk([['delete' => ['table' => $server->table('en'), 'id' => 1]]]);
    }

    /**
     * @param  callable(): mixed  $ask
     */
    private function denied(callable $ask): ManticoreDenied
    {
        try {
            $ask();
        } catch (ManticoreDenied $denied) {
            $this->assertStringNotContainsString(self::PASSWORD, $denied->getMessage());

            return $denied;
        }

        $this->fail('The server refused the login; the package did not say so.');
    }

    /** Neither the password nor the header made of it, in the failure or in anything logged. */
    private function assertPasswordNowhere(?Throwable $failure = null): void
    {
        $texts = $failure === null ? [] : [(string) $failure];

        for ($previous = $failure?->getPrevious(); $previous !== null; $previous = $previous->getPrevious()) {
            $texts[] = (string) $previous;
        }

        foreach ($this->logged as $logged) {
            $texts[] = $logged->message;

            foreach ($logged->context as $value) {
                $texts[] = $value instanceof Throwable ? (string) $value : (string) json_encode($value);
            }
        }

        foreach ($texts as $text) {
            $this->assertStringNotContainsString(self::PASSWORD, $text);
            $this->assertStringNotContainsString(base64_encode(self::USERNAME.':'.self::PASSWORD), $text);
        }
    }

    private function engine(): ManticoreEngine
    {
        $engine = $this->app->make(Catalog::class)->engine();
        $this->assertInstanceOf(ManticoreEngine::class, $engine);

        return $engine;
    }
}
