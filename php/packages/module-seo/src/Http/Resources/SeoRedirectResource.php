<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Seo\Models\SeoRedirect;

/**
 * @mixin SeoRedirect
 */
final class SeoRedirectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeoRedirect $redirect */
        $redirect = $this->resource;

        return [
            'id' => $redirect->id,
            'match_type' => $redirect->match_type,
            'pattern' => $redirect->pattern,
            'target' => $redirect->target,
            'status' => $redirect->status,
            'is_active' => $redirect->is_active,
            'hits' => $redirect->hits,
            'last_hit_at' => $redirect->last_hit_at?->toAtomString(),
            // Said here rather than refused on save: the panel marks the row, and the
            // middleware steps over it.
            'is_loop' => $redirect->isLoop(),
            'created_at' => $redirect->created_at?->toAtomString(),
            'updated_at' => $redirect->updated_at?->toAtomString(),
        ];
    }
}
