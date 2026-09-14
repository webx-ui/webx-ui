<?php

declare(strict_types=1);

namespace WebxUi\Admin\Manifest;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Localization\Locales;

/**
 * The one request the admin front end makes before it can draw anything: what this panel is
 * called, where its API lives, which languages it works in, and which modules it has.
 */
final class ManifestBuilder
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Repository $config,
        private readonly Locales $locales,
        private readonly ScreenRegistry $screens,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     path: string,
     *     apiPath: string,
     *     locale: string,
     *     locales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     panelLocales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     groups: list<array{id: string, title: string, order: int}>,
     *     modules: list<array<string, mixed>>,
     *     screens: list<string>,
     * }
     */
    public function build(): array
    {
        return [
            'title' => (string) $this->config->get('webx-admin.title'),
            'path' => '/'.ltrim((string) $this->config->get('webx-admin.path'), '/'),
            'apiPath' => '/'.ltrim((string) $this->config->get('webx-admin.api_path'), '/'),
            // The language this administrator reads the panel in — their own choice, not the
            // site's, so two people can work on one site in two languages.
            'locale' => $this->locales->resolvePanel($this->locales->current()),
            // The languages content is written in. Every editing screen is built around this
            // list: one tab, one column or one card per entry.
            'locales' => $this->locales->toPayload(),
            // What the interface itself can be switched to.
            'panelLocales' => $this->locales->panel(),
            'groups' => $this->groups(),
            'modules' => array_map($this->describe(...), $this->registry->all()),
            // Only the names: a screen travels on its own, when the page that needs it opens.
            'screens' => $this->screens->names(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Module $module): array
    {
        return [
            'id' => $module->id(),
            'title' => $module->title(),
            'icon' => $module->icon(),
            'order' => $module->order(),
            'group' => $module->group(),
            'permissions' => $module->permissions(),
            // Whatever the module itself wants to say, kept in its own room so it can never
            // shadow the fields above.
            'meta' => $module->manifest(),
        ];
    }

    /**
     * The navigation groups, translated: `webx-admin.groups` maps an id to a title key and an
     * order, and a module names the id.
     *
     * @return list<array{id: string, title: string, order: int}>
     */
    private function groups(): array
    {
        $configured = $this->config->get('webx-admin.groups', []);
        $groups = [];

        foreach (is_array($configured) ? $configured : [] as $id => $group) {
            $title = is_array($group) ? (string) ($group['title'] ?? $id) : (string) $group;

            $groups[] = [
                'id' => (string) $id,
                'title' => (string) __($title),
                'order' => is_array($group) ? (int) ($group['order'] ?? 0) : 0,
            ];
        }

        usort($groups, static fn (array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);

        return $groups;
    }
}
