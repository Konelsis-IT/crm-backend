<?php

return [
    'label' => 'Approval policy',
    'plural' => 'Approval Policies',

    'sections' => [
        'main' => 'Policy details',
    ],

    'fields' => [
        'code' => 'Code',
        'name_tr' => 'Name (TR)',
        'name_en' => 'Name (EN)',
        'subject_type' => 'Subject type',
        'current_version' => 'Published version',
        'status' => 'Status',
    ],

    'help' => [
        'main' => 'A policy defines the approval flow for a subject type (e.g. document revision). Steps live in versions; a published version never changes.',
        'code' => 'Upper case, unique; e.g. DOC_REVISION_STANDARD.',
        'subject_type' => 'The record type the policy applies to. Cannot change once a version is published.',
        'no_version' => 'No published version yet',
    ],

    'subject_types' => [
        'work_request' => 'Request',
        'document_revision' => 'Document revision',
    ],
];
