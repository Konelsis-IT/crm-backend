<?php

return [
    'label' => 'Teklif dokümanı',
    'plural' => 'Teklif dokümanları',

    'sections' => [
        'main' => 'Doküman bilgileri',
    ],

    'fields' => [
        // D-158: Dokumanlar sekmesi.
        'document' => 'Belge',
        'file' => 'Dosya',
        'revision' => 'Revizyon',
        'uploaded_at' => 'Yüklenme',
        'role' => 'Belge türü',
        'created_at' => 'Oluşturulma',
        'document_revision' => 'Doküman revizyonu',
        'document_role' => 'Doküman rolü',
        'reason' => 'Gerekçe',
        'sort_order' => 'Sıra',
        'title' => 'Başlık',
    ],

    'relation' => [
        'title' => 'Dokümanlar',
        'empty' => 'Henüz doküman yok.',
    ],

    // D-184: Dokumanlar tablosunda teklife kopyalanmadan gorunen satirlar.
    'virtual' => [
        'reference_list' => 'Referans listesi',
        'reference_count' => ':type · :count referans',
    ],

    'actions' => [
        'upload' => 'Belge yükle',
        // D-186: "Yeni sürüm yükle" kaldırıldı; çöp kutusu belgeyi tekliften çıkarır.
        'detach' => 'Tekliften kaldır',
        'detach_heading' => 'Belge tekliften kaldırılsın mı?',
        'download' => 'İndir',
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

    'help' => [
        'upload' => 'Birden fazla dosya seçebilirsiniz. Her dosya bu türde yeni bir belge olarak güncel sürüme eklenir; teklif sürümü değişmez.',
        'detach' => 'Belge bu teklif sürümünden çıkarılır; Dokümanlar\'da kalır, silinmez. Teklif sürümü değişmez.',
        'current_only' => 'Güncel sürümün belgeleri. Önceki sürümlerin belgeleri sayfanın üstündeki Sürümler düğmesinden görülür.',
    ],

    'messages' => [
        'uploaded_in_place' => 'Belge yüklendi.',
        'detached' => 'Belge tekliften kaldırıldı; Dokümanlar\'da duruyor.',
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
