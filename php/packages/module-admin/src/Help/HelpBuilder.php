<?php

declare(strict_types=1);

namespace WebxUi\Admin\Help;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Screens\Tree;

/**
 * The help of one screen, ready to draw: what the files say, with the labels, the hints and the
 * tabs taken from the tree (WEBX_UI_PANEL_HELP.md §3.2).
 *
 * The tree is the one the form gets, through the same `can` and the same translator, so a field
 * this administrator does not see is not in their help either, and a label is always the words
 * of the form. A description of a field that is not there is simply not shown.
 *
 * @phpstan-type HelpField array{id: string, label: string|null, hint: string|null, required: bool, body: string|null}
 * @phpstan-type HelpGroup array{id: string, label: string|null, fields: list<HelpField>}
 */
final readonly class HelpBuilder
{
    /** The group of fields outside any tab. Not a node id: those have no underscore in front. */
    private const string LOOSE = '_screen';

    public function __construct(
        private HelpRegistry $help,
        private ScreenRegistry $screens,
        private Repository $config,
    ) {}

    /**
     * Null when nobody owns the key.
     *
     * @param  callable(string): bool  $can
     * @param  callable(string): string  $translate
     * @return array{key: string, title: string, locale: string, about: string, howto: list<array{title: string, body: string}>, groups: list<HelpGroup>, guide: string|null}|null
     */
    public function build(string $key, string $locale, callable $can, callable $translate): ?array
    {
        $page = $this->help->page($key, $locale);

        if ($page === null) {
            return null;
        }

        $groups = $this->groups($page, $can, $translate);

        // One level: a form in a dialog has no head of its own, and nothing includes a page that
        // includes another. Its groups come after the screen's own, under its title.
        foreach ($page->include as $name) {
            $included = $this->help->page($name, $locale);

            foreach ($included === null ? [] : $this->groups($included, $can, $translate) as $group) {
                $groups[] = [
                    ...$group,
                    // Ids are unique within a screen, and two screens now share one list of tabs.
                    'id' => $name.'.'.$group['id'],
                    'label' => $group['label'] ?? $included?->title,
                ];
            }
        }

        return [
            'key' => $page->key,
            'title' => $page->title,
            'locale' => $page->locale,
            'about' => $page->about,
            'howto' => $page->howto,
            'groups' => $groups,
            'guide' => $this->guide($page),
        ];
    }

    /**
     * @param  callable(string): bool  $can
     * @param  callable(string): string  $translate
     * @return list<HelpGroup>
     */
    private function groups(HelpPage $page, callable $can, callable $translate): array
    {
        if (! $this->screens->has($page->key)) {
            return $this->wordsOfTheFile($page);
        }

        $groups = [];

        $this->collect($this->screens->render($page->key, $can, $translate), $page, self::LOOSE, null, $groups);

        return array_values($groups);
    }

    /**
     * A page with no tree has nothing to take a label from, so the heading in the file is the
     * label, as it stands (§2.3).
     *
     * @return list<HelpGroup>
     */
    private function wordsOfTheFile(HelpPage $page): array
    {
        $fields = [];

        foreach ($page->fields as $label => $body) {
            $fields[] = ['id' => (string) $label, 'label' => (string) $label, 'hint' => null, 'required' => false, 'body' => $body];
        }

        return $fields === [] ? [] : [['id' => self::LOOSE, 'label' => null, 'fields' => $fields]];
    }

    /**
     * Not {@see Tree::fields()}: that walk stops at a named node, and the fields of a repeater's
     * row are described like any other (§2.3). A condition in `visible` does not count, because
     * help has no values to judge it by; `false` is a node no form ever shows.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, HelpGroup>  $groups
     */
    private function collect(array $nodes, HelpPage $page, string $group, ?string $label, array &$groups): void
    {
        foreach ($nodes as $node) {
            if (($node['visible'] ?? true) === false) {
                continue;
            }

            if (($node['type'] ?? null) === 'wx-tab') {
                $this->collect(Tree::children($node), $page, (string) ($node['id'] ?? ''), self::words($node['label'] ?? null), $groups);

                continue;
            }

            if (is_string($node['name'] ?? null) && $node['name'] !== '') {
                $id = (string) ($node['id'] ?? '');

                $groups[$group] ??= ['id' => $group, 'label' => $label, 'fields' => []];
                $groups[$group]['fields'][] = [
                    'id' => $id,
                    'label' => self::words($node['label'] ?? null),
                    'hint' => self::words($node['help'] ?? null),
                    'required' => ($node['required'] ?? false) === true,
                    'body' => $page->field($id),
                ];
            }

            $this->collect(Tree::children($node), $page, $group, $label, $groups);
        }
    }

    /**
     * The address of the guide, and only on a site that asked for it: the guides are written for
     * developers in English, and a manager is not who they are for (§3.2).
     */
    private function guide(HelpPage $page): ?string
    {
        $base = $this->config->get('webx-admin.docs_url');

        if ($page->guide === null || ! is_string($base) || $base === '') {
            return null;
        }

        return rtrim($base, '/').'/guide/'.$page->guide;
    }

    private static function words(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
