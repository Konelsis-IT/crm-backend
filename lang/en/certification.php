<?php

return [
    'label' => 'Certification',
    'plural' => 'Certifications',

    'sections' => [
        'main' => 'Certification details',
    ],

    'fields' => [
        'code' => 'Code',
        'name' => 'Certification name',
        'issuer' => 'Issuer',
        'validity_months' => 'Validity (months)',
        'is_field_mandatory' => 'Required for field work',
        'personnel_count' => 'Personnel count',
        'status' => 'Status',
        'certificate_no' => 'Certificate no',
        'issued_on' => 'Issued on',
        'valid_until' => 'Valid until',
        'verified_by' => 'Verified by',
    ],

    'help' => [
        'validity_months' => 'Leave blank if it never expires.',
        'valid_until' => 'Leave blank if it never expires; status is computed from this date.',
    ],

    'validation' => [
        'code_taken' => 'This code is already in use.',
        'duplicate' => 'This personnel already has this certification recorded for that date.',
    ],

    'relation' => [
        'title' => 'Certifications',
        'empty' => 'No certifications recorded.',
    ],
];
