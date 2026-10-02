<?php

declare(strict_types=1);

namespace WebxUi\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Audit\Runs\AuditRun;

/**
 * @mixin AuditRun
 */
final class RunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditRun $run */
        $run = $this->resource;

        return [
            'id' => $run->id,
            'status' => $run->status,
            'scope' => $run->scope,
            'base_url' => $run->base_url,
            'resolve_to' => $run->resolve_to,
            'progress' => [
                'stage' => $run->progress['stage'] ?? null,
                'done' => $run->progress['done'] ?? [],
                'checks' => count($run->progress['checks'] ?? []),
                'pages' => ['crawled' => $run->pages_crawled, 'limit' => $run->pages_limit],
            ],
            'counts' => $run->counts,
            'started_by' => $run->started_by,
            'created_at' => $run->created_at?->toAtomString(),
            'started_at' => $run->started_at?->toAtomString(),
            'finished_at' => $run->finished_at?->toAtomString(),
            'error' => $run->error,
        ];
    }
}
