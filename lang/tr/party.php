<?php

return [
    'label' => 'Taraf (Firma/Kişi)',
    'plural' => 'Taraflar',

    'tabs' => [
        'all' => 'Tümü',
    ],

    'fields_extra' => [
        'role_codes' => 'Taraf tipi',
    ],

    'help' => [
        'archive' => 'Kayıt silinmez, arşive alınır: listede görünmez, "Arşivlenenler" süzgeciyle bulunur ve istendiğinde geri alınır.',
        'restore' => 'Kayıt arşivden çıkar ve listeye geri döner.',
        'channels' => 'Kuruma ait, kişiden bağımsız iletişim bilgileri: e-posta, telefon, web sitesi. Kişilere ait bilgiler "İletişim ve kişiler" listesinden girilir.',
        'network_note' => 'Nereden tanındığı: referans, fuar, tanıdık, internet…',
        'role_codes' => 'En az bir satır girin; bir taraf aynı anda birden fazla tipte olabilir (örneğin hem müşteri hem tedarikçi). Sonradan Taraf tipi listesinden yönetilir.',
    ],

    'filters' => [
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif kayıtlar',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
    ],

    'sections' => [
        'side' => 'Özet',
        'archive' => 'Arşiv bilgisi',
        'channels' => 'İletişim bilgileri',
        'main' => 'Taraf bilgileri',
        'organization' => 'Kuruluş bilgileri',
        'person' => 'Kişi bilgileri',
    ],

    'fields' => [
        'last_meeting' => 'Son görüşme',
        'meeting_count' => 'Görüşme notu',
        'archive_reason' => 'Arşiv gerekçesi',
        'archived_at' => 'Arşivlenme tarihi',
        'archived_by' => 'Arşivleyen',
        'consent_status' => 'KVKK rızası',
        'country' => 'Ülke',
        'created_at' => 'Oluşturulma',
        'default_locale' => 'Varsayılan dil',
        'display_name' => 'Görünen ad',
        'family_name' => 'Soyad',
        'founded_year' => 'Kuruluş yılı',
        'given_name' => 'Ad',
        'is_public_company' => 'Halka açık',
        'job_title' => 'Görev',
        'legal_name' => 'Resmî unvan',
        'network_note' => 'Network',
        'party_kind' => 'Tür',
        'party_no' => 'Taraf no',
        'person_title' => 'Unvan',
        'reason' => 'Gerekçe',
        'registration_no' => 'Sicil no',
        'roles' => 'Tipi',
        'sector_code' => 'Sektör',
        'status' => 'Durum',
        'tax_number' => 'Vergi no',
        'tax_office' => 'Vergi dairesi',
        'trade_name' => 'Ticari ad',
        'visit_priority' => 'Ziyaret önceliği',
        'website_url' => 'Web sitesi',
    ],

    'values' => [
        'archived' => 'Arşivde',
    ],

    'relation' => [
        'title' => 'Taraflar',
        'empty' => 'Henüz party yok.',
    ],

    'actions' => [
        'archive' => 'Arşivle',
        'restore' => 'Arşivden çıkar',
        'add_channel' => 'İletişim bilgisi ekle',
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
        'archived' => 'Taraf arşivlendi.',
        'restored' => 'Taraf arşivden çıkarıldı.',
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],

];
