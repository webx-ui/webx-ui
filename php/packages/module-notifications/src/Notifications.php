<?php

declare(strict_types=1);

namespace WebxUi\Notifications;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stringable;
use Throwable;
use WebxUi\Notifications\Events\EventDefinition;
use WebxUi\Notifications\Jobs\Deliver;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\Sending\Deliverer;
use WebxUi\Notifications\Sending\Recipient;

/**
 * What a consumer calls (§8.1): an event happened, these are its values, send it to these
 * channels.
 *
 *     app(Notifications::class)->send(
 *         event: 'inbox.submission',
 *         channels: $form->notifyChannels(),
 *         variables: ['form' => 'Contact us', 'fields' => "Name: Alex", 'reply_to' => $email],
 *         subject: $submission,
 *         locale: $submission->locale,
 *     );
 *
 * The values are taken now — the job renders the template later, but from what was true when the
 * event happened. Every recipient gets a row in the log at once: queued, or skipped with the
 * reason a channel could not take it. Nothing is thrown: a submission must not fail because a
 * notification did.
 */
final class Notifications
{
    public function __construct(
        private readonly Providers $providers,
        private readonly NotificationEvents $events,
        private readonly Deliverer $deliverer,
        private readonly Config $config,
    ) {}

    /**
     * @param  iterable<int|string>  $channels  ids; deleted, switched off and foreign ones are logged as skipped
     * @param  array<string, mixed>  $variables
     */
    public function send(string $event, iterable $channels, array $variables, ?Model $subject = null, ?string $locale = null): Dispatch
    {
        $queued = [];
        $skipped = [];
        $failed = [];
        $values = self::values($variables);
        $ids = [];

        foreach ($channels as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[(int) $id] = true;
            }
        }

        foreach (array_keys($ids) as $id) {
            try {
                $rows = $this->rowsFor($id, $event, $values, $subject, $locale);
            } catch (Throwable $exception) {
                Log::warning("Notifications: [{$event}] could not be logged for channel {$id}. ".$exception->getMessage());

                continue;
            }

            $waiting = [];

            foreach ($rows as $row) {
                if ($row->status === Delivery::QUEUED) {
                    $waiting[] = (int) $row->id;
                } else {
                    $skipped[] = (int) $row->id;
                }
            }

            if ($waiting === []) {
                continue;
            }

            try {
                $this->dispatch(new Deliver($id, $waiting));
                $queued = [...$queued, ...$waiting];
            } catch (Throwable $exception) {
                Delivery::query()->whereKey($waiting)->update(['status' => Delivery::FAILED, 'error' => mb_substr($exception->getMessage(), 0, 1000)]);
                Log::warning("Notifications: [{$event}] could not be queued for channel {$id}. ".$exception->getMessage());
                $failed = [...$failed, ...$waiting];
            }
        }

