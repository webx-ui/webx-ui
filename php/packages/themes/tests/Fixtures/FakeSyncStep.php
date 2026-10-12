<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests\Fixtures;

use WebxUi\Themes\Contracts\SyncStep;
use WebxUi\Themes\Sync\SyncLine;
use WebxUi\Themes\ThemeChain;

/** A step that says what it was given and answers with lines fixed in advance. */
final class FakeSyncStep implements SyncStep
{
    /** @var list<array{bool, bool, list<string>}> */
    public array $calls = [];

    /**
     * @param  list<SyncLine>  $lines
     */
    public function __construct(private readonly array $lines) {}

    public function title(): string
    {
        return 'Fake step';
    }

    public function sync(ThemeChain $chain, bool $force, bool $dryRun, array $adopt = []): array
    {
        $this->calls[] = [$force, $dryRun, $adopt];

        return $this->lines;
    }
}
