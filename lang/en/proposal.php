<?php

return [
    'label' => 'Proposal',
    'plural' => 'Proposals',

    'sections' => [
        'version' => 'Version :no',
        'version_documents' => 'Documents of the version',
        'main' => 'Proposal details',
        'business_case' => 'Business case',
        'header' => 'Proposal card',
        'current_version' => 'Current version',
        'scope' => 'Project scope',
        'documents' => 'Proposal documents',
    ],

    'wizard' => [
        'case_description' => 'The potential job of this proposal',
    ],

    'tabs' => [
        'all' => 'All',
        'submitted' => 'Submitted proposals',
        'to_be_submitted' => 'Proposals to submit',
        'lost' => 'Lost opportunity',
    ],

    'fields' => [
        'business_case' => 'Business case',
        'created_at' => 'Created at',
        'current_version' => 'Current version',
        'is_selected' => 'Selected',
        'offer_status' => 'Offer status',
        'owner' => 'Owner',
        'proposal_no' => 'Proposal no',
        'case_code' => 'Potential job code',
        'reason' => 'Reason',
        'status' => 'Status',
        'title' => 'Title',
    ],

    'help' => [
        'business_case' => 'The proposal is opened for this business case. Its summary appears below once selected.',
        'documents' => 'Excel or any file can be uploaded. A new upload becomes a new revision of the same document; the old file is kept.',
        'ai_soon_short' => 'KonelsisAI soon',
        'revision_notice' => 'When you save, any change in the fields, scope or documents creates a new proposal version (Version :next). The previous version and its documents are kept.',
    ],

    'relation' => [
        'title' => 'Proposals',
        'empty' => 'No proposals yet.',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'set_status' => 'Set status to ":status"',
        'select' => 'Mark as selected',
        'submit' => 'Submit for review',
        'review' => 'Record review decision',
        'publish' => 'Publish',
        'approve' => 'Approve',
        'change_focus' => 'Change focus',
        'waive' => 'Grant waiver',
        'add_evidence' => 'Add evidence',
        'accept' => 'Accept',
        'open_business_case' => 'Open business case',
        'edit_selected' => 'Edit proposal (:no)',
    ],

    'versions' => [
        'button' => 'Versions',
        'item' => 'Version :no · :status · :date',
        'current' => 'Version :no · :status · current',
        'modal_description' => ':status · :date. When the window closes the page keeps showing the current version.',
        'close' => 'Close',
        'scope_totals' => 'Totals',
        'no_documents' => 'No documents in this version.',
    ],

    'steps' => [
        'version' => 'Version :no · :status',
        'no_version' => 'No version yet',
    ],

    'status' => [
        'fixed' => 'The status is changed on the proposal edit screen',
        'change' => 'Click to change the proposal status',
        'no_targets' => 'No manual change from this status',
        'no_version' => 'No version',
        'modal_heading' => 'Change the proposal status',
        'modal_description' => 'Current status: :status (Version :no). No new version is created. The potential job status and the offer status (to be submitted / submitted) follow it.',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
        'created' => 'Proposal created: :no',
        'draft_saved' => 'Proposal saved as a draft: :no',
        'new_version' => 'A new proposal version was created: Version :no',
        'saved_no_version' => 'Saved. The proposal content did not change, so no new version was opened.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
