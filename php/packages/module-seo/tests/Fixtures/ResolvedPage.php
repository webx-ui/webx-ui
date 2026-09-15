<?php

declare(strict_types=1);

namespace WebxUi\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Something the address registry found, as far as this module is concerned.
 *
 * Never saved and never queried: the only thing a `Resolution` needs is a model, and the only
 * thing a source does with it is read a field. A real entity with addresses lives in whichever
 * content module registered its type — this package will not have one until `module-pages`.
 *
 * @property string $title
 */
final class ResolvedPage extends Model
{
    protected $table = 'resolved_pages';

    protected $guarded = [];
}
