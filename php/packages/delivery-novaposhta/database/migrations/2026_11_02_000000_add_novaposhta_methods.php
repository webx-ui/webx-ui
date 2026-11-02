<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\NovaPoshta\Types\BranchType;
use WebxUi\Delivery\NovaPoshta\Types\CourierType;
use WebxUi\Delivery\NovaPoshta\Types\LockerType;
use WebxUi\Localization\Locales;

/**
 * The three methods of the carrier, switched off (decision 2, §7.1): the shop turns on the ones it
 * needs. Price «by the carrier's rates», Ukraine only, the privilege «delivery paid by the store»
 * honoured. A method already there under its key is left as it is — a second run adds nothing.
 */
return new class extends Migration
{
    /** Method key → type and the kind of points it takes. */
    private const array METHODS = [
        'novaposhta-branch' => [BranchType::KEY, 'branch', 'branch'],
        'novaposhta-locker' => [LockerType::KEY, 'locker', 'locker'],
        'novaposhta-courier' => [CourierType::KEY, 'courier', null],
    ];

    public function up(): void
    {
        $locales = $this->locales();
        $position = (int) DB::table('delivery_methods')->max('position');
        $now = now();

        foreach (self::METHODS as $key => [$type, $kind, $pointKind]) {
            if (DB::table('delivery_methods')->where('key', $key)->exists()) {
                continue;
            }

            $titles = [];
            $descriptions = [];

            foreach ($locales as $locale) {
                $titles[$locale] = NovaPoshtaCarrier::word("types.{$kind}.title", $locale);
                $descriptions[$locale] = NovaPoshtaCarrier::word("types.{$kind}.description", $locale);
            }

            DB::table('delivery_methods')->insert([
                'key' => $key,
                'type' => $type,
                'titles' => json_encode($titles, JSON_UNESCAPED_UNICODE),
                'descriptions' => json_encode($descriptions, JSON_UNESCAPED_UNICODE),
                'point_kind' => $pointKind,
                'countries' => json_encode(['UA']),
                'price' => null,
                'honours_paid_by_store' => true,
                'enabled' => false,
                'position' => ++$position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('delivery_methods')->whereIn('key', array_keys(self::METHODS))->delete();
    }

    /**
     * The site's languages the package has words for; all of them when the site's are not known yet.
     *
     * @return list<string>
     */
    private function locales(): array
    {
        try {
            $site = app(Locales::class)->codes();
        } catch (Throwable) {
            $site = [];
        }

        $known = array_values(array_intersect($site, NovaPoshtaCarrier::LOCALES));

        return $known === [] ? NovaPoshtaCarrier::LOCALES : $known;
    }
};
