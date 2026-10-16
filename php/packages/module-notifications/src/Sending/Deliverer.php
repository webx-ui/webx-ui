<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Sending;

use Illuminate\Support\Carbon;
use Throwable;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\NotificationEvents;
use WebxUi\Notifications\Providers;

/**
 * One attempt at the rows of one channel (§8.2): each queued recipient in turn — sent, failed for
 * good, or left queued for the next attempt.
 *
 * The channel is read as it is now, not as it was when the event happened: a token replaced in
 * between is the one used, and a channel switched off in between sends nothing (but tests).
 */
final class Deliverer
{
    public function __construct(
        private readonly Providers $providers,
        private readonly NotificationEvents $events,
        private readonly Renderer $renderer,
    ) {}

    /**
     * Try every queued row once. What is left queued failed for a reason another attempt may
     * change; the answer is the last such failure, to wait as long as it asks.
     *
     * @param  iterable<Delivery>  $deliveries
     */
    public function attempt(?Channel $channel, iterable $deliveries): ?DeliveryFailed
    {
        $temporary = null;

        foreach ($deliveries as $delivery) {
            if ($delivery->status !== Delivery::QUEUED) {
                continue;
            }

            $failure = $this->one($channel, $delivery);

            if ($failure !== null && ($temporary === null || ($failure->retryAfter ?? 0) > ($temporary->retryAfter ?? 0))) {
                $temporary = $failure;
            }
        }

        return $temporary;
    }

    /**
     * After the last attempt: whatever is still queued has failed, with the words of its last try.
     *
     * @param  iterable<Delivery>  $deliveries
     */
    public function giveUp(iterable $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            if ($delivery->status === Delivery::QUEUED) {
                $delivery->forceFill([
                    'status' => Delivery::FAILED,
                    'error' => $delivery->error ?? (string) __('webx-notifications::errors.gave-up'),
                ])->save();
            }
        }
    }

    private function one(?Channel $channel, Delivery $delivery): ?DeliveryFailed
    {
        $reason = match (true) {
            $channel === null, $channel->trashed() => 'channel_deleted',
            ! $channel->enabled && ! $delivery->is_test => 'channel_disabled',
            ! $this->providers->has($channel->provider) => 'provider_missing',
            default => null,
        };

        if ($reason !== null) {
            $delivery->forceFill(['status' => Delivery::SKIPPED, 'reason' => $reason])->save();

            return null;
        }

        /** @var Channel $channel */
        $provider = $this->providers->find($channel->provider);
        $delivery->attempts++;

        if ($provider === null || ! $channel->readable()) {
            $delivery->forceFill(['status' => Delivery::FAILED, 'error' => (string) __('webx-notifications::errors.unreadable')])->save();

            return null;
        }

        $credentials = $channel->secrets();

        try {
            $rendered = $this->renderer->render($channel, $provider, $delivery->values(), $delivery->locale, $this->events->find($delivery->event));

            $delivery->forceFill([
                'unknown_variables' => $rendered['unknown'] === [] ? null : $rendered['unknown'],
                'truncated' => $rendered['message']->truncated,
            ]);

            $provider->send($rendered['message'], $credentials, $channel->options(), new Recipient($delivery->recipient, $delivery->recipient_label));
        } catch (DeliveryFailed $failed) {
            $delivery->forceFill([
                'status' => $failed->permanent ? Delivery::FAILED : Delivery::QUEUED,
                'error' => $credentials->mask($failed->getMessage()),
            ])->save();

            return $failed->permanent ? null : $failed;
        } catch (Throwable $exception) {
            $failed = new DeliveryFailed($credentials->mask($exception->getMessage()));
            $delivery->forceFill(['error' => $failed->getMessage()])->save();

            return $failed;
        }

        $delivery->forceFill(['status' => Delivery::SENT, 'error' => null, 'sent_at' => Carbon::now()])->save();

        return null;
    }
}
