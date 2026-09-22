<?php

return [
    'label' => 'Teklif',
    'plural' => 'Teklifler',

    'sections' => [
        'main' => 'Teklif bilgileri',
        'business_case' => 'İş dosyası',
        'header' => 'Teklif kartı',
        'current_version' => 'Güncel sürüm',
    ],

    'fields' => [
        'business_case' => 'İş dosyası',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'is_selected' => 'Seçili',
        'offer_status' => 'Teklif durumu',
        'owner' => 'Sahip',
        'proposal_no' => 'Teklif no',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'title' => 'Başlık',
    ],

    'help' => [
        'business_case' => 'Teklif bu iş dosyası için açılır. Seçince iş dosyasının özeti aşağıda görünür.',
    ],

    'relation' => [
        'title' => 'Teklifler',
        'empty' => 'Henüz teklif yok.',
    ],

    'actions' => [
        'change_status' => 'Durum değiştir',
        'set_status' => 'Durumu ":status" yap',
        'select' => 'Seçili yap',
        'submit' => 'İncelemeye gönder',
        'review' => 'İnceleme kararı ver',
        'publish' => 'Yayımla',
        'approve' => 'Onayla',
        'change_focus' => 'Odağı değiştir',
        'waive' => 'Muafiyet ver',
        'add_evidence' => 'Kanıt ekle',
        'accept' => 'Kabul et',
        'open_business_case' => 'İş dosyasını aç',
    ],

    'steps' => [
        'version' => 'Sürüm :no · :status',
        'no_version' => 'Henüz sürüm yok',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
        'created' => 'Teklif oluşturuldu: :no',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