        return new Dispatch($queued, $skipped, $failed);
    }

    /**
     * Send a channel's message now, past the queue, with the event's example values (§8.5): what
     * the button "Send a test" does. The channel may be switched off and need not be saved — the
     * form's values are tried before they are kept. Addressees of the event are left out: a test
     * has no visitor to write to.
     *
     * @return list<array{recipient: string, label: string|null, status: string, reason: string|null, error: string|null, truncated: bool, unknown_variables: list<string>}>
     */
    public function test(Channel $channel, ?string $only = null): array
    {
        $provider = $this->providers->find($channel->provider);
        $event = $channel->event === null ? null : $this->events->find($channel->event);
        $values = $event?->examples() ?? [];
        $results = [];

        if ($provider === null) {
            return [];
        }

        $recipients = array_values(array_filter($channel->recipientList(), static fn (array $one): bool => ! isset($one['variable'])));

        foreach ($provider->resolve($recipients, $values) as $recipient) {
            if ($only !== null && $only !== '' && $recipient->key !== $only) {
                continue;
            }

            $row = $this->row($channel, $channel->event ?? 'test', $values, null, null, $recipient);
            $row->forceFill(['is_test' => true])->save();

            if ($row->status === Delivery::QUEUED) {
                $this->deliverer->attempt($channel, [$row]);
                $this->deliverer->giveUp([$row]);
            }

            $results[] = [
                'recipient' => $row->recipient,
                'label' => $row->recipient_label,
                'status' => $row->status,
                'reason' => $row->reason,
                'error' => $row->error,
                'truncated' => $row->truncated,
                'unknown_variables' => $row->unknown_variables ?? [],
            ];
        }

        return $results;
    }

    /**
     * Send a failed or stuck row again (§8.4): the same values, the template as it is now. No new
     * row — the attempts grow and the status goes back to queued.
     */
    public function retry(Delivery $delivery): Delivery
    {
        if (! $delivery->retryable() || $delivery->is_test || $delivery->channel_id === null) {
            throw new Refusal((string) __('webx-notifications::errors.not-retryable', ['id' => $delivery->id]));
        }

        $delivery->forceFill(['status' => Delivery::QUEUED, 'reason' => null, 'error' => null])->save();
        $this->dispatch(new Deliver($delivery->channel_id, [(int) $delivery->id]));

        return $delivery->refresh();
    }

    /**
     * The log of what was sent about a record — a submission's notifications, say.
     *
     * @return Builder<Delivery>
     */
    public function deliveriesFor(Model $subject): Builder
    {
        return Delivery::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->orderByDesc('id');
    }

    /**
     * How the job leaves (§8.3): through the queue, or after the response when there is no worker
     * to take it — a `sync` queue in `auto`, or `after_response` outright.
     */
    public function mode(): string
    {
        $mode = (string) $this->config->get('webx-notifications.queue.mode', 'auto');

        if ($mode === 'queue' || $mode === 'after_response') {
            return $mode;
        }

        return $this->driver() === 'sync' ? 'after_response' : 'queue';
    }

    /** The driver of the queue connection the module sends through. */
    public function driver(): string
    {
        return (string) $this->config->get('queue.connections.'.$this->connection().'.driver', 'sync');
    }

    public function connection(): string
    {
        return (string) ($this->config->get('webx-notifications.queue.connection') ?: $this->config->get('queue.default', 'sync'));
    }

    private function dispatch(Deliver $job): void
    {
        if ($this->mode() === 'after_response') {
            $job->inline = true;
            dispatch($job)->afterResponse();

            return;
        }

        $job->onConnection($this->connection())->afterCommit();
        $queue = $this->config->get('webx-notifications.queue.name');

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        dispatch($job);
    }

    /**
     * The rows of one channel for one event: a queued one per recipient it can reach, a skipped
     * one per recipient it cannot, or one skipped row when the channel itself does not send.
     *
     * @param  array<string, string>  $values
     * @return list<Delivery>
     */
    private function rowsFor(int $id, string $event, array $values, ?Model $subject, ?string $locale): array
    {
        $channel = Channel::query()->withTrashed()->find($id);

        if ($channel === null) {
            return [$this->skipped(null, '#'.$id, '', $event, $values, $subject, $locale, 'channel_deleted')];
        }

        $provider = $this->providers->find($channel->provider);
        $reason = match (true) {
            $channel->trashed() => 'channel_deleted',
            ! $channel->enabled => 'channel_disabled',
            $channel->event !== null && $channel->event !== $event => 'wrong_event',
            $provider === null => 'provider_missing',
            default => null,
        };

        if ($reason !== null || $provider === null) {
            return [$this->skipped($channel, $channel->name, $channel->provider, $event, $values, $subject, $locale, $reason)];
        }

        $recipients = $provider->resolve($channel->recipientList(), $values);

        if ($recipients === []) {
            return [$this->skipped($channel, $channel->name, $channel->provider, $event, $values, $subject, $locale, 'no_recipients')];
        }

        return array_map(fn (Recipient $recipient): Delivery => $this->row($channel, $event, $values, $subject, $locale, $recipient), $recipients);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function row(Channel $channel, string $event, array $values, ?Model $subject, ?string $locale, Recipient $recipient): Delivery
    {
        $row = new Delivery([
            'channel_id' => $channel->exists ? $channel->id : null,
            'channel_name' => mb_substr($channel->name, 0, 160),
            'provider' => $channel->provider,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'locale' => $locale,
            'recipient' => mb_substr($recipient->key, 0, 255),
            'recipient_label' => $recipient->label === null ? null : mb_substr($recipient->label, 0, 255),
            'status' => $recipient->skip === null ? Delivery::QUEUED : Delivery::SKIPPED,
            'reason' => $recipient->skip,
            'variables' => $values,
            'created_at' => Carbon::now(),
        ]);

        $row->save();

        return $row;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function skipped(?Channel $channel, string $name, string $provider, string $event, array $values, ?Model $subject, ?string $locale, string $reason): Delivery
    {
        $row = new Delivery([
            'channel_id' => $channel?->id,
            'channel_name' => mb_substr($name, 0, 160),
            'provider' => $provider,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'locale' => $locale,
            'recipient' => '',
            'status' => Delivery::SKIPPED,
            'reason' => $reason,
            'variables' => $values,
            'created_at' => Carbon::now(),
        ]);

        $row->save();

        return $row;
    }

    /**
     * The values as strings: what a template can print.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, string>
     */
    public static function values(array $variables): array
    {
        $values = [];

        foreach ($variables as $name => $value) {
            $values[(string) $name] = match (true) {
                $value === null => '',
                is_bool($value) => $value ? '1' : '0',
                is_scalar($value), $value instanceof Stringable => (string) $value,
                default => '',
            };
        }

        return $values;
    }

    /** The event a channel is for, if it is declared. */
    public function eventOf(Channel $channel): ?EventDefinition
    {
        return $channel->event === null ? null : $this->events->find($channel->event);
    }
}
