<?php

// D-192 (B53): Operasyon - Satin alma > Satin alma talepleri (kalemli talep).
// Kalem listesinin ortak metinleri (Kalem ekle, Toplu kalem ekle, Tumunu
// kucult / genislet, rozetler) procurement.php `lines` altindadir.
return [
    'label' => 'Satın alma talebi',
    'plural' => 'Satın alma talepleri',
    'navigation' => 'Satın alma talepleri',

    'tabs' => [
        'all' => 'Tümü',
        'mine' => 'Benim taleplerim',
        'open' => 'Açık',
        'in_progress' => 'İşleniyor',
        'completed' => 'Tamamlandı',
        'cancelled' => 'İptal',
    ],

    'sections' => [
        'request' => 'Talep',
        'lines' => 'Kalemler',
        'reasons' => 'Gerekçe ve notlar',
    ],

    'fields' => [
        'request_no' => 'Kod',
        'title' => 'Talep',
        'requested_by' => 'Talep eden',
        'org_unit' => 'Departman',
        'usage_area' => 'Kullanım yeri',
        'project' => 'Taahhüt',
        'needed_by' => 'Termin',
        'is_urgent' => 'Acil',
        'justification' => 'Gerekçe',
        'existing_check' => 'Var olan neden kullanılamıyor?',
        'note' => 'Not',
        'status' => 'Durum',
        'lines_count' => 'Kalem',
        'created_at' => 'Açılış',
        'archived_at' => 'Arşive alındı',
        'reason' => 'Gerekçe',
        // Kalem
        'catalog_item' => 'Ürün / hizmet',
        'not_in_list' => 'Listede yok',
        'requested_item_name' => 'Ürün / hizmet adı',
        'brand_model' => 'Marka / model',
        'specification' => 'İstenen özellikler',
        'quantity' => 'Miktar',
        'unit' => 'Birim',
        'line_note' => 'Kalem notu',
    ],

    'help' => [
        'usage_area' => 'Şirket içi talepler tamamlanınca Şirket içi alımlar\'a yazılır.',
        // D-230
        'project' => 'Saha talebinde zorunlu; şirket içi talep bir taahhütle ilgiliyse seçebilirsiniz.',
        'org_unit' => 'Talep edenin departmanı; değiştirilemez.',
        'justification' => 'Neden gerekli? Saha ya da taahhüt neden istiyor?',
        'existing_check' => 'Şirkette var olan ürün / hizmet neden kullanılamıyor?',
        'catalog_item' => 'Malzeme ve Hizmetler\'den seçin; listede yoksa "Listede yok"u açıp adını yazın.',
        'specification' => 'Ör. bilgisayar için işlemci, bellek, disk, ekran; yazılım için sürüm ve kullanıcı sayısı.',
        'bulk_add' => 'Listeden birden fazla malzeme / hizmet seçin ya da her satıra bir kalem yapıştırın: "ad; miktar; birim". Listede aynı adla duran kalem kendiliğinden eşleşir.',
        'bulk_paste' => 'Her satıra bir kalem: ad; miktar; birim (ör. "A4 fotokopi kağıdı; 10; koli").',
        'locked_line' => 'Satın alma bu kalemi işledi; kalem değiştirilemez.',
        'cancel' => 'Talep iptal edilir; işlenmemiş kalemler de iptal olur. Siparişi verilmiş kalem varsa talep iptal edilemez.',
        'archive' => 'Talep listeden kalkar; "Arşiv" süzgeciyle görülür ve geri alınabilir.',
        'restore' => 'Talep yeniden listede görünür.',
    ],

    'bulk' => [
        'items' => 'Listeden malzeme / hizmet',
        'paste' => 'Yapıştır',
    ],

    'lines' => [
        'no' => '#',
        'item' => 'Kalem',
        'quantity' => 'Miktar',
        'status' => 'Durum',
        'quotation' => 'Teklif',
        'order' => 'Sipariş',
        'processed' => 'İşlendi',
        'empty' => 'Bu talepte kalem yok.',
    ],

    'actions' => [
        'create' => 'Satın alma talebi aç',
        'edit' => 'Düzenle',
        'cancel' => 'Talebi iptal et',
        'archive' => 'Arşive al',
        'restore' => 'Arşivden çıkar',
        'open' => 'Talebi aç',
    ],

    'messages' => [
        'created' => 'Satın alma talebi açıldı: :code',
        'cancelled' => 'Satın alma talebi iptal edildi.',
        'archived' => 'Satın alma talebi arşive alındı.',
        'restored' => 'Satın alma talebi arşivden çıkarıldı.',
        'bulk_added' => ':count kalem eklendi.',
        'bulk_none' => 'Eklenecek kalem bulunamadı.',
    ],

    'modals' => [
        'heading' => 'Satın alma talebi',
        'description' => 'Bir talepte birden fazla kalem açılabilir. Her kalem Operasyon - Satın alma › Satın alınacaklar listesine düşer; talebi Satın alma talepleri ekranından izleyebilirsiniz.',
    ],

    'filters' => [
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
        'urgent' => 'Yalnız acil',
    ],

    'values' => [
        'title_more' => ':items +:count kalem',
        'urgent' => 'Acil',
        'normal' => 'Normal',
    ],

    'empty' => 'Satın alma talebi yok.',
];
