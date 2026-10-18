<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Admin\Remote\HostResolver;
use WebxUi\Admin\Testing\FakeHostResolver;
use WebxUi\Catalog\Exchange\ExchangeFiles;
use WebxUi\Catalog\Exchange\ExchangeRun;
use WebxUi\Catalog\Models\ProductImage;

/**
 * An address the catalogue downloads — an import by URL, a picture by link — reaches the public
 * internet and nothing else, and what comes back is shown only when it is what was asked for.
 */
final class RemoteAddressTest extends TestCase
{
    private FakeHostResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->app->instance(HostResolver::class, $this->dns = new FakeHostResolver);
    }

    #[Test]
    public function an_import_never_reaches_inside_the_network(): void
    {
        Http::fake(['*' => static fn () => Http::response("sku,price\nA-1,1\n")]);
        $this->dns->answer('intranet.example.com', ['10.0.0.7']);

        foreach ([
            'http://127.0.0.1/prices.csv',
            'http://169.254.169.254/latest/meta-data/',
            'http://[::1]:8080/prices.csv',
            'https://intranet.example.com/prices.csv',
        ] as $url) {
            $this->inspect($url)->assertStatus(422)->assertJsonPath('errors.url.0', fn (string $error): bool => str_contains($error, 'not on the public internet'));
            $this->import($url)->assertStatus(422);
        }

        $this->inspect('ftp://files.example.com/prices.csv')->assertStatus(422);
        $this->inspect('file:///etc/passwd')->assertStatus(422);

        Http::assertNothingSent();
        $this->assertSame(0, ExchangeRun::query()->count());
    }

    #[Test]
    public function a_redirect_into_the_network_is_not_followed(): void
    {
        Http::fake([
            'https://files.example.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
            '169.254.169.254/*' => Http::response("sku,price\nsecret,1\n"),
        ]);

        $this->inspect('https://files.example.com/prices.csv')
            ->assertStatus(422)
            ->assertJsonPath('errors.url.0', fn (string $error): bool => str_contains($error, 'not on the public internet'));

        Http::assertSentCount(1);
    }

    #[Test]
    public function the_queue_checks_the_address_again_when_it_downloads(): void
    {
        // Public when the import was started, private when the queue came to it: rebinding.
        Http::fake(['*' => static fn () => Http::response("sku,price\nA-1,1\n")]);
        $this->product('Sofa', null, ['sku' => 'A-1']);

        $this->import('https://rebind.example.com/prices.csv', ['dry_run' => true])->assertOk();

        $this->dns->answer('rebind.example.com', ['127.0.0.1']);
        $this->import('https://rebind.example.com/prices.csv')->assertStatus(422);

        $run = ExchangeRun::query()->create([
            'direction' => 'import', 'format' => 'csv', 'options' => ['key' => 'sku'], 'mapping' => ['sku' => 'sku'],
            'dry_run' => false, 'status' => ExchangeRun::QUEUED, 'source' => 'https://rebind.example.com/prices.csv',
        ]);

        try {
            $this->app->make(ExchangeFiles::class)->download((string) $run->source);
            $this->fail('A name that turned private was downloaded.');
        } catch (RuntimeException $refused) {
            $this->assertStringContainsString('not on the public internet', $refused->getMessage());
        }
    }

    #[Test]
    public function an_answer_that_is_not_a_table_is_not_shown(): void
    {
        Http::fake([
            'https://files.example.com/page' => static fn () => Http::response('<!doctype html><title>Admin</title><p>token=abc123</p>', 200, ['Content-Type' => 'text/html']),
            'https://files.example.com/sneaky.csv' => static fn () => Http::response('<html><body>token=abc123</body></html>', 200, ['Content-Type' => 'text/csv']),
            'https://files.example.com/binary.csv' => static fn () => Http::response("\x89PNG\r\n\x1a\n\0\0\0token=abc123", 200, ['Content-Type' => 'image/png']),
            'https://files.example.com/denied.csv' => static fn () => Http::response('token=abc123', 403),
        ]);

        foreach (['page', 'sneaky.csv', 'binary.csv', 'denied.csv'] as $path) {
            $answer = $this->inspect("https://files.example.com/{$path}")->assertStatus(422);

            $this->assertStringNotContainsString('abc123', (string) $answer->getContent(), $path);
            $this->assertNull($answer->json('data'));
        }
    }

    #[Test]
    public function a_file_larger_than_the_exchange_takes_is_refused(): void
    {
        config()->set('webx-catalog.exchange.max_bytes', 1024);
        Http::fake(['*' => static fn () => Http::response("sku,price\n".str_repeat("A-1,1\n", 400))]);

        $this->inspect('https://files.example.com/prices.csv')
            ->assertStatus(422)
            ->assertJsonPath('errors.url.0', fn (string $error): bool => str_contains($error, 'larger than'));
    }

    #[Test]
    public function the_site_may_switch_the_import_by_address_off_or_name_its_hosts(): void
    {
        Http::fake(['*' => static fn () => Http::response("sku,price\nA-1,1\n")]);

        config()->set('webx-catalog.exchange.url_hosts', ['*.supplier.example.com']);
        $this->inspect('https://files.example.com/prices.csv')->assertStatus(422);
        $this->inspect('https://feeds.supplier.example.com/prices.csv')->assertOk()->assertJsonPath('data.header', ['sku', 'price']);

        config()->set('webx-catalog.exchange.url_import', false);
        $this->inspect('https://feeds.supplier.example.com/prices.csv')
            ->assertStatus(422)
            ->assertJsonPath('errors.url.0', fn (string $error): bool => str_contains($error, 'switched off'));
        $this->import('https://feeds.supplier.example.com/prices.csv')->assertStatus(422);

        // An upload is not an address: the switch leaves it alone.
        Http::assertSentCount(1);
    }

    #[Test]
    public function a_picture_by_link_never_reaches_inside_the_network(): void
    {
        $product = $this->product('Sofa');
        Http::fake(['*' => static fn () => Http::response((string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNQUFAAAABSACl1eIV3AAAAAElFTkSuQmCC'), 200, ['Content-Type' => 'image/png'])]);
        $this->dns->answer('intranet.example.com', ['192.168.1.20']);

        foreach (['http://127.0.0.1/a.png', 'http://169.254.169.254/a.png', 'https://intranet.example.com/a.png', 'http://127.0.0.1/clip.mp4'] as $url) {
            $this->actingAs($this->editor(), 'cms')->postJson($this->api("products/{$product->id}/images"), ['url' => $url])->assertStatus(422);
        }

        Http::assertNothingSent();
        $this->assertSame(0, ProductImage::query()->count());

        $this->actingAs($this->editor(), 'cms')->postJson($this->api("products/{$product->id}/images"), ['url' => 'https://images.example.com/a.png'])->assertCreated();
        $this->assertSame(1, ProductImage::query()->count());
    }

    /**
     * @return TestResponse<Response>
     */
    private function inspect(string $url): TestResponse
    {
        return $this->actingAs($this->editor(), 'cms')->postJson($this->api('exchange/inspect'), ['url' => $url]);
    }

    /**
     * @param  array<string, mixed>  $more
     * @return TestResponse<Response>
     */
    private function import(string $url, array $more = []): TestResponse
    {
        return $this->actingAs($this->editor(), 'cms')->postJson($this->api('exchange/import'), [
            'url' => $url,
            'mapping' => ['sku' => 'sku', 'price' => 'price'],
            ...$more,
        ]);
    }
}
