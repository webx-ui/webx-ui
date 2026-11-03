<?php

declare(strict_types=1);

namespace WebxUi\Admin;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use WebxUi\Admin\Contracts\Module;

/**
 * What a module is called where the whole module is meant — the row of the roles' matrix, the
 * consent screen's list of what an agent may reach — rather than one entry of the menu.
 *
 * A section made of several menu entries declares its permissions on the first one, and the
 * first one's title is the title of its entry: «Methods» for both the delivery and the payments.
 * Such a module heads a navigation group of its own id, and the group's title is the module's
 * name («Delivery», «Payments»), so that one is said. Any other module is called by its title.
 */
final class ModuleNames
{
    public function __construct(
        private readonly Repository $config,
        private readonly Translator $translator,
    ) {}

    public function of(Module $module): string
    {
        $group = $module->group();

        if ($group === null || $group !== $module->id()) {
            return $module->title();
        }

        $groups = $this->config->get('webx-admin.groups', []);
        $described = is_array($groups) ? ($groups[$group] ?? null) : null;
        $key = is_array($described) ? ($described['title'] ?? null) : $described;

        if (! is_string($key) || $key === '') {
            return $module->title();
        }

        return (string) $this->translator->get($key);
    }
}
