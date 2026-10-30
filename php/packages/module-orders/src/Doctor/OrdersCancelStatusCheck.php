<?php

declare(strict_types=1);

namespace WebxUi\Orders\Doctor;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Orders\Data\StatusData;
use WebxUi\Orders\InitialStatuses;

/**
 * A cancellation moves the order into the first active status that cancels (spec §9.1, decision
 * 88). Without one the order is cancelled but stays where it stood — «New» to the buyer — which
 * the panel mends: a status with the mark «Cancelled», switched on.
 */
final readonly class OrdersCancelStatusCheck implements Check
{
    private const string SUBJECT = 'Orders: cancellation';

    public function __construct(private InitialStatuses $statuses) {}

    public function run(): array
    {
        $status = $this->statuses->cancelling();

        if ($status === null) {
            return [Diagnosis::content(self::SUBJECT, 'no active status sets the mark "Cancelled": a cancelled order stays in its status, and the buyer does not see it cancelled — switch one on or add one in "Orders → Statuses".')];
        }

        return [Diagnosis::ok(self::SUBJECT, sprintf('a cancelled order comes into "%s".', StatusData::of($status, (string) config('app.locale'))->name))];
    }
}
