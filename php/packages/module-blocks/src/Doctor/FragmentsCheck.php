<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Doctor;

use Throwable;
use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Blocks\Panel\Usage;
use WebxUi\Blocks\Regions;

/**
 * The shared blocks against the content that places them (§5.1 of the shared blocks spec).
 *
 * The layout's own check (`Regions` of the frame) leaves them alone: the layout does not print a
 * shared block, and looking for it there is a false alarm. What can go unnoticed instead is the
 * opposite — a shared block filled and published that no page holds. A template may still print
 * it with a tag, which only the source knows, so that is said rather than guessed.
 */
final class FragmentsCheck implements Check
{
    public function __construct(
        private readonly Regions $regions,
        private readonly Usage $usage,
    ) {}

    /** @return list<Diagnosis> */
    public function run(): array
    {
        $names = $this->regions->fragments();

        if ($names === []) {
            return [];
        }

        try {
            $placed = $this->usage->fragments();
        } catch (Throwable) {
            // No tables yet: the frame's own checks say so, louder.
            return [];
        }

        $found = [];

        foreach ($names as $name) {
            $count = count($placed[$name] ?? []);

            $found[] = $count > 0
                ? Diagnosis::ok('Regions', "The shared block [{$name}] stands in the content of {$count} record(s).")
                : Diagnosis::content('Regions', "The shared block [{$name}] is placed on no page — unless a template of the site prints it with <x-webx-blocks::region name=\"{$name}\" />, which is not checked. Add the block «Shared block» to a page, or the tag to a template.");
        }

        return $found;
    }
}
