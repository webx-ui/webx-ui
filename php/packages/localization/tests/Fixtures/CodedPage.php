<?php

declare(strict_types=1);

namespace WebxUi\Localization\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Localization\HasCodes;

/**
 * A record with a code somebody types: the `code` of a page row, `[a-z][a-z0-9-]*`, 16 long.
 *
 * @property string|null $code
 */
class CodedPage extends Model
{
    use HasCodes;

    protected $table = 'pages';

    protected $guarded = [];

    protected static function codes(): array
    {
        return ['code' => ['max' => 16, 'lead' => 'page']];
    }
}
