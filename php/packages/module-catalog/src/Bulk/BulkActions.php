<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk;

use InvalidArgumentException;
use WebxUi\Catalog\Parts\PartField;

/**
 * The bulk actions, the core's first and then whatever the satellites add from their providers
 * (§11.4). The panel's selection bar and `catalog_bulk` both list what is here, so a satellite's
 * action is one line in its provider and nothing in the panel.
 */
final class BulkActions
{
    /** @var array<string, BulkAction> */
    private array $actions = [];

    public function register(BulkAction $action): void
    {
        $key = $action->key();

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException("[{$key}] is not a key for a bulk action: lowercase letters, digits and single hyphens.");
        }

        if (isset($this->actions[$key]) && $action::class !== $this->actions[$key]::class) {
            throw new InvalidArgumentException("A bulk action with the key [{$key}] is registered already, by ".$this->actions[$key]::class.'.');
        }

        $this->actions[$key] = $action;
    }

    /**
     * @return array<string, BulkAction>
     */
    public function all(): array
    {
        return $this->actions;
    }

    public function find(string $key): ?BulkAction
    {
        return $this->actions[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->actions);
    }

    /**
     * What the panel and an agent are told about the actions the caller may start.
     *
     * @param  (callable(string): bool)|null  $can  null — everything, as on the local stdio server
     * @return list<array{key: string, label: string, permission: string, trashed: bool, params: list<array<string, mixed>>}>
     */
    public function describe(?callable $can = null): array
    {
        $described = [];

        foreach ($this->actions as $action) {
            if ($can !== null && ! $can($action->permission())) {
                continue;
            }

            $described[] = [
                'key' => $action->key(),
                'label' => $action->label(),
                'permission' => $action->permission(),
                'trashed' => $action->trashed(),
                'params' => array_map(static fn (PartField $field): array => $field->toArray(), $action->params()),
            ];
        }

        return $described;
    }
}
