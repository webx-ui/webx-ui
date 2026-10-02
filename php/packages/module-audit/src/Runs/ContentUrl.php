<?php

declare(strict_types=1);

namespace WebxUi\Audit\Runs;

use Illuminate\Database\Eloquent\Model;

/**
 * An absolute address found in a field of a record in the database (§4,
 * `audit_content_urls`) — every class of host, not just stands: the outgoing hosts screen reads
 * the same rows.
 *
 * @property int $id
 * @property int $run_id
 * @property string $source
 * @property string $record_id
 * @property string|null $record_label
 * @property string $field
 * @property string|null $locale
 * @property string $url
 * @property string $host
 * @property string $host_class
 * @property bool $published
 * @property string|null $edit_url
 */
class ContentUrl extends Model
{
    protected $table = 'audit_content_urls';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published' => 'boolean'];
    }
}
