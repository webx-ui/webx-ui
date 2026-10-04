<?php

declare(strict_types=1);

return [
    'empty' => 'Address, question and answer are all required.',
    'question-long' => 'The question is longer than 1000 characters.',
    'foreign-host' => 'The address is on another site.',
    'duplicate' => 'This question is already on the page.',
    'redirected' => ':from redirects; its target :to is used instead.',
    'unreadable' => 'The file could not be read as CSV or XLSX.',
    'has-faq' => 'This rule has a FAQ, and only a rule for one exact address can keep one. Remove the questions first.',
    'question-missing' => 'The answer has no question.',
    'answer-missing' => 'The question has no answer.',

    // The panel's view of it (§18.5).
    'tab' => 'FAQ',
    'meta' => 'Meta tags',
    'help' => 'The questions of this page. They go into its FAQPage markup and onto the page wherever the template prints the FAQ.',
    'exact-only' => 'Only a rule for one exact address has a FAQ.',
    'question' => 'Question',
    'answer' => 'Answer',
    'add' => 'Add a question',
    'remove' => 'Remove the question',
    'drag' => 'Move',
    'none' => 'No questions yet.',
    'column' => 'FAQ',
    'with-faq' => 'Only with FAQ',
    'import' => 'Import FAQ',
    'export-csv' => 'Export FAQ as CSV',
    'export-xlsx' => 'Export FAQ as XLSX',
    'import-title' => 'Import FAQ',
    'import-help' => 'A CSV or XLSX file with the columns address, question and answer (HTML or text), in the language of the address. An address without an exact rule gets one with empty meta tags.',
    'mode-replace' => 'Replace the FAQ of the addresses in the file',
    'mode-append' => 'Add to the questions that exist',
    'imported' => 'Imported. Addresses: :count',
    'result-addresses' => 'Addresses',
    'result-questions' => 'Questions',
    'result-created' => 'New rules',
    'result-replaced' => 'Replaced',
    'result-appended' => 'Added to',
    'result-errors' => 'Errors',
];
