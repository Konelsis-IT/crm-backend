<?php

return [
    'label' => 'Party',
    'plural' => 'Parties',

    'nav' => [
        'associations' => 'Associations',
    ],

    'tabs' => [
        'all' => 'All',
    ],

    'fields_extra' => [
        'role_codes' => 'Party type',
    ],

    'help' => [
        'is_competitor' => 'Competitors are marked by the responsible staff; find them with the "Competitor" filter.',
        'activity_areas' => 'What the company does: each row is a project type, an activity area and a sub-activity area. A company can have several rows. An empty project type means all project types.',
        'archive' => 'The record is not deleted but archived: it leaves the list, can be found with the "Archived" filter and restored at any time.',
        'restore' => 'The record leaves the archive and returns to the list.',
        'channels' => 'Company-level contact details independent of a person: email, phone, website. Person details are entered in the contacts list.',
        'network_note' => 'Where the party is known from: referral, fair, acquaintance, internet…',
        'role_codes' => 'Pick at least one type; a party can hold several at once (customer and supplier, for example).',
    ],

    'filters' => [
        'archive' => 'Archive',
        'archive_active' => 'Active records',
        'archive_archived' => 'Archived',
        'archive_all' => 'All',
    ],

    'sections' => [
        'activity_areas' => 'Activity areas',
        'side' => 'Summary',
        'archive' => 'Archive details',
        'channels' => 'Contact details',
        'main' => 'Party details',
        'organization' => 'Organization details',
        'person' => 'Person details',
    ],

    'fields' => [
        'origin' => 'Origin',
        'is_competitor' => 'Competitor',
        'project_type' => 'Project type',
        'activity_area' => 'Activity area',
        'sub_activity_area' => 'Sub-activity area',
        'last_meeting' => 'Last meeting',
        'meeting_count' => 'Meeting notes',
        'archive_reason' => 'Archive reason',
        'archived_at' => 'Archived at',
        'archived_by' => 'Archived by',
        'consent_status' => 'Consent status',
        'country' => 'Country',
        'created_at' => 'Created at',
        'default_locale' => 'Default language',
        'display_name' => 'Display name',
        'family_name' => 'Family name',
        'founded_year' => 'Founded year',
        'given_name' => 'Given name',
        'is_public_company' => 'Public company',
        'job_title' => 'Job title',
        'legal_name' => 'Legal name',
        'network_note' => 'Network',
        'party_kind' => 'Kind',
        'party_no' => 'Party no',
        'person_title' => 'Title',
        'reason' => 'Reason',
        'registration_no' => 'Registration no',
        'roles' => 'Type',
        'sector_code' => 'Sector',
        'status' => 'Status',
        'tax_number' => 'Tax number',
        'tax_office' => 'Tax office',
        'trade_name' => 'Trade name',
        'visit_priority' => 'Visit priority',
        'website_url' => 'Website',
    ],

    'values' => [
        'all_project_types' => 'All project types',
        'competitor' => 'Competitor',
        'not_competitor' => 'Not a competitor',
        'archived' => 'Archived',
    ],

    'relation' => [
        'title' => 'Parties',
        'empty' => 'No parties yet.',
    ],

    'actions' => [
        'add_activity_area' => 'Add activity area',
        'archive' => 'Archive',
        'restore' => 'Restore',
        'add_channel' => 'Add contact detail',
        'change_status' => 'Change status',
        'set_status' => 'Set status to \":status\"',
        'select' => 'Mark as selected',
        'submit' => 'Submit for review',
        'review' => 'Record review decision',
        'publish' => 'Publish',
        'approve' => 'Approve',
        'change_focus' => 'Change focus',
        'waive' => 'Grant waiver',
        'add_evidence' => 'Add evidence',
        'accept' => 'Accept',
    ],

    'messages' => [
        'archived' => 'Party archived.',
        'restored' => 'Party restored.',
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],

];
