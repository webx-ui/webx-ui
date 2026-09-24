<?php

declare(strict_types=1);

/*
 * A field that points at records of another section (`wx-relations`), and the same choice as a
 * block's filter ("only related to" in `wx-collection`). The keys are the panel's: the English
 * here is what `messages.ts` carries.
 */

return [
    'field-add' => 'Add…',
    'field-searching' => 'Searching…',
    'field-nothing' => 'Nothing found.',
    'field-empty' => 'Nothing chosen yet.',
    'field-remove' => 'Remove',
    'field-drag' => 'Drag to change the order',
    'field-hidden' => 'Not on the site',
    'field-trashed' => 'In the bin',
    'field-missing' => 'Not found',
    'field-full' => 'No more than :max can be chosen.',
    'field-forbidden' => 'You cannot see these records, so the choice cannot be changed here.',
    'collection-related' => 'Only related to',
    'collection-related-to' => 'Only related to “:target”',
    'collection-related-type' => 'Which section',
    'collection-related-any' => 'Not narrowed: every record, related or not.',
    'collection-related-current' => 'The record of the page it stands on',
    'collection-related-current-hint' => 'On the page of a record of “:target” the block shows what is related to it; on any other page it shows nothing.',
];
