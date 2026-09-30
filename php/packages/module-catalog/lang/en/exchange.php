<?php

declare(strict_types=1);

return [
    'columns' => [
        'id' => 'ID',
        'external_id' => 'External ID',
    ],
    'errors' => [
        'forbidden' => 'This needs the :permission permission.',
        'no-source' => 'Give the file: an upload or an address.',
        'upload-missing' => 'There is no such finished upload for the exchange.',
        'not-a-url' => 'Not an http(s) address: :url',
        'too-large' => 'The file is larger than :max MB.',
        'unreachable' => 'The file could not be downloaded: :reason',
        'unknown-format' => 'The exchange reads and writes these files: :known.',
        'option' => 'The setting :name is one of: :allowed.',
        'mapping-unknown' => 'There is no column :code, or it is not yours to write.',
        'mapping-twice' => 'Two columns of the file go into :code.',
        'key-not-mapped' => 'The key column :key has to be one of the columns of the file.',
        'profile-missing' => 'There is no such profile for this direction.',
        'trashed' => 'The product #:id is in «Deleted»: restore it first.',
        'not-an-id' => 'An id is a whole positive number.',
        'not-a-number' => 'Not a number.',
        'not-an-integer' => 'Not a whole number.',
        'not-a-boolean' => 'Yes or no: 1 or 0, yes or no, true or false.',
        'unknown-unit' => 'Not a unit of the catalogue. Known: :known',
        'too-long' => 'Longer than :max characters.',
        'external-id-taken' => 'The product #:id already has this external ID.',
        'category-empty' => 'The path of the category is empty.',
        'category-unknown-id' => 'There is no category #:id.',
        'category-missing' => 'There is no category :path.',
        'category-ambiguous' => 'Several categories are called :name here: :ids. Name one by its #id.',
        'create-forbidden' => 'Creating it needs the :permission permission.',
    ],
];
