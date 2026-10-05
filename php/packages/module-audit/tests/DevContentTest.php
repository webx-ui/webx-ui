<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Runs\AuditIssue;
use WebxUi\Audit\Runs\AuditRun;
use WebxUi\Audit\Runs\ContentUrl;
use WebxUi\Audit\Runs\Runner;
use WebxUi\Settings\Settings;

/**
 * The case the module is for (decision 12): content filled in on a stand with absolute links,
 * published and in drafts, found in the database before anyone stumbles on it.
 */
final class DevContentTest extends TestCase
{
    private FakeContentSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->source = new FakeContentSource;
        $this->source->records = [
            '1' => [
                'label' => 'Delivery',
                'published' => true,
                'fields' => [
                    'body' => '<p>See <a href="https://dev.shop.example.com/sale">sale</a> and <img src="http://localhost:8000/storage/a.jpg"></p>',
                    'cta' => 'https://shop.example.com/contacts',
                ],
            ],
            '2' => [
                'label' => 'About',
                'published' => false,
                'fields' => ['body' => 'Nothing to see.'],
                'draft' => ['blocks' => '[{"type":"image","values":{"src":"https:\/\/old-agency.net\/x.png"}}]'],
            ],
        ];

        $this->app->make(AuditContentSources::class)->register($this->source);
    }

    #[Test]
    public function stand_addresses_are_found_published_and_in_drafts(): void
    {
        $this->app->make(Settings::class)->save(['audit.other-hosts' => "old-agency.net\n"]);

        $this->artisan('webx:audit:run', ['--quick' => true, '--fail-on' => 'error'])->assertFailed();

        $issues = AuditIssue::query()->where('check', 'hosts.dev_content')->orderBy('id')->get();

        $this->assertCount(2, $issues, 'One finding per field of a record.');

        $first = $issues[0];
        $this->assertSame('error', $first->severity);
        $rows = $first->details['table']['rows'] ?? [];
        $this->assertSame(
            ['dev.shop.example.com', 'localhost'],
            array_map(static fn (array $row): string => (string) parse_url((string) $row['url'], PHP_URL_HOST), $rows),
        );
        $this->assertSame('/posts/1', $first->details['table']['rows'][0]['edit'] ?? null);
        $this->assertTrue($first->details['table']['rows'][0]['published'] ?? null);

        $draft = $issues[1];
        $this->assertSame('https://old-agency.net/x.png', $draft->url);
        $this->assertFalse($draft->details['table']['rows'][0]['published'] ?? null, 'A draft is not on the site yet.');

        // Every class is kept, not just stands: the outgoing hosts screen reads the same rows.
        $this->assertSame('own', ContentUrl::query()->where('url', 'https://shop.example.com/contacts')->value('host_class'));
    }

    #[Test]
    public function a_library_picture_written_on_the_stand_is_not_a_stand_link(): void
    {
        // The site prints these pictures from the key, so the stand's host is in the column and
        // on no page; the link beside them has no key and is printed as written.
        $this->source->records = [
            '4' => [
                'label' => 'Moved',
                'published' => true,
                'fields' => [
                    'body' => '<p><img src="https://dev.shop.example.com/storage/a.webp?v=1" data-wx-path="media/a.webp" alt="">'
                        .'<a data-wx-path="docs/price.pdf" href="https://dev.shop.example.com/storage/price.pdf">Prices</a>'
                        .'<a href="https://dev.shop.example.com/sale">sale</a></p>',
                    'blocks' => '[{"type":"text","values":{"body":"<img src=\"https:\/\/dev.shop.example.com\/storage\/b.webp\" data-wx-path=\"media\/b.webp\">"}}]',
                ],
            ],
        ];

        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $this->assertSame(
            ['https://dev.shop.example.com/sale'],
            ContentUrl::query()->where('host', 'dev.shop.example.com')->pluck('url')->all(),
        );
    }

    #[Test]
    public function the_database_stage_is_done_a_piece_at_a_time(): void
    {
        $runner = $this->app->make(Runner::class);
        $run = $runner->start(AuditRun::QUICK);

        $pieces = 0;

        while ($runner->step($run->refresh(), 0.0)) {
            $pieces++;
            $this->assertLessThan(20, $pieces);
        }

        // Probes, a piece per record with a zero budget, the end of the scan, the analysis.
        $this->assertGreaterThanOrEqual(4, $pieces);
        $this->assertSame(AuditRun::DONE, $run->refresh()->status);
        $this->assertSame(1, AuditIssue::query()->where('check', 'hosts.dev_content')->count());
        $this->assertSame(['posts'], $run->counts['sources']['searched'] ?? null);
    }

    #[Test]
    public function the_next_run_tells_new_from_persisting_and_counts_the_fixed(): void
    {
        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $this->source->records['1'] = ['label' => 'Delivery', 'published' => true, 'fields' => ['body' => 'Fixed.']];
        $this->source->records['3'] = ['label' => 'Fresh', 'published' => true, 'fields' => ['body' => 'https://staging.shop.example.com/']];

        $this->artisan('webx:audit:run', ['--quick' => true])->assertSuccessful();

        $run = AuditRun::query()->orderByDesc('id')->firstOrFail();
        $states = AuditIssue::query()->where('run_id', $run->id)->where('check', 'hosts.dev_content')->pluck('state', 'url')->all();

        $this->assertSame(['https://staging.shop.example.com/' => 'new'], $states);
        $this->assertSame(1, $run->counts['fixed'] ?? null);
        $this->assertSame(1, $run->counts['new'] ?? null);
    }
}
