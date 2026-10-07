<?php

return [
    'label' => 'Contact relationship',
    'plural' => 'Contact relationships',

    'sections' => [
        'channels' => 'Contact details',
        'main' => 'Relationship details',
    ],

    'fields' => [
        'channels' => 'Contact details',
        'contact' => 'Name',
        'contact_name' => 'Name',
        'created_at' => 'Created at',
        'department_note' => 'Department note',
        'is_primary' => 'Primary',
        'network_note' => 'Network',
        'reason' => 'Reason',
        'relationship_role' => 'Relationship role',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
    ],

    'relation' => [
        'title' => 'Contacts',
        'empty' => 'No contacts yet.',
    ],

    'help' => [
        'channels' => 'Add every contact detail for this person: work phone, mobile, email, fax. Add as many rows as you need.',
        'contact_name' => 'Person name or channel name (for example Switchboard, Accounting).',
        'network_note' => 'How this person was met.',
    ],

    'actions' => [
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
        'status_changed' => 'Status updated.',
        'done' => 'Done.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This record already exists.',
    ],

    // D-167: add a contact with "+" in the meeting note / plan form.
    'quick' => [
        'action' => 'Add person',
        'heading' => 'Add person',
        'description' => 'Add the person you met if they are not in the list: they are written to the contacts of :party and selected in this field.',
        'submit' => 'Add',
        'name' => 'Full name',
        'created' => 'Person added: :name',
    ],

    // D-167: people in the global search (contacts and people met).
    'search' => [
        'category' => 'People',
        'party' => 'Company',
        'role' => 'Role',
        'channel' => 'Contact',
        'last_meeting' => 'Last meeting',
        'open_notes' => 'Meeting notes',
        'open_contact' => 'Person record',
    ],
];
