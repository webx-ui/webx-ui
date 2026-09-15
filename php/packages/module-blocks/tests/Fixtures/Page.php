<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Versions\HasDraft;
use WebxUi\Admin\Versions\HasVersions;
use WebxUi\Blocks\HasBlocks;

/**
 * An entity the way `module-pages` will write one: blocks, a draft, a history.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $slug
 * @property list<array<string, mixed>>|null $blocks
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $published_at
 */
class Page extends Model
{
    use HasBlocks;
    use HasDraft;
    use HasVersions;

    protected $table = 'pages';

    protected $guarded = [];
}
