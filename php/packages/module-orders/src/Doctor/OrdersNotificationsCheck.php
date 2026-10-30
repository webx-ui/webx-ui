<?php

declare(strict_types=1);

namespace WebxUi\Orders\Doctor;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Notifications\OrderEvents;

/**
 * The letters about orders in the command a deploy already runs (spec §10, decisions 29 and 81):
 * a site without `webx-ui/module-notifications` writes none — a choice it may make, so a note —
 * and a channel of a new order that has nobody to write to, which the panel mends.
 */
final readonly class OrdersNotificationsCheck implements Check
{
    private const string SUBJECT = 'Orders: notifications';

    public function __construct(private OrderChannels $channels) {}

    public function run(): array
    {
        if (! $this->channels->available()) {
            return [Diagnosis::note(self::SUBJECT, 'webx-ui/module-notifications is not installed: nobody is told of a new order, a payment or a public status — neither the buyer nor the shop.')];
        }

        $ids = $this->channels->forEvent(OrderEvents::PLACED);
        $found = [];

        foreach (Channel::query()->whereKey($ids)->orderBy('id')->get() as $channel) {
            if ($channel->recipientList() === []) {
                $found[] = Diagnosis::content(self::SUBJECT, sprintf(
                    'the channel "%s" of a new order has no recipient%s — add an e-mail to the contacts of the site or to the channel in "Notifications".',
                    $channel->name,
                    $channel->enabled ? '' : ' and is switched off',
                ));
            }
        }

        if ($ids === []) {
            $found[] = Diagnosis::note(self::SUBJECT, 'no channel is told of a new order — choose them in the settings of the orders.');
        }

        return $found === [] ? [Diagnosis::ok(self::SUBJECT, 'every channel of a new order has somebody to write to.')] : $found;
    }
}
