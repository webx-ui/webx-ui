<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Notifications;

use Illuminate\Contracts\Container\Container;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Notifications;

/**
 * The inbox's side of `webx-ui/module-notifications` (NOTIFICATIONS §15): "Notify through" on a
 * form, the sending, and what came of it on a submission.
 *
 * The dependency is soft. The package is a `suggest`, so every method answers for a site without
 * it first — nothing, nobody, false — and only then names a class of it. `available()` is the one
 * question: the package is there, its provider ran, and the event is declared. A site that has the
 * code in `vendor` but not the provider (a package discovered away) is a site without it.
 *
 * The letter of the form (`options.recipients`, `Mail\Notifier`) does not go through here: it is
 * the inbox's own and works beside the channels, with or without them (decision 11).
 */
final class Channels
{
    public const OPTION = 'notify_channels';

    public function __construct(private readonly Container $container) {}

    public function available(): bool
    {
        return class_exists(NotificationEvents::class)
            && $this->container->bound(NotificationEvents::class)
            && $this->container->make(NotificationEvents::class)->has(SubmissionEvent::KEY);
    }

    /**
     * The ids a form keeps, as stored: whole, positive, each once.
     *
     * @return list<int>
     */
    public static function ids(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $ids = [];

        foreach ($input as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $ids[(int) $id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * What a form may keep: the channels that exist, in the bin included — a channel put in the
     * bin comes back from it, and until then the log says why nothing went. Without the module the
     * list is kept as it came: removing the package must not wipe a choice somebody made.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function existing(array $ids): array
    {
        if ($ids === [] || ! $this->available()) {
            return $ids;
        }

        $known = Channel::query()->withTrashed()->whereKey($ids)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        return array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $known, true)));
    }

    /**
     * The chosen channels, named — for the form's editor, the audit and an agent.
     *
     * @return list<array{id: int, name: string|null, provider: string|null, enabled: bool, deleted: bool, sends: bool}>
     */
    public function describe(Form $form): array
    {
        $ids = self::ids($form->option(self::OPTION));

        if ($ids === [] || ! $this->available()) {
            return [];
        }

        $channels = Channel::query()->withTrashed()->whereKey($ids)->get()->keyBy('id');
        $described = [];

        foreach ($ids as $id) {
            $channel = $channels->get($id);
            $deleted = $channel === null || $channel->trashed();
            $wrong = $channel !== null && $channel->event !== null && $channel->event !== SubmissionEvent::KEY;

            $described[] = [
                'id' => $id,
                'name' => $channel?->name,
                'provider' => $channel?->provider,
                'enabled' => (bool) $channel?->enabled,
                'deleted' => $deleted,
                'sends' => ! $deleted && ! $wrong && (bool) $channel?->enabled,
            ];
        }

        return $described;
    }

    /** Whether a channel of the form would send anything — what makes a form "notify" for the audit. */
    public function notifies(Form $form): bool
    {
        return in_array(true, array_column($this->describe($form), 'sends'), true);
    }

    /**
     * Tell the form's channels about a submission. The number of channels asked; nothing is thrown —
     * `send()` logs a channel that cannot take it as skipped.
     */
    public function send(Submission $submission): int
    {
        $submission->loadMissing('form');
        $ids = self::ids($submission->form?->option(self::OPTION));

        if ($ids === [] || ! $this->available()) {
            return 0;
        }

        $meta = is_array($submission->meta) ? $submission->meta : [];

        $this->container->make(Notifications::class)->send(
            SubmissionEvent::KEY,
            $ids,
            SubmissionEvent::values($submission),
            subject: $submission,
            locale: is_string($meta['locale'] ?? null) ? $meta['locale'] : null,
        );

        return count($ids);
    }

    /**
     * The log of the channels about one submission, newest first — the card's "Channels" block.
     *
     * @return list<array{id: int, channel_id: int|null, channel: string, provider: string, recipient: string, recipient_label: string|null, status: string, status_label: string, reason: string|null, reason_label: string|null, error: string|null, attempts: int, retryable: bool, created_at: string|null, sent_at: string|null}>
     */
    public function deliveries(Submission $submission): array
    {
        if (! $this->available() || ! $submission->exists) {
            return [];
        }

        return $this->container->make(Notifications::class)->deliveriesFor($submission)
            ->where('is_test', false)
            ->limit(100)
            ->get()
            ->map(static fn (Delivery $row): array => [
                'id' => (int) $row->id,
                'channel_id' => $row->channel_id,
                'channel' => $row->channel_name,
                'provider' => $row->provider,
                'recipient' => $row->recipient,
                'recipient_label' => $row->recipient_label,
                'status' => $row->status,
                // In the notifications module's own words: the card shows them as they are.
                'status_label' => (string) __('webx-notifications::module.statuses.'.$row->status),
                'reason' => $row->reason,
                'reason_label' => $row->reason === null ? null : (string) __('webx-notifications::module.reasons.'.$row->reason),
                'error' => $row->error,
                'attempts' => (int) $row->attempts,
                'retryable' => $row->retryable() && $row->channel_id !== null,
                'created_at' => $row->created_at?->toAtomString(),
                'sent_at' => $row->sent_at?->toAtomString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Send again what failed or is stuck (§8.4). The ids of the rows sent again; with `$dryRun`,
     * the ones that would be.
     *
     * @return list<int>
     */
    public function retry(Submission $submission, bool $dryRun = false): array
    {
        if (! $this->available()) {
            return [];
        }

        $notifications = $this->container->make(Notifications::class);
        $retried = [];

        foreach ($notifications->deliveriesFor($submission)->where('is_test', false)->get() as $row) {
            if (! $row->retryable() || $row->channel_id === null) {
                continue;
            }

            if (! $dryRun) {
                $notifications->retry($row);
            }

            $retried[] = (int) $row->id;
        }

        return $retried;
    }
}
