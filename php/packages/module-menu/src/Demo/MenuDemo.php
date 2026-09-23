<?php

declare(strict_types=1);

namespace WebxUi\Menu\Demo;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Localization\Locales;
use WebxUi\Menu\Menus;
use WebxUi\Menu\Models\Menu;
use WebxUi\Menu\Models\MenuItem;

/**
 * A header and a footer made out of the pages the demo has just created (§14).
 *
 * All three kinds of target on one site, because the difference between them is the thing this
 * module is about and the thing a screenshot cannot show: the pages are `entity` items, the
 * documentation link is a `url`, and the footer's group heading points nowhere at all.
 *
 * The pages come out of the journal rather than out of a query, which is what `requires()` buys.
 * Nothing here guesses at a slug, and a site whose tree already held pages of its own gets a
 * header of its own pages rather than a header somebody else wrote.
 *
 * What is not written down is the `menus` row itself, and that is deliberate rather than an
 * oversight: the only rows this creates are the declared ones, a declared menu refuses to be
 * deleted because a template names it — so `--remove` could not undo the entry it would have
 * made. An empty row for a declared menu is exactly what the panel leaves behind the first time
 * somebody opens the section, which is to say it is structure and not content.
 */
final class MenuDemo
{
    /** The morph alias `module-pages` registers, and the one this demo is about. */
    private const PAGES = 'page';

    public function __construct(
        private readonly Filesystem $files,
        private readonly Locales $locales,
        private readonly Menus $menus,
        private readonly LinkSources $sources,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A site where anybody has arranged a menu is a site with menus of its own.
        if (MenuItem::query()->exists()) {
            return;
        }

        $source = $this->sources->find(self::PAGES);

        if (! $source instanceof LinkSource) {
            $ledger->note('the menus were left empty: nothing registered pages as something to link to.');

            return;
        }

        $pages = $ledger->idsOf('pages', $source->model());

        if ($pages === []) {
            $ledger->note('the menus were left empty: the pages demo created nothing to point them at.');

            return;
        }

        $document = $this->read();

        $this->header($pages, is_array($document['header'] ?? null) ? $document['header'] : [], $ledger);
        $this->footer($pages, is_array($document['footer'] ?? null) ? $document['footer'] : [], $ledger);
    }

    /**
     * The pages, and after them one link off the site.
     *
     * The external one takes the look the header declares for a call to action where there is
     * one — a site whose configuration names no second variant gets an ordinary link rather
     * than a class its markup does not divide by.
     *
     * @param  list<int|string>  $pages
     * @param  array<string, mixed>  $document
     */
    private function header(array $pages, array $document, DemoLedger $ledger): void
    {
        $menu = $this->menu('header');

        if (! $menu instanceof Menu) {
            return;
        }

        foreach ($pages as $id) {
            // No label: the item is called what the page is called, in every language the page
            // is translated into, and renaming the page renames the item (§4).
            $this->item($menu, ['target' => 'entity', 'entity_type' => self::PAGES, 'entity_id' => (int) $id], null, $ledger);
        }

        $external = is_array($document['external'] ?? null) ? $document['external'] : [];

        if ($external === []) {
            return;
        }

        $variant = (string) ($external['variant'] ?? 'link');

        $this->item($menu, [
            'target' => 'url',
            'url' => (string) ($external['url'] ?? ''),
            'title' => $this->words($external['title'] ?? null),
            'variant' => in_array($variant, $this->menus->variants('header'), true) ? $variant : 'link',
            'new_tab' => (bool) ($external['new_tab'] ?? false),
            'rel' => is_array($external['rel'] ?? null) ? array_values(array_map(strval(...), $external['rel'])) : null,
        ], null, $ledger);
    }

    /**
     * A group heading with the same pages under it: two levels, which is what a footer is, and
     * the one place a target of `none` is the ordinary answer rather than an unfinished choice.
     *
     * @param  list<int|string>  $pages
     * @param  array<string, mixed>  $document
     */
    private function footer(array $pages, array $document, DemoLedger $ledger): void
    {
        $menu = $this->menu('footer');

        if (! $menu instanceof Menu) {
            return;
        }

        $heading = $this->item($menu, [
            'target' => 'none',
            'is_heading' => true,
            'title' => $this->words($document['heading'] ?? 'About the site'),
        ], null, $ledger);

        foreach ($pages as $id) {
            $this->item($menu, ['target' => 'entity', 'entity_type' => self::PAGES, 'entity_id' => (int) $id], $heading, $ledger);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(Menu $menu, array $attributes, ?MenuItem $parent, DemoLedger $ledger): MenuItem
    {
        $item = new MenuItem(array_filter(
            ['menu_id' => $menu->getKey(), ...$attributes],
            static fn (mixed $value): bool => $value !== null,
        ));

        if ($parent instanceof MenuItem) {
            $item->appendTo($parent);
        } else {
            $item->saveAsRoot();
        }

        $ledger->created($item, $this->describe($item));

        return $item->refresh();
    }

    /**
     * A menu this site actually has — and only one it declared, because that is the only kind
     * whose row this may make without leaving behind an entry `--remove` could not undo.
     */
    private function menu(string $key): ?Menu
    {
        return $this->menus->isDeclared($key) ? $this->menus->ensure($key) : null;
    }

    private function describe(MenuItem $item): string
    {
        $label = $item->getTranslation('title', $this->locale(), false);

        if (is_string($label) && trim($label) !== '') {
            return trim($label);
        }

        return $item->target === 'entity' ? "{$item->entity_type} #{$item->entity_id}" : $item->target;
    }

    /**
     * @return array<string, string>|null
     */
    private function words(mixed $text): ?array
    {
        $text = is_string($text) ? trim($text) : '';

        return $text === '' ? null : [$this->locale() => $text];
    }

    /** The language the demo is written in: one, and the site's own default. */
    private function locale(): string
    {
        return $this->locales->defaultCode();
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $document = json_decode((string) $this->files->get(__DIR__.'/../../resources/demo/menu.json'), true);

        if (! is_array($document)) {
            throw new RuntimeException('menu.json is not a menu document.');
        }

        return $document;
    }
}
