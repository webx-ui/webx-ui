<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Audit\Runs\AuditIssue;

/**
 * `hosts.absolute_own` is about what an editor wrote. The site's own templates print absolute
 * addresses everywhere — an entity's link, a card, a library picture — and none of those is
 * anything an editor can fix.
 */
final class AbsoluteOwnTest extends TestCase
{
    private const BASE = 'https://shop.example.com';

    #[Test]
    public function only_an_address_written_into_the_content_is_reported(): void
    {
        $source = new FakeContentSource;
        $source->records = [
            '1' => ['label' => 'Delivery', 'published' => true, 'fields' => [
                'body' => '<p>See <a href="https://shop.example.com/about">about us</a>.</p>',
            ]],
        ];
        $this->app->make(AuditContentSources::class)->register($source);

        $page = '<!doctype html><html lang="en"><head><title>Shop</title></head><body><h1>Shop</h1>'
            // Pasted by an editor: the record above holds it.
            .'<p>See <a href="https://shop.example.com/about">about us</a>.</p>'
            // Printed by the system: a card's link and a library picture.
            .'<a href="https://shop.example.com/services/pruning">Pruning</a>'
            .'<img src="https://shop.example.com/storage/media/ab/photo.webp" alt="Photo">'
            .'</body></html>';

        Http::fake(static function (Request $request) use ($page) {
            return in_array($request->url(), [self::BASE.'/', self::BASE.'/about', self::BASE.'/services/pruning'], true)
                ? Http::response($page, 200, ['Content-Type' => 'text/html; charset=utf-8'])
                : Http::response('Not found', 404);
        });

        $this->artisan('webx:audit:run')->assertSuccessful();

        $urls = AuditIssue::query()
            ->where('check', 'hosts.absolute_own')
            ->get()
            ->flatMap(static fn (AuditIssue $issue): array => array_column($issue->details['table']['rows'] ?? [], 'url'))
            ->unique()
            ->values()
            ->all();

        $this->assertSame([self::BASE.'/about'], $urls);
    }
}
