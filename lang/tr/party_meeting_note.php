<?php

return [
    'label' => 'Görüşme notu',
    'plural' => 'Görüşme notları',

    'sections' => [
        'main' => 'Görüşme bilgileri',
    ],

    'fields' => [
        'business_case' => 'Potansiyel iş',
        'proposals' => 'Teklifler',
        'channel' => 'Kanal',
        'contact' => 'Görüşülen kişi',
        'created_at' => 'Oluşturulma',
        'next_action' => 'Sonraki adım',
        'next_action_on' => 'Sonraki adım tarihi',
        'note' => 'Not',
        'noted_on' => 'Görüşme tarihi',
        'personnel' => 'Görüşen personel',
        'reason' => 'Gerekçe',
        'subject' => 'Konu',
        'archived_at' => 'Arşive alınma',
    ],

    'help' => [
        'business_case' => 'Görüşme bir potansiyel işle ilgiliyse seçin; not o işin sayfasında da görünür.',
        'proposals' => 'Görüşmede konuşulan teklifler (seçilen potansiyel işin teklifleri); not teklif sayfasında da görünür.',
        'next_action_reminder' => 'Sonraki adım tarihi verilirse bu adım Görüşme planı takvimine planlı görüşme olarak düşer; tarihten 1 gün önce ve o günün sabahı görüşen personele zil bildirimi gider.',
        // D-156: silme yok, arşiv var.
        'archive' => 'Not silinmez, arşive alınır: listelerde görünmez, Görüşme planındaki karşılığı da arşive gider. Arşiv süzgeciyle bulunur ve geri alınabilir.',
        'restore' => 'Not ve Görüşme planındaki karşılığı yeniden görünür.',
    ],

    'filters' => [
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
    ],

    'relation' => [
        'title' => 'Görüşme notları',
        'empty' => 'Henüz görüşme notu yok.',
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
        'edit' => 'Notu düzenle',
        'archive' => 'Arşive al',
        'restore' => 'Arşivden çıkar',
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
        'updated' => 'Görüşme notu güncellendi.',
        'archived' => 'Görüşme notu arşive alındı.',
        'restored' => 'Görüşme notu arşivden çıkarıldı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
