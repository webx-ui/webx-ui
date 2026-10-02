<?php

declare(strict_types=1);

namespace WebxUi\Seo\Audit;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Contracts\AuditFix;
use WebxUi\Seo\Sitemap\Sitemap;
use WebxUi\Settings\Settings;

/**
 * `seo.robots-sitemap` (audit spec §7): the `Sitemap:` line written into `seo.robots-txt`.
 *
 * The route adds the line by itself to a non-empty setting, so the finding usually means the
 * setting is empty and `/robots.txt` is somebody else's — a file in `public/` or nothing. An
 * empty setting gets the plainest file there is, everything open, with the line; a file in
 * `public/` still wins, and the note says so.
 */
final readonly class RobotsSitemapFix implements AuditFix
{
    public const ID = 'seo.robots-sitemap';

    private const KEY = 'seo.robots-txt';

    public function __construct(
        private Settings $settings,
        private Sitemap $sitemap,
    ) {}

    public function textNamespace(): string
    {
        return 'webx-seo';
    }

    public function id(): string
    {
        return self::ID;
    }

    public function fixes(): array
    {
        return ['robots.no_sitemap', 'robots.missing'];
    }

    public function available(Finding $finding): bool
    {
        return $this->sitemap->enabled() && $this->body() !== $this->current();
    }

    public function preview(Finding $finding): FixPreview
    {
        if (! $this->available($finding)) {
            return new FixPreview;
        }

        $note = file_exists(public_path('robots.txt')) ? (string) __('webx-seo::audit.robots-file-note') : null;

        return new FixPreview([[
            'label' => (string) __('webx-seo::screen.robots-txt'),
            'before' => $this->current(),
            'after' => $this->body(),
            'edit_url' => '/settings',
        ]], $note);
    }

    public function apply(Finding $finding): void
    {
        if ($this->available($finding)) {
            $this->settings->save([self::KEY => $this->body()]);
        }
    }

    private function current(): string
    {
        $value = $this->settings->get(self::KEY);

        return is_string($value) ? trim($value) : '';
    }

    /** The setting with the line — or the line under an open file, when the setting is empty. */
    private function body(): string
    {
        $current = $this->current();

        if (preg_match('/^\s*sitemap\s*:/im', $current) === 1) {
            return $current;
        }

        $body = $current === '' ? "User-agent: *\nDisallow:" : $current;

        return $body."\n\nSitemap: ".$this->sitemap->url();
    }
}
