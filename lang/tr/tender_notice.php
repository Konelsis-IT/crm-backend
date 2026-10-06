<?php

return [
    'label' => 'İhale ilanı',
    'plural' => 'İhale ilanları',
    'label_title' => 'İhale İlanı',
    'plural_title' => 'İhale İlanları',

    'sections' => [
        'identity' => 'İlan kimliği',
        'publication' => 'Yayın ve durum',
        'summary' => 'Özet',
        'main' => 'İlan bilgileri',
        // D-155: ihale detay kartlari.
        'header' => 'İhale kartı',
        'details' => 'İhale ayrıntıları',
    ],

    'fields' => [
        'business_case' => 'Potansiyel iş',
        'captured_at' => 'Yakalanma tarihi',
        'created_at' => 'Oluşturulma',
        'current_version' => 'Güncel sürüm',
        'external_notice' => 'Harici ilan no',
        'issuer_party' => 'İlanı veren',
        'notice_url' => 'İlan adresi',
        'published_on' => 'Yayım tarihi',
        'reason' => 'Gerekçe',
        'source_document_revision' => 'İlan dosyası',
        'status' => 'Durum',
        'summary' => 'Özet',
        'tender_source' => 'İhale kaynağı',
        'title' => 'Başlık',
        'continue_to_case' => 'Kaydettikten sonra bu ihaleden potansiyel iş oluştur',
    ],

    'help' => [
        'no_case' => 'Potansiyel iş yok',
        'case_after_save' => 'İhale potansiyel iş seçmeden kaydedilir. Potansiyel iş bu ihaleden açılır ve ihale seçili gelir.',
        'no_case_yet' => 'Bu ihaleden henüz potansiyel iş açılmadı.',
        'proposal_after_case' => 'Teklif, ihaleden potansiyel iş açıldıktan sonra hazırlanır.',
        'project_after_case' => 'Proje, teklif kazanıldığında açılır.',
    ],

    'steps' => [
        'no_case' => 'Henüz potansiyel iş yok',
    ],

    'relation' => [
        'title' => 'İhale İlanları',
        'empty' => 'Henüz ilan yok.',
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
        'open' => 'İhaleyi aç',
        'create_case' => 'Bu ihaleden potansiyel iş oluştur',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
        'created' => 'İhale kaydedildi.',
        'draft_saved' => 'İhale taslak olarak kaydedildi.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
