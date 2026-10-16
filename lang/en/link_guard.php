<?php

// Confirmation before following a link (D-200, konelsis-link-guard.js).
// Placeholders: :type the target record type (RecordHeading::typeLabel),
// :field the field / column name, :target the clicked record. :field and
// :target are shown in bold in the description (no quotes).
return [
    'title' => 'You are leaving this page',
    'title_type' => 'Opening :type',
    'body' => ':target from the :field field will open. Do you want to leave this page?',
    'body_no_field' => ':target will open. Do you want to leave this page?',
    'body_no_target' => 'The linked page will open. Do you want to leave this page?',
    'stay' => 'Stay on page',
    'row' => 'Open the row\'s details',
    'go' => 'Go',
    'go_type' => 'Open :type',
    'close' => 'Close',
];
