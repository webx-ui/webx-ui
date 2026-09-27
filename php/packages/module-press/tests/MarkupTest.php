<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\Test;

/**
 * schema.org on an outlet's page (decision 14): an `ItemList` of the articles seen there, each an
 * `Article` published by the outlet — `datePublished` only for a date known to the day.
 */
final class MarkupTest extends TestCase
{
    #[Test]
    public function the_page_lists_its_articles_published_by_the_outlet(): void
    {
        $logo = $this->file('media/ab/cd/tatler.png', 'image/png');

        $this->outlet('Tatler', [
            $this->row('To the day', ['published_on' => '2023-08-12', 'excerpt' => ['en' => 'About.']]),
            $this->row('To the month', ['published_on' => '2023-08-12', 'date_precision' => 'month']),
            $this->row('Hidden', ['is_hidden' => true]),
        ], values: ['website_url' => 'https://tatler.example', 'logo' => ['path' => $logo->path]]);

        $list = $this->jsonLd((string) $this->get('/press/tatler')->assertOk()->getContent(), 'ItemList');

        $this->assertSame('Tatler', $list['name']);
        $this->assertCount(2, $list['itemListElement']);

        [$first, $second] = array_column($list['itemListElement'], 'item');

        $this->assertSame('Article', $first['@type']);
        $this->assertSame('To the day', $first['headline']);
        $this->assertSame('https://news.example/to-the-day', $first['url']);
        $this->assertSame('2023-08-12', $first['datePublished']);
        $this->assertSame('About.', $first['description']);
        $this->assertSame('Organization', $first['publisher']['@type']);
        $this->assertSame('Tatler', $first['publisher']['name']);
        $this->assertSame('https://tatler.example', $first['publisher']['url']);
        $this->assertStringContainsString('tatler.png', $first['publisher']['logo']);

        $this->assertArrayNotHasKey('datePublished', $second, 'a month is not a date');
        $this->assertSame(2, $list['itemListElement'][1]['position']);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonLd(string $page, string $type): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches);

        foreach ($matches[1] as $json) {
            $decoded = json_decode($json, true);

            if (is_array($decoded) && ($decoded['@type'] ?? null) === $type) {
                return $decoded;
            }
        }

        $this->fail("No {$type} on the page.");
    }
}
