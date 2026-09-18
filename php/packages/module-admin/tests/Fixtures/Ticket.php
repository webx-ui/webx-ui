<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Notes\HasNotes;
use WebxUi\Admin\Notes\Notable;

/**
 * A record a module would hang notes off — and, being a fixture, the only one this package
 * has: `module-admin` owns the feed and nothing it can write notes on.
 *
 * @property int $id
 * @property string $subject
 */
final class Ticket extends Model implements Notable
{
    use HasNotes;

    protected $table = 'tickets';

    protected $guarded = [];

    public function notesPermission(): string
    {
        return 'tickets.update';
    }
}
