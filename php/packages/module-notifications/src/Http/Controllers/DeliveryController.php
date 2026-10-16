<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Refusal;

/**
 * The delivery log (§10.4): the newest first, narrowed by channel, event, status and period, and
 * a failed row sent again. The values of the events are in it — what visitors typed — which is
 * why reading it is a permission of its own.
 */
final class DeliveryController
{
    public function __construct(
        private readonly NotificationEvents $events,
        private readonly Notifications $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Delivery::query()->orderByDesc('id');

        foreach (['channel' => 'channel_id', 'subject_id' => 'subject_id'] as $parameter => $column) {
            if (is_numeric($request->query($parameter))) {
                $query->where($column, (int) $request->query($parameter));
            }
        }

        foreach (['event' => 'event', 'status' => 'status', 'subject_type' => 'subject_type', 'provider' => 'provider'] as $parameter => $column) {
            if (is_string($value = $request->query($parameter)) && $value !== '') {
                $query->where($column, $value);
            }
        }

        foreach (['since' => '>=', 'until' => '<='] as $parameter => $operator) {
            if (is_string($value = $request->query($parameter)) && $value !== '') {
                try {
                    $query->where('created_at', $operator, Carbon::parse($value)->setTimezone((string) config('app.timezone')));
                } catch (Throwable) {
                    throw ValidationException::withMessages([$parameter => (string) __('webx-notifications::errors.date', ['value' => $value])]);
                }
            }
        }

        if (! $request->boolean('tests', true)) {
            $query->where('is_test', false);
        }

        $page = $query->paginate(min(100, max(1, (int) $request->query('per_page', '25'))));

        return new JsonResponse([
            ...$page->toArray(),
            'data' => array_map(fn (Delivery $delivery): array => $this->row($delivery), $page->items()),
            'environment' => app()->environment(),
        ]);
    }

    public function retry(Delivery $delivery): JsonResponse
    {
        try {
            $delivery = $this->notifications->retry($delivery);
        } catch (Refusal $refusal) {
            return ApiResponse::message($refusal->getMessage(), 422);
        }

        return ApiResponse::data($this->row($delivery));
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Delivery $delivery): array
    {
        $event = $this->events->find($delivery->event);

        return [
            'id' => (int) $delivery->id,
            'channel_id' => $delivery->channel_id,
            'channel_name' => $delivery->channel_name,
            'provider' => $delivery->provider,
            'event' => $delivery->event,
            'event_label' => $event === null ? null : (string) __($event->label),
            'subject_type' => $delivery->subject_type,
            'subject_id' => $delivery->subject_id,
            'subject_url' => $event?->urlFor($delivery->values()),
            'locale' => $delivery->locale,
            'recipient' => $delivery->recipient,
            'recipient_label' => $delivery->recipient_label,
            'status' => $delivery->status,
            'reason' => $delivery->reason,
            'error' => $delivery->error,
            'attempts' => $delivery->attempts,
            'is_test' => $delivery->is_test,
            'truncated' => $delivery->truncated,
            'unknown_variables' => $delivery->unknown_variables ?? [],
            'variables' => $delivery->variables ?? [],
            'retryable' => $delivery->retryable() && ! $delivery->is_test && $delivery->channel_id !== null,
            'created_at' => $delivery->created_at?->toAtomString(),
            'sent_at' => $delivery->sent_at?->toAtomString(),
        ];
    }
}
