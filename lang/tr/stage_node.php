<?php

return [
    'label' => 'Onay kapısı tanımı',
    'plural' => 'Onay kapısı tanımları',

    'sections' => [
        'main' => 'Onay kapısı bilgileri',
    ],

    'fields' => [
        'created_at' => 'Oluşturulma',
        'description' => 'Açıklama',
        'is_hard_gate' => 'Zorunlu onay kapısı',
        'name_en' => 'Ad (EN)',
        'name_tr' => 'Ad (TR)',
        'owner_group' => 'Sahip grup',
        'owner_group_definition' => 'Sahip grup tanımı',
        'reason' => 'Gerekçe',
        'sequence_no' => 'Sıra no',
        'stage_code' => 'Onay kapısı kodu',
        'template' => 'Şablon',
        'template_version' => 'Şablon sürümü',
    ],

    'relation' => [
        'title' => 'Onay kapıları',
        'empty' => 'Henüz onay kapısı yok.',
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
