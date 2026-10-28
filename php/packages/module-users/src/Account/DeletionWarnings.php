<?php

declare(strict_types=1);

namespace WebxUi\Users\Account;

use Closure;
use WebxUi\Admin\Visitors\Visitor;

/**
 * What a module says before a visitor's account goes (spec §14.1): the wallet — «The balance of
 * 1 250.00 will be lost», the orders — «Order №… will be delivered, but you will not see it in the
 * account». The module of visitors prints them and does not know who speaks.
 */
final class DeletionWarnings
{
    /** @var list<Closure(Visitor): ?string> */
    private array $warnings = [];

    /** @param  Closure(Visitor): ?string  $warning  a sentence in the visitor's language, or null when there is nothing to lose */
    public function register(Closure $warning): void
    {
        $this->warnings[] = $warning;
    }

    /** @return list<string> */
    public function for(Visitor $visitor): array
    {
        $said = [];

        foreach ($this->warnings as $warning) {
            $text = $warning($visitor);

            if (is_string($text) && trim($text) !== '') {
                $said[] = $text;
            }
        }

        return $said;
    }
}
