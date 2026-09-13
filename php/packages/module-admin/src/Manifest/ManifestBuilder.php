<?php

declare(strict_types=1);

namespace WebxUi\Admin\Manifest;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;
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
    ) {}

    /**
     * @return array{
     *     title: string,
     *     path: string,
     *     apiPath: string,
     *     locale: string,
     *     locales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     panelLocales: list<array{code: string, name: string, nativeName: string, direction: string, default: bool}>,
     *     modules: list<array<string, mixed>>,
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
            'modules' => array_map($this->describe(...), $this->registry->all()),
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
            'permissions' => $module->permissions(),
            // Whatever the module itself wants to say, kept in its own room so it can never
            // shadow the fields above.
            'meta' => $module->manifest(),
        ];
    }
}
