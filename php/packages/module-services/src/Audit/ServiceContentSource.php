<?php

declare(strict_types=1);

namespace WebxUi\Services\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Audit\Content\ModelContentSource;
use WebxUi\Services\Models\Service;

/**
 * The services' text for the site audit: the lead, the blocks and what the draft holds, so an
 * address of a development stand is found before «Publish» puts it on the site.
 *
 * Registered only when `webx-ui/module-audit` is installed.
 */
final class ServiceContentSource extends ModelContentSource
{
    public function id(): string
    {
        return 'services';
    }

    protected function models(): array
    {
        return ['' => Service::class];
    }

    protected function columns(Model $model): array
    {
        return ['lead', 'blocks'];
    }

    protected function editUrl(Model $model): string
    {
        return '/services/'.$model->getKey();
    }
}
