<?php

declare(strict_types=1);

namespace WebxUi\Admin\Manifest;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Admin\Contracts\BrandingSource;
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
        private readonly ?BrandingSource $brand = null,
    ) {}

    /**
     * @return array{
     *     title: string,
     *     branding: array{logo: array{url: string, width: int|null, height: int|null}|null, mark: array{url: string, width: int|null, height: int|null}|null},
     *     path: string,
     *     apiPath: string,
     *     locale: string,
     *     locales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     panelLocales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     groups: list<array{id: string, title: string, icon: string|null, order: int}>,
     *     modules: list<array<string, mixed>>,
     *     screens: list<string>,
     * }
     */
    public function build(): array
    {
        $brand = $this->brand?->branding() ?? new Branding;

        return [
            // The name the client gave the panel, falling back to the one it was deployed with.
            // It stays in the payload even when there is a logo: it is what the corner shows
            // until the picture loads, what it shows if the picture is gone, and its alt.
            'title' => $this->title($brand),
            'branding' => $brand->toArray(),
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
     * A blank name is no name: a setting saved and then cleared leaves an empty string behind,
     * and an empty corner is worse than the deployed title.
     */
    private function title(Branding $brand): string
    {
        $given = trim($brand->title ?? '');

        return $given === '' ? (string) $this->config->get('webx-admin.title') : $given;
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
     * The navigation groups, translated: `webx-admin.groups` maps an id to a title key, an icon
     * and an order, and a module names the id.
     *
     * The icon is optional and stays `null` when nobody named one — the front end then draws
     * the branch with the picture it has always drawn, so a group written before this existed
     * looks exactly as it looked.
     *
     * @return list<array{id: string, title: string, icon: string|null, order: int}>
     */
    private function groups(): array
    {
        $configured = $this->config->get('webx-admin.groups', []);
        $groups = [];

        foreach (is_array($configured) ? $configured : [] as $id => $group) {
            $title = is_array($group) ? (string) ($group['title'] ?? $id) : (string) $group;
            $icon = is_array($group) && isset($group['icon']) ? (string) $group['icon'] : null;

            $groups[] = [
                'id' => (string) $id,
                'title' => (string) __($title),
                'icon' => $icon,
                'order' => is_array($group) ? (int) ($group['order'] ?? 0) : 0,
            ];
        }

        usort($groups, static fn (array $a, array $b): int => [$a['order'], $a['id']] <=> [$b['order'], $b['id']]);

        return $groups;
    }
}
