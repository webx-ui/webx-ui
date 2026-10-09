<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Dom\HTMLDocument;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Audit\Contracts\AuditPageReader;
use WebxUi\Audit\Crawl\PageReaders;
use WebxUi\Audit\Runs\AuditPage;

/**
 * §7: a module reads its own facts off a crawled page while the parser has it — the page's HTML
 * is not kept — and finds them on the page's snapshot under its id.
 */
final class PageReadersTest extends TestCase
{
    #[Test]
    public function a_module_reads_the_page_whole_and_finds_its_facts_on_the_snapshot(): void
    {
        $readers = $this->app->make(PageReaders::class);
        $readers->register(new class implements AuditPageReader
        {
            public function id(): string
            {
                return 'counters';
            }

            public function read(HTMLDocument $document, string $url): array
            {
                // Scripts and templates are still there: the text is taken after the readers.
                return ['scripts' => $document->querySelectorAll('script')->length, 'url' => $url];
            }
        });
        $readers->register(new class implements AuditPageReader
        {
            public function id(): string
            {
                return 'nothing';
            }

            public function read(HTMLDocument $document, string $url): array
            {
                return [];
            }
        });
        $readers->register(new class implements AuditPageReader
        {
            public function id(): string
            {
                return 'broken';
            }

            public function read(HTMLDocument $document, string $url): array
            {
                throw new RuntimeException('A bug of the module.');
            }
        });

        $reported = [];
        $this->app->make(ExceptionHandler::class)->reportable(static function (RuntimeException $exception) use (&$reported): bool {
            $reported[] = $exception->getMessage();

            return false;
        });

        Http::fake(['*' => Http::response('<!doctype html><html lang="en"><head><title>Home</title><script src="/a.js"></script></head><body><h1>Home</h1><script>var b</script></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->artisan('webx:audit:run')->assertSuccessful();

        $home = AuditPage::query()->where('url', 'https://shop.example.com/')->firstOrFail();
        $this->assertSame(['scripts' => 2, 'url' => 'https://shop.example.com/'], $home->fact('counters'));
        $this->assertNull($home->fact('nothing'), 'Nothing read, nothing stored.');
        $this->assertNull($home->fact('broken'));
        $this->assertContains('A bug of the module.', $reported, 'Reported, and the crawl went on.');
        $this->assertSame(1, (int) $home->word_count);
    }
}
