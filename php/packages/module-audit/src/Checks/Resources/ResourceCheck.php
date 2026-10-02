<?php

declare(strict_types=1);

namespace WebxUi\Audit\Checks\Resources;

use Illuminate\Database\Eloquent\Builder;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\Page\LinkCheck;
use WebxUi\Audit\Runs\AuditLink;
use WebxUi\Audit\Runs\AuditResource;

/**
 * A check over what the pages load or where they lead elsewhere, by the answer of stage 5: one
 * finding per page, its addresses in the table with their codes.
 */
abstract class ResourceCheck extends LinkCheck
{
    protected const TO = 'resource';

    /**
     * The links whose resource matches.
     *
     * @param  list<string>  $kinds  Kinds of `audit_links`.
     * @param  callable(Builder<AuditResource>): mixed  $resource
     * @return Builder<AuditLink>
     */
    protected function withResource(AuditContext $context, array $kinds, callable $resource): Builder
    {
        return $this->query($context)->whereIn('kind', $kinds)->whereHas('resource', $resource);
    }

    /**
     * 4xx, 5xx, or asked and nothing answered.
     *
     * @param  Builder<AuditResource>  $query
     * @return Builder<AuditResource>
     */
    protected static function broken(Builder $query): Builder
    {
        return $query->whereNotNull('checked_at')->where(
            static fn (Builder $bad) => $bad->where('status', '>=', 400)->orWhereNull('status'),
        );
    }

    protected function columns(): array
    {
        return [Finding::column('url', 'url'), Finding::column('status', 'status'), Finding::column('error')];
    }

    protected function row(AuditLink $link): array
    {
        $resource = $link->resource;

        return [
            'url' => $link->to_url,
            'kind' => $link->kind,
            'anchor' => $link->anchor,
            'status' => $resource?->status,
            'error' => $resource?->error,
            'location' => $resource?->location,
            'kb' => $resource?->bytes === null ? null : (int) round($resource->bytes / 1024),
            'type' => $resource?->content_type,
        ];
    }
}
