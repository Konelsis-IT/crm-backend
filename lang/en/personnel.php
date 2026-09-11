<?php

return [
    'label' => 'Personnel',
    'plural' => 'Personnel',

    'sections' => [
        'identity' => 'Identity and contact',
        'assignment' => 'Assignment',
        'competencies' => 'Competencies',
        'account' => 'Account and sign-in',
        'side' => 'Manager and dates',
    ],

    'fields' => [
        'photo' => 'Photo',
        'full_name' => 'Full name',
        'personnel_no' => 'Personnel no',
        'national_id' => 'National ID',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'department' => 'Department',
        'direct_manager' => 'Direct manager',
        'job_title' => 'Job title',
        'hired_on' => 'Hire date',
        'competencies' => 'Competencies',
        'competency' => 'Competency',
        'competency_level' => 'Level',
        'competency_note' => 'Note',
        'locale' => 'Language',
        'timezone' => 'Time zone',
        'status' => 'Status',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'current_password' => 'Current password',
        'password_changed_at' => 'Password changed',
        'last_login_at' => 'Last sign-in',
        'reason' => 'Reason',
    ],

    'help' => [
        'national_id' => '11-digit national identity number.',
        'phone' => 'Mobile number; the call and WhatsApp links are built from it.',
        'status' => 'Not editable here; use the "Change status" button at the top of the page. Personnel can sign in only while "Active" and holding at least one role.',
        'direct_manager' => 'Separate from the department manager; the person this personnel actually reports to.',
        'password' => 'At least 8 characters. Leave empty while editing to keep the current password.',
        'competencies' => 'Topics the person is qualified in; shown as badges in the list.',
        'tap_to_call' => 'Tap the number to start a call.',
        'profile_intro' => 'Your own record. Changes are written to Personel Hareketleri.',
        'profile_password' => 'Leave empty if you do not want to change your password.',
    ],

    'actions' => [
        'change_status' => 'Change status',
        'set_status' => 'Set status to ":status"',
        'add_competency' => 'Add competency',
        'contact' => 'Contact',
        'whatsapp' => 'Message on WhatsApp',
        'whatsapp_short' => 'WhatsApp',
        'start_chat' => 'Start chat',
        'call' => 'Call',
        'mail' => 'Send e-mail',
        'show_as_cards' => 'Card view',
        'show_as_list' => 'List view',
    ],

    'messages' => [
        'status_changed' => 'Personnel status updated.',
        'no_competency' => 'No competency defined.',
        'never_logged_in' => 'Never signed in',
        'profile_saved' => 'Your profile has been updated.',
    ],

    'profile' => [
        'title' => 'My profile',
    ],

    'validation' => [
        'email_taken' => 'This e-mail address is already in use.',
    ],
];
