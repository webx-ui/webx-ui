<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Notifications\Channels\ChannelForm;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;

/**
 * The channels of the section (§10.2, §10.5): the list, one channel as its form opens it, a save,
 * the bin, and a copy. No answer carries a secret — only which secrets are set.
 */
final class ChannelController
{
    public function __construct(private readonly ChannelForm $form) {}

    /**
     * Every channel, the newest first; with `trashed`, the bin. `provider` and `event` narrow it,
     * `event=none` to the channels for any event.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Channel::query();

        if ($request->boolean('trashed')) {
            $query->onlyTrashed()->orderByDesc('deleted_at');
        }

        if (is_string($provider = $request->query('provider')) && $provider !== '') {
            $query->where('provider', $provider);
        }

        if (is_string($event = $request->query('event')) && $event !== '') {
            $event === 'none' ? $query->whereNull('event') : $query->where('event', $event);
        }

        $channels = $query->orderByDesc('id')->get();
        $last = $this->lastDeliveries($channels->modelKeys());

        return new JsonResponse([
            'data' => $channels->map(fn (Channel $channel): array => $this->form->row($channel, $last[$channel->id] ?? null))->values()->all(),
            'environment' => app()->environment(),
        ]);
    }

    public function show(Channel $channel): JsonResponse
    {
        return ApiResponse::data($this->form->describe($channel));
    }

    public function store(Request $request): JsonResponse
    {
        $saved = $this->form->save(new Channel, $this->values($request));

        return ApiResponse::data($this->form->describe($saved['channel'], $saved['warnings']), 201);
    }

    /** An empty secret keeps the one saved; `forget` wipes it. */
    public function update(Request $request, Channel $channel): JsonResponse
    {
        $saved = $this->form->save($channel, $this->values($request));

        return ApiResponse::data($this->form->describe($saved['channel'], $saved['warnings']));
    }

    /** Into the bin: sends nothing, comes back into every consumer that names it. */
    public function destroy(Channel $channel): JsonResponse
    {
        $channel->delete();

        return ApiResponse::noContent();
    }

    public function restore(int $channel): JsonResponse
    {
        $found = Channel::query()->onlyTrashed()->findOrFail($channel);
        $found->restore();

        return ApiResponse::data($this->form->describe($found->refresh()));
    }

    /** Gone for good, only from the bin. Its log rows stay, without the link to it. */
    public function purge(int $channel): JsonResponse
    {
        Channel::query()->onlyTrashed()->findOrFail($channel)->forceDelete();

        return ApiResponse::noContent();
    }

    /** `{ name? }` — a copy, switched off, its secrets carried over on the server (§3.4). */
    public function copy(Request $request, Channel $channel): JsonResponse
    {
        $name = $request->input('name');

        return ApiResponse::data($this->form->describe($this->form->copy($channel, is_string($name) ? $name : null)), 201);
    }

    /**
     * The last delivery that went or failed, of each channel, in one query.
     *
     * @param  array<array-key, mixed>  $ids
     * @return array<int, Delivery>
     */
    private function lastDeliveries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $latest = Delivery::query()
            ->selectRaw('max(id) as id')
            ->whereIn('channel_id', $ids)
            ->where('is_test', false)
            ->whereIn('status', [Delivery::SENT, Delivery::FAILED])
            ->groupBy('channel_id');

        return Delivery::query()->whereIn('id', $latest)->get()->keyBy('channel_id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }
}
