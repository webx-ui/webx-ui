<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Blocks\HasBlocks;

/**
 * @property int $id
 * @property string $title
 * @property list<array<string, mixed>>|null $blocks
 * @property array<string, mixed>|null $draft
 */
final class Page extends Model
{
    use HasBlocks;

    protected $table = 'pages';

    protected $guarded = [];
}
