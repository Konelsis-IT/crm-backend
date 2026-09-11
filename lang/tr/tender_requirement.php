<?php

return [
    'label' => 'İhale şartı',
    'plural' => 'İhale şartları',

    'sections' => [
        'main' => 'Şart bilgileri',
    ],

    'fields' => [
        'compliance_state' => 'Uygunluk',
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'evaluated_by' => 'Evaluated by',
        'is_mandatory' => 'Zorunlu',
        'reason' => 'Gerekçe',
        'requirement_code' => 'Şart kodu',
        'requirement_type' => 'Şart türü',
        'sort_order' => 'Sıra',
    ],

    'relation' => [
        'title' => 'Şartlar',
        'empty' => 'Henüz şart yok.',
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
