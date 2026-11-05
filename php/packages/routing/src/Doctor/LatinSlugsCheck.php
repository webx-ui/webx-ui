<?php

declare(strict_types=1);

namespace WebxUi\Routing\Doctor;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Routing\LatinSlugs;

/**
 * Slugs saved before the rule: an address a feed cannot carry as it stands. Silent when there are
 * none — which is every site that never had one.
 */
final class LatinSlugsCheck implements Check
{
    public function __construct(private readonly LatinSlugs $slugs) {}

    public function run(): array
    {
        $count = $this->slugs->count();

        if ($count === 0) {
            return [];
        }

        return [Diagnosis::warn('Slugs', $count.' slug(s) are not in ASCII. See them with `php artisan webx:slugs:latin`, move them with --apply: the old addresses redirect.')];
    }
}
