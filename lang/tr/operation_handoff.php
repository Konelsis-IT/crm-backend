<?php

return [
    'label' => 'Operasyona devir',
    'plural' => 'Operasyona devirler',

    'sections' => [
        'main' => 'Devir bilgileri',
    ],

    'fields' => [
        'accepted_at' => 'Kabul tarihi',
        'accepted_version' => 'Kabul edilen sürüm',
        'acceptor' => 'Kabul eden',
        'business_case' => 'İş dosyası',
        'created_at' => 'Oluşturulma',
        'prepared_by' => 'Prepared by',
        'preparer' => 'Hazırlayan',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
    ],

    'relation' => [
        'title' => 'Operasyona Devir',
        'empty' => 'Henüz devir yok.',
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
        'duplicate' => 'Bu iş dosyası için zaten bir devir kaydı var.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
