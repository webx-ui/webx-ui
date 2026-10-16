<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\History\RecordsHistory;
use WebxUi\Localization\HasTranslations;

/**
 * A record with no versions behind it, so the journal is the only place an old value lives: the
 * long text is kept whole, the translated name is cut short like anybody's.
 *
 * @property int $id
 * @property array<string, string>|null $name
 * @property string|null $body
 */
final class Script extends Model
{
    use HasTranslations;
    use RecordsHistory;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    public function translatable(): array
    {
        return ['name'];
    }

    /**
     * @return list<string>
     */
    public function historyKeepsFull(): array
    {
        return ['body'];
    }
}
