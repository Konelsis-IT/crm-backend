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
        'case_in_proposal_step' => 'The business case is chosen in the Proposal step; once chosen its summary also appears here.',
        'ai_soon_short' => 'KonelsisAI soon',
    ],

    // D-186: versioning is up to the staff; Edit never bumps the version.
    'new_version' => [
        'action' => 'New proposal version',
        'tooltip' => 'Prepare Version :no from the current data; you can drop documents and add new ones',
        'heading' => 'New version (Version :no)',
        'breadcrumb' => 'New version',
        'subheading' => 'Version :current is filled in. Saving creates Version :next; Version :current is kept as it is.',
        'save' => 'Save new version',
    ],

    'relation' => [
        'title' => 'Proposals',
        'empty' => 'No proposals yet.',
    ],

    'actions' => [
        'general_catalog' => 'General catalogue',
        'general_catalog_tooltip' => 'Open the general catalogue in a new tab',
        'change_status' => 'Change status',
        'set_status' => 'Set status to ":status"',
        'submit' => 'Submit for review',
        'review' => 'Record review decision',
        'publish' => 'Publish',
        'approve' => 'Approve',
        'change_focus' => 'Change focus',
        'waive' => 'Grant waiver',
        'add_evidence' => 'Add evidence',
        'accept' => 'Accept',
        'open_business_case' => 'Open business case',
        'edit_latest' => 'Edit proposal (:no)',
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
        'fixed' => 'The offer status is changed with the offer status button at the top of the proposal edit screen',
        'no_targets' => 'No manual change from this status',
        'no_version' => 'No version',
        // D-182: offer status dropdown in the page header (detail and edit).
        'no_offer_status' => 'No offer status',
        'menu_label' => ':status ▾',
        'menu_tooltip' => 'Change the offer status; the chosen status is saved at once, no new version is created',
        'confirm_heading' => 'Set the proposal to ":status"?',
        'confirm_description' => 'The offer status changes from ":from" to ":to"; there is no way back from this status. No new version is created.',
        'approved_note' => 'If the proposal was not sent yet, the current version is marked as submitted; the potential job becomes Won.',
        'lost_note' => 'When every proposal of the potential job is a lost opportunity, the potential job becomes Lost. A reason is optional.',
    ],

    'messages' => [
        'status_changed' => 'Status updated.',
        'offer_status_changed' => 'Offer status saved as ":status".',
        'done' => 'Done.',
        'created' => 'Proposal created: :no',
        'draft_saved' => 'Proposal saved as a draft: :no',
        'new_version' => 'A new proposal version was created: Version :no',
        // D-186: Edit updates the current version in place.
        'saved_in_place' => 'Saved (Version :no updated).',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],
];
