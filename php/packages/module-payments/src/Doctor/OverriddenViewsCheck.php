<?php

declare(strict_types=1);

namespace WebxUi\Payments\Doctor;

use Illuminate\View\Factory;
use InvalidArgumentException;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;

/**
 * A theme's copy of a template older than what the buyer's pages now need (spec §8.4) in
 * `webx:doctor`: the copy keeps rendering, it just leaves the buyer with no way to pay or with
 * nothing to read, and no test of the site says so. The file the finder picks is read — the
 * package's own has the word, so only a copy can miss it.
 *
 * Registered only where the panel's frame is installed: payments do not need it.
 */
final class OverriddenViewsCheck implements Check
{
    private const string SUBJECT = 'Payment templates';

    /** The template, the word it cannot do without, and what is lost without it. */
    private const array NEEDS = [
        'webx-orders::order.body' => ['prompt', 'print $order[\'prompt\'], or the buyer gets neither «Awaiting payment» nor the buttons to pay'],
        'webx-payments::pay.page' => ['declined', 'handle the state `declined`, or the buyer the bank turned down reads nothing'],
    ];

    public function __construct(private readonly Factory $views) {}

    public function run(): array
    {
        $found = [];

        foreach (self::NEEDS as $view => [$word, $lost]) {
            try {
                $path = $this->views->getFinder()->find($view);
            } catch (InvalidArgumentException) {
                continue;
            }

            $source = @file_get_contents($path);

            if (is_string($source) && ! str_contains($source, $word)) {
                $found[] = Diagnosis::warn(self::SUBJECT, "{$path} is older than the module: it has to {$lost}. Compare it with the package's template.");
            }
        }

        return $found;
    }
}
