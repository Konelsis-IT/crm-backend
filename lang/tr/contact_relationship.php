<?php

return [
    'label' => 'Kişi ilişkisi',
    'plural' => 'Kişi ilişkileri',

    'sections' => [
        'main' => 'İlişki bilgileri',
    ],

    'fields' => [
        'contact' => 'Kişi',
        'contact_party' => 'Kişi (taraf)',
        'created_at' => 'Oluşturulma',
        'department_note' => 'Departman notu',
        'is_primary' => 'Asıl',
        'reason' => 'Gerekçe',
        'relationship_role' => 'İlişki rolü',
        'valid_from' => 'Başlangıç',
        'valid_until' => 'Bitiş',
    ],

    'relation' => [
        'title' => 'Kişiler',
        'empty' => 'Henüz kişi bağlanmadı.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu \":status\" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
