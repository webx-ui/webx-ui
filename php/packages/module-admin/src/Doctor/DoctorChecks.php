<?php

declare(strict_types=1);

namespace WebxUi\Admin\Doctor;

/**
 * The checks modules add to `webx:doctor`, after the frame's own.
 *
 * What only a module can know about — the catalogue that has outgrown its engine, a queue that
 * stopped moving — belongs in the same command a deploy already runs, not in a second one
 * nobody remembers. A module names its check from its provider:
 *
 *     $this->app->make(DoctorChecks::class)->register(CatalogEngineCheck::class);
 */
final class DoctorChecks
{
    /** @var list<class-string<Check>> */
    private array $checks = [];

    /**
     * @param  class-string<Check>  $check
     */
    public function register(string $check): void
    {
        if (! in_array($check, $this->checks, true)) {
            $this->checks[] = $check;
        }
    }

    /** @return list<class-string<Check>> */
    public function all(): array
    {
        return $this->checks;
    }
}
