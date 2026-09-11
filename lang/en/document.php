<?php

return [
    'label' => 'Document',
    'plural' => 'Documents',

    'sections' => [
        'main' => 'Document details',
        'identity' => 'Identity',
        'ownership' => 'Ownership and classification',
        'context' => 'Context',
        'initial_content' => 'Original document',
        'header' => 'Document card',
        'content' => 'Current content',
        'body' => 'Document body',
        'file' => 'File',
    ],

    'fields' => [
        'project' => 'Project',
        'document_no' => 'Document no',
        'title' => 'Title',
        'document_type' => 'Document type',
        'is_controlled' => 'Controlled document',
        'owner' => 'Owner',
        'owner_org_unit' => 'Owner unit',
        'classification' => 'Classification',
        'retention_policy' => 'Retention policy',
        'default_language' => 'Default language',
        'description' => 'Description',
        'current_revision' => 'Current revision',
        'status' => 'Status',
        'created_at' => 'Created',
    ],

    'tabs' => [
        'revisions' => 'Revisions',
        'approvals' => 'Approvals',
        'shares' => 'Shares',
        'links' => 'Links',
        'reviews' => 'Reviews',
        'distributions' => 'Distributions',
        'acknowledgements' => 'Acknowledgements',
    ],

    'help' => [
        'project' => 'Select when the document belongs to a project; project reports and handoff manifests branch from this link.',
        'document_no' => 'Generated automatically from the document type.',
        'classification' => 'Leave blank to use the document type default.',
        'retention_policy' => 'Leave blank to use the document type default.',
        'identity' => 'Name, type and language. The document number is generated from the type prefix.',
        'ownership' => 'Who is responsible, which unit owns it, how confidential it is and how long it is kept.',
        'context' => 'The project the document belongs to; optional.',
        'initial_content' => 'Upload the original file or write the document here; the first revision (01) opens as a draft.',
        'title' => 'The name shown in lists and on the card.',
        'document_type' => 'Numbering, default classification and retention policy come from the type.',
        'description' => 'Short description of what the document is.',
        'owner' => 'Person responsible for the document; defaults to you.',
        'is_controlled' => 'Distribution and revisions of controlled documents are tracked.',
        'first_revision_note' => 'Short note for the first revision (optional).',
        'create_intro' => 'Fill in the details and add the original; the document detail view opens after saving.',
        'no_content' => 'This document has no content yet. Use "New revision" to upload a file or write the document.',
        'working_revision' => 'Draft in progress: Rev :code (:status). It becomes the current content once issued.',
        'content' => 'The issued revision; otherwise the latest revision is shown.',
        'no_file' => 'No file attached to this revision.',
        'new_revision' => 'Opens a new draft revision. Issued revisions are never overwritten.',
        'share_public' => 'The link is currently open to anyone who has it. Permission/password protection comes in a later round.',
        'approval' => 'The working revision is sent to approvers according to the approval policy; once approved the revision becomes "approved".',
        'no_policy' => 'No published approval policy for this subject. Add one under Settings › Approval policies.',
        'approval_tab' => 'Approval requests opened for this document\'s revisions. Decisions are made on the Approvals screen.',
    ],

    'values' => [
        'previewable' => 'Previewable in browser',
        'download_only' => 'Download only',
    ],

    'actions' => [
        'download_current' => 'Download current file',
        'preview_current' => 'Preview current file',
        'create_with_file' => 'Add document (with file)',
        'edit_details' => 'Edit details',
        'new_revision' => 'New revision',
        'share' => 'Share',
        'send_to_approval' => 'Send for approval',
        'open_share' => 'Open link',
        'view_body' => 'View content',
        'close' => 'Close',
    ],

    'messages' => [
        'revision_created' => 'New revision opened.',
        'share_created' => 'Share link created.',
        'approval_requested' => 'Approval request opened; approvers were notified.',
    ],

    'share' => [
        'title' => 'Shared document',
        'intro' => 'This document was shared with you.',
        'unavailable' => 'This share link is no longer valid.',
        'no_content' => 'The document has no content yet.',
        'footer' => 'Shared via :app.',
    ],

    'relation' => [
        'automation_title' => 'SCADA / PLC documents',
        'revisions' => [
            'title' => 'Revisions',
            'empty' => 'No revisions yet.',
        ],
        'links' => [
            'title' => 'Links',
            'empty' => 'No links yet.',
        ],
        'reviews' => [
            'title' => 'Reviews',
            'empty' => 'No review records yet.',
        ],
        'distributions' => [
            'title' => 'Distributions',
            'empty' => 'No distribution records yet.',
        ],
        'acknowledgements' => [
            'title' => 'Read/Accept Acknowledgements',
            'empty' => 'No acknowledgement records yet.',
        ],
    ],
];
