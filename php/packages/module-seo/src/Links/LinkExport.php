<?php

declare(strict_types=1);

namespace WebxUi\Seo\Links;

use WebxUi\Seo\Models\SeoLinkBlock;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * Every block as the flat file an import reads (§18.4): one line per link, the heading on the
 * first line of its donor. Addresses are the current ones — what the site prints now — so the
 * file sent back to whoever wrote the brief shows where links actually lead.
 */
final class LinkExport
{
    public function __construct(private readonly UrlTargets $targets) {}

    public function write(string $path, string $format): void
    {
        LinkSpreadsheet::write($path, $format, $this->rows());
    }

    /**
     * @return iterable<list<string>>
     */
    public function rows(): iterable
    {
        yield LinkSpreadsheet::COLUMNS;

        foreach (SeoLinkBlock::query()->with('items')->orderBy('id')->lazy() as $block) {
            $donor = $this->targets->address($block->target());
            $first = true;

            foreach ($block->items as $item) {
                yield [$donor, $this->targets->address($item->target()), $item->anchor, $first ? (string) $block->heading : ''];
                $first = false;
            }
        }
    }
}
