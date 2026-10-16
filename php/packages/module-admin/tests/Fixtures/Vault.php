<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\History\RecordsHistory;

/**
 * A record with secrets: encrypted, so the journal would skip them, and masked, so it says that
 * they changed without saying them.
 *
 * @property int $id
 * @property array<string, string>|null $token
 */
final class Vault extends Model
{
    use RecordsHistory;

    protected $table = 'products';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['token' => 'encrypted:array'];

    /**
     * @return list<string>
     */
    public function historyMasked(): array
    {
        return ['token'];
    }
}
