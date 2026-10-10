<?php

declare(strict_types=1);

// D-176: "Download all documents" (ZIP with folders), automatic company
// documents (general catalogue) and several documents per potential job item.

return [
    'actions' => [
        'download_all' => 'Download all documents',
        'download_all_help' => 'Every document of this record is downloaded as one ZIP file, sorted into folders.',
        'add_documents' => 'Add documents',
    ],

    'folders' => [
        'general' => 'General documents',
        'archive' => 'Archive',
        'scopes' => 'Scope lists',
        'cost_lists' => 'Cost lists',
        'checklist' => 'Checklist',
        'extra' => 'Additional documents',
        'proposals' => 'Proposals',
        'previous_versions' => 'Previous versions',
        'version' => 'Version :no',
    ],

    'file_suffix' => '- Documents',

    'add' => [
        'heading' => 'Add documents',
        'help' => 'Every selected file is added as a separate document; an item or the additional documents can hold several files. The checklist board shows the first document of an item, the others are listed on this card.',
        'target' => 'Place of the document',
        'extra' => 'Additional document',
        'files' => 'Files',
        'added' => ':count document(s) added.',
    ],

    'card' => [
        'empty' => 'No additional documents. Checklist documents are on the board, proposal documents on the Documents tab of each proposal.',
    ],
];
