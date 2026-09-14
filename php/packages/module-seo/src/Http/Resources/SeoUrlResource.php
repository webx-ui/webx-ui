<?php

declare(strict_types=1);

namespace WebxUi\Seo\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Seo\Http\Requests\SeoUrlRequest;
use WebxUi\Seo\Models\SeoUrl;

/**
 * A rule as the panel edits it.
 *
 * Every language of every text field, not the resolved one: this is a form, and a form that
 * showed one language would quietly drop the others on the next save.
 *
 * @mixin SeoUrl
 */
final class SeoUrlResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SeoUrl $rule */
        $rule = $this->resource;

        $payload = [
            'id' => $rule->id,
            'match_type' => $rule->match_type,
            'pattern' => $rule->pattern,
            'priority' => $rule->priority,
            'og_image' => $this->image($rule),
            'canonical' => $rule->canonical,
            'robots' => $rule->robots,
            'json_ld' => $rule->json_ld,
            'is_active' => $rule->is_active,
            'created_at' => $rule->created_at?->toAtomString(),
            'updated_at' => $rule->updated_at?->toAtomString(),
        ];

        foreach (SeoUrlRequest::TRANSLATED as $field) {
            $payload[$field] = (object) $rule->getTranslations($field);
        }

        return $payload;
    }

    /**
     * The stored key with the address beside it, so the card can show a preview without asking
     * the library a second time.
     *
     * @return array<string, mixed>|null
     */
    private function image(SeoUrl $rule): ?array
    {
        $stored = $rule->og_image;

        if (! is_array($stored) || $stored === []) {
            return null;
        }

        return $stored + ['url' => $rule->imageUrl()];
    }
}
