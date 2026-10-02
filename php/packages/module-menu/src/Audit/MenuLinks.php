<?php

declare(strict_types=1);

namespace WebxUi\Menu\Audit;

use WebxUi\Admin\Links\LinkTarget;
use WebxUi\Admin\Links\LinkUrls;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Audit\Contracts\AuditCheck;
use WebxUi\Audit\Runs\AuditPage;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * What the menus bring to the site audit (audit spec §7): an item that leads to an error page
 * or to a redirect. The crawl sees the broken link on every page the menu is printed on; this
 * says which item of which menu it is — and finds the item whose page was deleted, which prints
 * no link at all and so is invisible to the crawl.
 *
 * Read against the crawl's snapshot: a menu item is not requested a second time.
 */
final readonly class MenuLinks implements AuditCheck
{
    public const BROKEN = 'menu.broken';

    public const REDIRECT = 'menu.redirect';

    public function __construct(
        private string $id,
        private LinkUrls $urls,
    ) {}

    public function textNamespace(): string
    {
        return 'webx-menu';
    }

    public function id(): string
    {
        return $this->id;
    }

    public function group(): string
    {
        return 'menu';
    }

    public function severity(): string
    {
        return $this->id === self::BROKEN ? Severity::ERROR : Severity::WARNING;
    }

    public function needs(): array
    {
        return ['crawl'];
    }

    public function run(AuditContext $context): iterable
    {
        $menus = Menu::query()->get()->keyBy('id');
        $base = $context->base();

        foreach (MenuItem::query()->where('visible', true)->where('is_heading', false)->orderBy('menu_id')->orderBy('lft')->get() as $item) {
            $link = $item->link();

            if ($link->target === LinkTarget::None) {
                continue;
            }

            $href = $this->urls->href($link);
            $label = $this->label($item, $menus->get($item->menu_id));

            if ($href === null) {
                // An item for an entity that has no address any more: nothing is printed, so
                // nothing is crawled, and only the menu knows the item is there.
                if ($this->id === self::BROKEN) {
                    yield $this->finding('missing', $label, null, null, $item);
                }

                continue;
            }

            $url = $this->absolute(strtok($href, '#') ?: '/', $base);

            if ($url === null) {
                continue;
            }

            $page = AuditPage::query()->where('run_id', $context->run->id)->where('url_hash', AuditPage::hash($url))->first();

            if ($page === null) {
                continue;
            }

            if ($this->id === self::BROKEN && ($page->status ?? 0) >= 400) {
                yield $this->finding('broken', $label, $url, $page->status, $item);
            } elseif ($this->id === self::REDIRECT && $page->redirect_to !== null && ($page->status ?? 0) < 400) {
                yield $this->finding('redirect', $label, $url, $page->status, $item, $page->redirect_to);
            }
        }
    }

    /** An address of this site, absolute; null for somebody else's. */
    private function absolute(string $href, string $base): ?string
    {
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return $base.$href;
        }

        $host = parse_url($href, PHP_URL_HOST);

        return is_string($host) && strcasecmp($host, (string) parse_url($base, PHP_URL_HOST)) === 0 ? $href : null;
    }

    private function label(MenuItem $item, ?Menu $menu): string
    {
        $title = $item->getTranslation('title');
        $menuTitle = $menu?->getTranslation('title') ?: $menu?->key;
        $name = is_string($title) && $title !== '' ? $title : '#'.$item->id;

        return is_string($menuTitle) && $menuTitle !== '' ? $menuTitle.' → '.$name : $name;
    }

    private function finding(string $summary, string $label, ?string $url, ?int $status, MenuItem $item, ?string $location = null): Finding
    {
        return new Finding($this->id, $this->severity(), $url, [
            'summary' => ['key' => 'webx-menu::audit.'.$summary, 'params' => ['item' => $label, 'status' => $status, 'location' => $location]],
            'table' => [
                'columns' => [Finding::column('record'), Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('edit', 'edit')],
                'rows' => [['record' => $label, 'url' => $url, 'status' => $status, 'edit' => '/menus']],
            ],
        ], (string) $item->id);
    }
}
