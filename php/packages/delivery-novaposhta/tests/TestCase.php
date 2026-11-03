<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use WebxUi\Cache\CacheServiceProvider;
use WebxUi\Cart\CartServiceProvider;
use WebxUi\Delivery\DeliveryServiceProvider;
use WebxUi\Delivery\NovaPoshta\Api\Journal;
use WebxUi\Delivery\NovaPoshta\DeliveryNovaPoshtaServiceProvider;
use WebxUi\Geography\GeographyServiceProvider;
use WebxUi\Localization\LocalizationServiceProvider;

/**
 * Nothing here reaches the carrier: every answer is `Http::fake()` with the key `test-key`
 * (§10.3, §13). The answers of stage 2 are drafts after the documentation; stage 3 records the
 * real ones, scrubbed.
 */
abstract class TestCase extends Orchestra
{
    public const string KEY = 'test-key';

    public const string URL = 'https://api.novaposhta.test/v2.0/json/';

    protected string $journal;

    protected function setUp(): void
    {
        $this->journal = sys_get_temp_dir().'/novaposhta-calls-'.bin2hex(random_bytes(6)).'.log';

        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        @unlink($this->journal);

        parent::tearDown();
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocalizationServiceProvider::class,
            CacheServiceProvider::class,
            GeographyServiceProvider::class,
            CartServiceProvider::class,
            DeliveryServiceProvider::class,
            DeliveryNovaPoshtaServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('webx-localization.locales', [['code' => 'en', 'default' => true], ['code' => 'uk']]);
        $app['config']->set('webx-localization.cache.enabled', false);
        $app['config']->set('webx-cart.currency', 'UAH');
        $app['config']->set('webx-delivery-novaposhta.journal', $this->journal);
        $app['config']->set('webx-delivery-novaposhta.url', self::URL);
        // No pauses in tests: the pace has a test of its own.
        $app['config']->set('webx-delivery-novaposhta.sync.requests_per_minute', 0);
        $app['config']->set('webx-delivery.sync.backoff', [0, 0, 0]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();
    }

    /**
     * A body of the carrier's answer.
     *
     * @param  list<mixed>  $data
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    protected static function answer(array $data = [], bool $success = true, array $errors = [], ?int $total = null): array
    {
        return [
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
            'warnings' => [],
            'info' => $total === null ? [] : ['totalCount' => $total],
            'messageCodes' => [],
            'errorCodes' => [],
            'warningCodes' => [],
            'infoCodes' => [],
        ];
    }

    /**
     * An answer recorded on the key (stage 3, §13.1), scrubbed: `tests/Fixtures/api/<name>.json`.
     *
     * @return array<string, mixed>
     */
    protected static function fixture(string $name): array
    {
        $body = json_decode((string) file_get_contents(__DIR__.'/Fixtures/api/'.$name.'.json'), true);
        self::assertIsArray($body, $name);

        /** @var array<string, mixed> $body */
        return $body;
    }

    /** @return list<array<string, mixed>> */
    protected function journalEntries(): array
    {
        return $this->app->make(Journal::class)->entries();
    }
}
