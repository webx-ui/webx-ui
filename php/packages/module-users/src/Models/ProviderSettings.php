<?php

declare(strict_types=1);

namespace WebxUi\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Admin\History\RecordsHistory;

/**
 * The keys of one social network as an administrator filled them in (spec §3 `users_providers`).
 *
 * `credentials` is encrypted with the application key and hidden from every serialisation, as
 * the channels of `module-notifications` keep theirs: a secret goes into the provider and nowhere
 * else — not a page, not the panel, not MCP, not the history.
 *
 * @property string $key
 * @property bool $enabled
 * @property array<string, mixed>|null $options
 * @property array<string, mixed>|null $credentials
 * @property Carbon|null $updated_at
 */
class ProviderSettings extends Model
{
    use RecordsHistory;

    /** The journal's type (§16): the secret as «replaced», never its value. */
    public const string TYPE = 'users.provider';

    public const null CREATED_AT = null;

    protected $table = 'users_providers';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'enabled', 'options', 'credentials'];

    protected $hidden = ['credentials'];

    /**
     * @return list<string>
     */
    public function historyMasked(): array
    {
        return ['credentials'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'options' => 'array',
            'credentials' => 'encrypted:array',
        ];
    }
}
