<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Inbox\Models\Status;

/**
 * One state a submission can be in.
 *
 * The count comes along because it is the answer to the only question the screen asks about a
 * status it is about to delete, and the panel would rather grey the line out than offer a
 * delete the server will refuse.
 *
 * @mixin Status
 */
final class StatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Status $status */
        $status = $this->resource;

        return [
            'id' => (int) $status->getKey(),
            'key' => $status->key,
            'title' => $status->getTranslations('title'),
            'color' => $status->color,
            'is_default' => $status->is_default,
            'is_spam' => $status->is_spam,
            'is_closed' => $status->is_closed,
            'position' => $status->position,
            'submissions_count' => $status->getAttribute('submissions_count') === null
                ? null
                : (int) $status->getAttribute('submissions_count'),
        ];
    }
}
