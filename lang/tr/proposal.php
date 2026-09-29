<?php

return [
    'label' => 'Teklif',
    'plural' => 'Teklifler',

    'sections' => [
        'main' => 'Teklif bilgileri',
        'business_case' => 'Potansiyel iş',
        'header' => 'Teklif kartı',
        'current_version' => 'Güncel sürüm',
    ],

    'tabs' => [
        'all' => 'Tümü',
        'submitted' => 'Verilen Teklifler',
        'to_be_submitted' => 'Verilecek Teklifler',
        'lost' => 'Kaçan Fırsat',
    ],

    'fields' => [
        'business_case' => 'Potansiyel iş',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'is_selected' => 'Seçili',
        'offer_status' => 'Teklif durumu',
        'owner' => 'Sahip',
        'proposal_no' => 'Teklif no',
        'case_code' => 'Potansiyel iş kodu',
        'reason' => 'Gerekçe',
        'status' => 'Durum',
        'title' => 'Başlık',
    ],

    'help' => [
        'business_case' => 'Teklif bu potansiyel iş için açılır. Seçince potansiyel işin özeti aşağıda görünür.',
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
        'open_business_case' => 'Potansiyel işi aç',
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
