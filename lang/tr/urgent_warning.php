<?php

// Panodan acil uyari, departman cagrisi ve departman uyarisi (D-208, 14 Ekim 2026).
return [
    'item' => [
        'title' => 'Acil: :subject',
        'body_fixed' => 'Durumu :sender tarafından acil olarak işaretlendi.',
        'body_custom' => ':sender: :message',
    ],
    'call' => [
        'title' => ':sender sizi çağırıyor',
        'body' => ':department departmanı :sender tarafından çağrıldı.',
    ],
    'department' => [
        'title' => ':department: bekleyen işler için uyarı',
        'body' => ':topic: :count kayıt bekliyor. :sender uyardı.',
        'body_oldest' => ':topic: :count kayıt bekliyor, en eskisi :days gün. :sender uyardı.',
    ],
    'note' => 'Not: :note',
    'actions' => [
        'open_record' => 'Kaydı aç',
        'open_list' => 'Listeyi aç',
    ],
];
