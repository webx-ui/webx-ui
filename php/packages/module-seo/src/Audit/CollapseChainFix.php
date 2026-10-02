<?php

declare(strict_types=1);

namespace WebxUi\Seo\Audit;

use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\FixPreview;
use WebxUi\Audit\Contracts\AuditFix;

/**
 * `seo.collapse-chain` (audit spec §7, SEO spec §16): every exact redirect of a chain pointed
 * straight at where the chain ends — one hop instead of several.
 *
 * Only the rows of this table: a chain that starts at the web server, at the normalisation or at
 * a mask has nothing here to rewrite, and the fix is not offered for it.
 */
final readonly class CollapseChainFix implements AuditFix
{
    public const ID = 'seo.collapse-chain';

    public function __construct(private RedirectChains $chains) {}

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
        return ['redirects.chain', SeoChecks::REDIRECT_CHAIN];
    }

    public function available(Finding $finding): bool
    {
        return $this->chains->collapsible($this->path($finding)) !== [];
    }

    public function preview(Finding $finding): FixPreview
    {
        $changes = [];

        foreach ($this->chains->collapsible($this->path($finding)) as [$from, $before, $after]) {
            $changes[] = ['label' => $from, 'before' => $before, 'after' => $after, 'edit_url' => '/seo/redirects'];
        }

        return new FixPreview($changes);
    }

    public function apply(Finding $finding): void
    {
        $this->chains->collapse($this->path($finding));
    }

    /** The address the chain starts at, as the table knows addresses: a path and its query. */
    private function path(Finding $finding): string
    {
        $url = (string) $finding->url;
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $query = parse_url($url, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? $path.'?'.$query : $path;
    }
}
