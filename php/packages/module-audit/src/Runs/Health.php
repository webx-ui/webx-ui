<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use WebxUi\Audit\Checks\Severity;

/**
 * The health of a run in percent (§12, decided): the share of crawled pages without an error,
 * minus a penalty for what is wrong with the site as a whole and for warnings.
 *
 * - **Pages.** A page with at least one error finding is a lost page, however many errors it
 *   has. A run without pages (quick) starts from 100.
 * - **Site errors.** An error that belongs to no page — a mirror that does not redirect, debug
 *   mode in production, a stand's address in the database — touches every page at once, so each
 *   such check takes {@see SITE_ERROR} points.
 * - **Warnings.** Each check with a warning takes {@see WARNING} points, counted once and not per
 *   address, and all of them together at most {@see WARNINGS_CAP}: a hundred pictures without
 *   `alt` are one warning, and warnings alone cannot sink a site that works.
 *
 * Notices weigh nothing. Hidden findings are not passed in.
 */
final class Health
{
    public const SITE_ERROR = 10;

    public const WARNING = 2;

    public const WARNINGS_CAP = 20;

    /**
     * @param  iterable<array{check: string, severity: string, page_id: int|null}>  $findings
     * @return array{score: int, pages: int, clean: int, site_errors: int, warnings: int}
     */
    public static function measure(int $pages, iterable $findings): array
    {
        $broken = [];
        $siteErrors = [];
        $warnings = [];

        foreach ($findings as $finding) {
            if ($finding['severity'] === Severity::ERROR) {
                if ($finding['page_id'] !== null) {
                    $broken[$finding['page_id']] = true;
                } else {
                    $siteErrors[$finding['check']] = true;
                }
            } elseif ($finding['severity'] === Severity::WARNING) {
                $warnings[$finding['check']] = true;
            }
        }

        $clean = max(0, $pages - count($broken));
        $share = $pages === 0 ? 100.0 : 100 * $clean / $pages;
        $penalty = self::SITE_ERROR * count($siteErrors) + min(self::WARNINGS_CAP, self::WARNING * count($warnings));

        return [
            'score' => (int) max(0, round($share - $penalty)),
            'pages' => $pages,
            'clean' => $clean,
            'site_errors' => count($siteErrors),
            'warnings' => count($warnings),
        ];
    }
}
