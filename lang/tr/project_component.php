<?php

return [
    'label' => 'Proje bileşeni',
    'plural' => 'Proje bileşenleri',

    'sections' => [
        'main' => 'Bileşen bilgileri',
    ],

    'fields' => [
        'capacity_uom' => 'Kapasite birimi',
        'capacity_value' => 'Kapasite',
        'code' => 'Kod',
        'component_definition' => 'Bileşen tanımı',
        'created_at' => 'Oluşturulma',
        'definition' => 'Bileşen',
        'note' => 'Not',
        'reason' => 'Gerekçe',
        'scope_state' => 'Kapsam durumu',
    ],

    'relation' => [
        'title' => 'Bileşenler',
        'empty' => 'Henüz bileşen yok.',
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
        'duplicate' => 'Bu bileşen projede zaten tanımlı; aynı bileşen ikinci kez eklenemez.',
    ],
];
