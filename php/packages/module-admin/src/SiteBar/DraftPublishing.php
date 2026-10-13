<?php

declare(strict_types=1);

namespace WebxUi\Admin\SiteBar;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Admin\Versions\HasDraft;

/**
 * Publishing a record that keeps a draft ({@see HasDraft}) — the same two
 * calls the sections' own buttons make: `publish()` with the panel as the source, so the version
 * and the journal say where it came from, and `discardDraft()`.
 */
final class DraftPublishing implements Publishing
{
    public function publish(Model $entity, ?int $authorId): void
    {
        if (! method_exists($entity, 'publish')) {
            throw new NotFoundHttpException;
        }

        $entity->publish($authorId, EntityVersion::SOURCE_PANEL);
    }

    public function discard(Model $entity): void
    {
        if (! method_exists($entity, 'discardDraft')) {
            throw new NotFoundHttpException;
        }

        $entity->discardDraft();
    }
}
