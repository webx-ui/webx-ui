<?php

declare(strict_types=1);

namespace WebxUi\Audit\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as Unit;
use WebxUi\Audit\Runs\Health;

/**
 * The health in percent (§12): pages without errors, minus site-wide errors and warnings, each
 * check counted once.
 */
final class HealthTest extends Unit
{
    #[Test]
    public function a_clean_site_and_a_run_without_pages_are_whole(): void
    {
        $this->assertSame(100, Health::measure(40, [])['score']);
        $this->assertSame(['score' => 100, 'pages' => 0, 'clean' => 0, 'site_errors' => 0, 'warnings' => 0], Health::measure(0, []));
    }

    #[Test]
    public function a_page_with_errors_is_lost_once_however_many_it_has(): void
    {
        $health = Health::measure(10, [
            self::finding('links.broken', 'error', 1),
            self::finding('title.missing', 'error', 1),
            self::finding('title.missing', 'error', 2),
        ]);

        $this->assertSame(80, $health['score']);
        $this->assertSame(8, $health['clean']);
    }

    #[Test]
    public function a_hundred_pictures_without_alt_are_one_warning(): void
    {
        $findings = [];

        for ($page = 1; $page <= 100; $page++) {
            $findings[] = self::finding('images.alt', 'warning', $page);
        }

        $this->assertSame(98, Health::measure(100, $findings)['score']);
    }

    #[Test]
    public function warnings_alone_cannot_sink_a_working_site(): void
    {
        $findings = [];

        foreach (range(1, 30) as $i) {
            $findings[] = self::finding("check.{$i}", 'warning', null);
        }

        $health = Health::measure(5, $findings);

        $this->assertSame(80, $health['score'], 'The warnings cap at 20 points.');
        $this->assertSame(30, $health['warnings']);
    }

    #[Test]
    public function an_error_of_the_whole_site_weighs_more_than_one_page_and_notices_weigh_nothing(): void
    {
        $health = Health::measure(50, [
            self::finding('host.mirror', 'error', null),
            self::finding('host.mirror', 'error', null),
            self::finding('config.debug', 'error', null),
            self::finding('host.hsts', 'notice', null),
            self::finding('headings.skipped', 'notice', 3),
        ]);

        $this->assertSame(80, $health['score']);
        $this->assertSame(2, $health['site_errors']);
        $this->assertSame(50, $health['clean']);
        $this->assertSame(0, Health::measure(1, [self::finding('a', 'error', 1)])['score'], 'Never below zero.');
    }

    /**
     * @return array{check: string, severity: string, page_id: int|null}
     */
    private static function finding(string $check, string $severity, ?int $page): array
    {
        return ['check' => $check, 'severity' => $severity, 'page_id' => $page];
    }
}
