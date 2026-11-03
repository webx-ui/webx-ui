<?php

declare(strict_types=1);

namespace WebxUi\Payments\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use WebxUi\Payments\Models\PaymentSelection;

/**
 * The buyers' choices of carts that are gone (spec §5): `payment_selections` has no foreign key —
 * carts are pruned by a mass delete that tells nobody — so the night takes the leftovers.
 */
final class SelectionsPruneCommand extends Command
{
    protected $signature = 'webx:payments:selections-prune';

    protected $description = 'Remove the payment choices of carts that no longer exist';

    public function handle(): int
    {
        $deleted = 0;

        PaymentSelection::query()
            ->whereNotExists(static fn (Builder $carts): Builder => $carts->selectRaw('1')->from('carts')->whereColumn('carts.id', 'payment_selections.cart_id'))
            ->select('id')
            ->chunkById(500, static function ($rows) use (&$deleted): void {
                $deleted += PaymentSelection::query()->whereKey($rows->modelKeys())->delete();
            });

        $this->components->info(sprintf('%d payment %s of gone carts removed.', $deleted, $deleted === 1 ? 'choice' : 'choices'));

        return self::SUCCESS;
    }
}
