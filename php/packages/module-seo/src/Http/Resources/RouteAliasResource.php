<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Routing\Aliases\Alias;

/**
 * An address a rename left behind, on its way to the panel.
 *
 * The same shape a manual redirect has where the two overlap — `pattern`, `target`, `status` —
 * so one table can show both without the screen learning two vocabularies. What it does not
 * carry is everything a manual rule can be: these are always exact, always 301, always on.
 *
 * @mixin Alias
 */
final class RouteAliasResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Alias $alias */
        $alias = $this->resource;

        return [
            'id' => $alias->id,
            'locale' => $alias->locale,
            // With the leading slash the panel prints everywhere else; the registry stores the
            // key without one, and the difference belongs here rather than in the screen.
            'pattern' => '/'.$alias->path,
            'target' => $alias->target === null ? null : '/'.$alias->target,
            'url' => $alias->url,
            'target_url' => $alias->targetUrl,
            'entity_type' => $alias->entityType,
            'entity_id' => $alias->entityId,
            'created_at' => $alias->createdAt?->toAtomString(),
        ];
    }
}
