<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Bulk\Actions;

use WebxUi\Catalog\Bulk\BulkAction;
use WebxUi\Catalog\Parts\PartField;

/**
 * What the core's actions have in common: words from the module's dictionary under their key,
 * `catalog.manage`, the live products, nothing asked.
 */
abstract class CoreAction implements BulkAction
{
    public function label(): string
    {
        return (string) __('webx-catalog::bulk.actions.'.$this->key());
    }

    public function permission(): string
    {
        return 'catalog.manage';
    }

    public function trashed(): bool
    {
        return false;
    }

    /**
     * @return list<PartField>
     */
    public function params(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
