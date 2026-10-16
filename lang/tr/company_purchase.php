<?php

// D-189: Satin Alma > Sirket ici alimlar ekrani.
return [
    'label' => 'Şirket içi alım',
    'plural' => 'Şirket içi alımlar',
    'navigation' => 'Şirket içi alımlar',

    'outlook' => 'Önümüzdeki 12 ay yenileme gideri: :amount · :count yenileme yaklaşıyor',

    'tabs' => [
        'all' => 'Tümü',
        'upcoming' => 'Yenilemesi yaklaşan',
        'overdue' => 'Yenileme tarihi geçmiş',
        'one_time' => 'Tek seferlik',
    ],

    'sections' => [
        'purchase' => 'Alım',
        'amount' => 'Tutar ve yenileme',
        'lines' => 'Kalem bilgileri',
    ],

    'fields' => [
        'request' => 'Satın alma talebi',
        'catalog_item' => 'Katalog kalemi',
        'description' => 'Açıklama',
        'org_unit' => 'Departman',
        'personnel' => 'Kullanan kişi',
        'supplier' => 'Tedarikçi',
        'supplier_party' => 'Tedarikçi (kayıtlı firma)',
        'supplier_name' => 'Tedarikçi adı',
        'purchased_on' => 'Alım tarihi',
        'quantity' => 'Miktar',
        'unit_price' => 'Birim fiyat',
        'currency' => 'Para birimi',
        'total' => 'Toplam',
        'renewal' => 'Yenileme',
        'renewal_months' => 'Yenileme dönemi',
        'next_renewal_on' => 'Sonraki yenileme',
        'ends_on' => 'Bitiş tarihi',
        'note' => 'Not',
        'need' => 'Sipariş kalemi',
        'request_line' => 'Talep kalemi',
        'request_lines' => 'Talebin kalemleri',
        'purchase_request' => 'Talep',
        'purchase_order' => 'Sipariş',
    ],

    'help' => [
        'request' => 'Alım bir satın alma talebine bağlıdır. Talebi seçince kalem, açıklama, departman, kişi, miktar, tedarikçi ve fiyat talepten dolar.',
        'catalog_item' => 'Kullanım yeri Şirket içi olan kalemler (bilgisayar, yazıcı, yazılım, kırtasiye) listenin başındadır.',
        'supplier_name' => 'Firma kayıtlı değilse adını yazın.',
        'renewal_months' => 'Boş bırakılırsa katalog kaleminin dönemi.',
        'next_renewal_on' => 'Boş bırakılırsa alım tarihi + yenileme dönemi.',
        'renew' => 'Yenileme yapıldı: :current tarihli yenileme kapanır, sonraki yenileme :next olur.',
        'archive' => 'Kayıt listeden kalkar; "Arşiv" süzgeciyle görülür ve geri alınabilir.',
        'restore' => 'Kayıt yeniden listede görünür.',
        'request_lines' => 'Şirket içi satın alma talebi. Seçince birim, kişi ve talebin kalemleri dolar; her kalem ayrı alım kaydı olur.',
        'bulk_add' => 'Seçilen talebin listede olmayan kalemleri eklenir.',
    ],

    'values' => [
        'months' => 'ay',
        'every_months' => ':months ayda bir',
        'one_time' => 'Tek seferlik',
    ],

    'actions' => [
        'create' => 'Alım ekle',
        'renew' => 'Yenilendi',
        'archive' => 'Arşive al',
        'restore' => 'Arşivden çıkar',
    ],

    'messages' => [
        'renewed' => 'Yenileme kaydedildi; sonraki yenileme tarihi ileri alındı.',
        'archived' => 'Şirket içi alım arşive alındı.',
        'restored' => 'Şirket içi alım arşivden çıkarıldı.',
        'created_lines' => ':count şirket içi alım kaydedildi.',
    ],

    'filters' => [
        'archive' => 'Arşiv',
        'archive_active' => 'Aktif',
        'archive_archived' => 'Arşivlenenler',
        'archive_all' => 'Tümü',
    ],

    'empty' => 'Şirket içi alım kaydı yok.',
];
