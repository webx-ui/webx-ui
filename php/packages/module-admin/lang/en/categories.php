<?php

declare(strict_types=1);

/*
 * The words of the categories every module shares (`WebxUi\Admin\Categories`). A module that
 * calls its categories something else — rubrics — says its own refusal instead.
 */

return [
    'in-use' => 'There are :count entries in it. Move them to another category first.',
    'slug-shape' => 'Letters, digits and single hyphens between them.',

    // The shared screens of categories (`categories/` in @webx-ui/module-admin). A module
    // that calls its categories something else hands its own keys in over these.
    'new' => 'New category',
    'empty' => 'No categories yet.',
    'empty-help' => 'A category groups entries. One entry can be in several of them.',
    'order' => 'The order here is the order on the site',
    'hidden' => 'Hidden from the site',
    'no-address' => 'No address in this language',
    'count' => 'Entries: :count',
    'show-items' => 'Show its entries',
    'edit' => 'Edit',
    'open-on-site' => 'Open on the site',
    'delete' => 'Delete',
    'delete-blocked' => 'While it holds entries it cannot go — move them first.',
    'delete-title' => 'Delete “:name”?',
    'delete-text' => 'It goes to the bin and comes off the site, and its address is free again.',
    'deleted' => 'The category is in the bin.',
    'cancel' => 'Cancel',
    'create' => 'Create',
    'save' => 'Save',
    'saved' => 'Saved.',
    'save-failed' => 'Not saved — look at the marked fields.',
    'reorder-failed' => 'The new order was not saved.',
    'field-title' => 'Name',
    'field-slug' => 'Address',
    'address-moving' => 'The address is changing. The old one keeps working and leads to the new one.',
    'untitled' => 'Untitled',
    'trail' => 'Where you are',
    'leave-title' => 'Leave without saving?',
    'leave-text' => 'What was changed here since the last save will be lost.',
    'leave' => 'Leave',
    'field-main' => 'Main',
    'field-add' => 'Add a category',
    'field-remove' => 'Take out of this category',
    'field-empty' => 'In no category yet.',
    'field-none-left' => 'Every category is already chosen.',
    'order-all' => 'Drag to change the order on the site.',
    'order-category' => 'Drag to change the order inside this category. The rest of the list keeps its own.',
    'order-locked' => 'Clear the search and the filters to change the order — only a whole list, or one category, can be dragged.',
    'unknown' => 'One of the chosen categories is not there any more.',
];
