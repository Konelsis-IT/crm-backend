<?php

declare(strict_types=1);

// Settings > Units and the purchasing unit select (D-194, B54).
return [
    'label' => 'Unit',
    'plural' => 'Units',
    'nav' => 'Units',
    'field' => 'Unit',
    'fields' => [
        'name' => 'Name',
        'symbol' => 'Symbol',
        'dimension' => 'Type',
        'status' => 'Status',
    ],
    'help' => [
        'name' => 'The name shown in the selects and documents, for example Metre.',
        'symbol' => 'The short form, for example m. It appears next to the name in the list.',
        'locked' => 'The unit of the material / service; it comes from the item.',
        'catalog_required' => 'The unit is set when the item is created; purchasing lines take it automatically.',
        'deactivate' => 'An inactive unit leaves the selects; records that use it keep showing it.',
        'activate' => 'The unit returns to the selects.',
    ],
    'filters' => [
        'dimension' => 'Type',
        'status' => 'Status',
    ],
    'actions' => [
        'create' => 'Add unit',
        'deactivate' => 'Deactivate',
        'activate' => 'Activate',
    ],
    'messages' => [
        'deactivated' => 'The unit was deactivated.',
        'activated' => 'The unit was activated.',
    ],
    'values' => [
        'inactive_suffix' => '(inactive)',
    ],
    'empty' => 'No units yet.',
];
