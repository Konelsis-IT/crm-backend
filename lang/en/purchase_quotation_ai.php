<?php

declare(strict_types=1);

// Reading purchase quotations with Konelsis AI (D-192, u-mv0njps9-98cc).
return [
    'actions' => [
        'read' => [
            'label' => 'Fill from documents with Konelsis AI',
            'heading' => 'Fill from documents with Konelsis AI',
            'description' => 'Upload the supplier\'s quotation documents: PDF, Word, Excel or photos. Konelsis AI checks whether each document is a quotation; if it is, it writes the supplier, dates, currency, terms and items into empty fields. Values you entered are kept. Save any unsaved changes on the edit screen first.',
            'files' => 'Documents',
            'files_hint' => 'Up to 10 documents; PDFs and photos up to 10 MB each, 20 MB in total.',
            'submit' => 'Send for reading',
        ],
        'items' => [
            'label' => 'Items Konelsis AI created',
            'heading' => 'Items Konelsis AI created',
            'description' => 'These quotation items were not found in the Materials and Services, so Konelsis AI created them. Add the suitable ones as they are, edit the others, or pick another item from the list.',
            'submit' => 'Save',
            'accept_all' => 'All suitable, add',
        ],
    ],

    'items' => [
        'line' => 'Quotation line',
        'item' => 'Created item',
        'decision' => 'Decision',
        'decisions' => [
            'accept' => 'Suitable, add',
            'edit' => 'Edit and add',
            'relink' => 'Pick another item from the list',
        ],
        'name' => 'Name',
        'kind' => 'Type',
        'category' => 'Category',
        'unit' => 'Unit',
        'brand' => 'Brand',
        'target' => 'Item from the list',
        'later' => 'Lines without a decision stay in the list; you can open it again later.',
        'saved_title' => 'Items saved',
        'saved_body' => ':accepted item(s) added as they are, :edited edited, :relinked line(s) linked to another item.',
        'nothing' => 'No line has a decision.',
    ],

    'notifications' => [
        'started_title' => 'Konelsis AI is reading the documents',
        'started_body' => ':count document(s) sent. When reading ends the fields are filled and the page refreshes.',
        'completed_title' => 'Konelsis AI read the documents',
        'completed_body' => ':documents quotation document(s) read: :filled field(s) filled, :priced item(s) priced, :added item(s) added.',
        'no_quotation_title' => 'No quotation document found',
        'no_quotation_body' => 'None of the uploaded documents is a supplier quotation; the fields were not changed.',
        'failed_title' => 'Konelsis AI could not read the documents',
        'new_items' => ':count new item(s) were created; check them in "Items Konelsis AI created".',
    ],

    'progress' => [
        'title' => 'Konelsis AI is reading the documents',
        'body' => 'Reading :count document(s). When it ends the fields are filled and the page refreshes by itself.',
    ],

    'document' => [
        'quotation_summary' => ':reason (:lines item(s))',
        'currency_kept' => 'The document currency is :document; the quotation stays :current because it already has priced items.',
        'locked' => 'The quotation can no longer be changed, so the fields were not filled.',
        'legacy_format' => 'Old Word / Excel files (.doc / .xls) cannot be read; upload a PDF, .docx or .xlsx.',
        'unsupported' => 'Konelsis AI cannot read this file type; upload a PDF, Word, Excel file or a photo.',
        'too_large' => 'The file is larger than Konelsis AI can read (10 MB per document, 20 MB in total).',
        'too_many' => 'At most 10 documents can be sent in one reading.',
        'missing' => 'The file was not found.',
        'blocked' => 'The file was blocked by the security scan.',
        'empty' => 'The document has no text to read.',
        'unreadable' => 'The file could not be opened or read.',
        'not_sent' => 'The document could not be sent to Konelsis AI.',
        'no_result' => 'Konelsis AI returned no result for this document.',
    ],

    'failure' => [
        'timeout' => 'Konelsis AI did not answer in time; upload the documents again and retry.',
        'service' => 'Konelsis AI could not finish reading; retry or fill in the fields by hand.',
        'limit' => 'Konelsis AI\'s daily reading limit is used up; try again tomorrow.',
        'invalid_result' => 'Konelsis AI\'s answer could not be read; retry.',
    ],
];
