<?php

declare(strict_types=1);

namespace WebxUi\Admin\Facades;

use Illuminate\Support\Facades\Facade;
use WebxUi\Admin\History\Journal;

/**
 * @method static \WebxUi\Admin\History\HistoryEntry|null record(\Illuminate\Database\Eloquent\Model $subject, string $event, array<array-key, mixed> $changes = [])
 * @method static \WebxUi\Admin\History\HistoryEntry|null recordFor(string $type, ?int $id, string $event, array<array-key, mixed> $changes = [])
 * @method static mixed run(string $type, array<string, mixed> $summary, \Closure $work, ?string $source = null)
 * @method static bool enabled()
 * @method static \WebxUi\Admin\History\HistoryTypes types()
 *
 * @see Journal
 */
final class History extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Journal::class;
    }
}
