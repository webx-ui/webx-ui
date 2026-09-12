<?php

declare(strict_types=1);

namespace WebxUi\Admin\Manifest;

use Illuminate\Contracts\Config\Repository;
use WebxUi\Admin\Contracts\Module;
use WebxUi\Admin\ModuleRegistry;

/**
 * The one request the admin front end makes before it can draw anything: what this panel is
 * called, where its API lives, and which modules it has.
 */
final class ManifestBuilder
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly Repository $config,
    ) {}

    /**
     * @return array{title: string, path: string, apiPath: string, modules: list<array<string, mixed>>}
     */
    public function build(): array
    {
        return [
            'title' => (string) $this->config->get('webx-admin.title'),
            'path' => '/'.ltrim((string) $this->config->get('webx-admin.path'), '/'),
            'apiPath' => '/'.ltrim((string) $this->config->get('webx-admin.api_path'), '/'),
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
