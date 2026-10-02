<?php

declare(strict_types=1);

namespace WebxUi\Audit\Contracts;

use WebxUi\Audit\Content\ContentField;
use WebxUi\Audit\Content\ContentRecord;

/**
 * The text a module keeps in the database, handed to the audit (§6, decision 13).
 *
 * The crawl sees what is published; an address of a development stand also lies in drafts,
 * hidden records and fields the template never prints. A module that implements this has its
 * fields searched by the same finder as everyone's; one that does not is listed in the overview
 * as installed without a source.
 */
interface AuditContentSource
{
    /** `pages`, `regions`, `blog.posts` */
    public function id(): string;

    /**
     * Every record, drafts and hidden ones included. Lazily: a catalogue has a hundred thousand.
     *
     * @return iterable<ContentRecord>
     */
    public function records(): iterable;

    /**
     * The text-bearing fields of one record — plain text, HTML, block JSON, URL fields — one per
     * locale where the field is translated.
     *
     * @return iterable<ContentField>
     */
    public function fields(ContentRecord $record): iterable;

    /** Writes a field back through the model, so the history journal sees the change. */
    public function replace(ContentRecord $record, ContentField $field, string $value): void;
}
