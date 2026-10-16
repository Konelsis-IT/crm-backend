<?php

// Detail and edit page heading (D-205, RecordHeading / HasRecordHeading).
// :type is the record type in sentence case ("Potential job").
return [
    'view' => ':type details',
    'edit' => 'Edit :type',
    'crumb_view' => 'Details',

    // Pages whose resource label reads poorly in the heading (URL segment => type name).
    'labels' => [
        'parties' => 'Company / person',
        'personnel/personnels' => 'Personnel',
    ],
];
