<?php

return [
    'label' => 'Proje kapsamı',
    'plural' => 'Proje kapsamları',

    'sections' => [
        'ges' => 'GES kapsamı',
        'res' => 'RES kapsamı',
        'tm' => 'TM kapsamı',
        'hes' => 'HES kapsamı',
        'bes' => 'BES kapsamı',
        'enh_eih' => 'ENH/EIH kapsamı',
    ],

    'fields' => [
        'capacity_mw' => 'Kurulu güç (MW)',
        'cost_amount' => 'Maliyet',
        'sales_amount' => 'Satış',
        'cost_per_mw' => 'MW başı maliyet',
        'sales_per_mw' => 'MW başı satış',
        'total_sales' => 'Toplam satış',
        'res_material_amount' => 'Respark malzeme',
        'res_construction_amount' => 'Respark inşaat',
        'res_assembly_amount' => 'Respark montaj',
        'total' => 'Toplam',
        'tm_total_cost' => 'Toplam maliyet',
        'tm_total_sales' => 'Toplam satış',
        'tm_feeder_cost' => 'Fider başı maliyet',
        'hes_unit_cost' => 'Jeneratör/Türbin başı maliyet',
        'scope_file' => 'Kapsam listesi (Excel)',
        'current_file' => 'Yüklü kapsam listesi',
        'scope_document' => 'Kapsam listesi',
        'note' => 'Not',
    ],

    'help' => [
        'scope_file' => 'Bu proje tipi için hazırlanan kapsam listesi (.xlsx/.xls/.csv). Yeni yükleme eskisinin yerine yeni sürüm olarak eklenir.',
        'fields_later' => 'Bu tip için alanlar daha sonra tanımlanacak; şimdilik kapsam listesini yükleyebilirsiniz.',
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
    ],

    'messages' => [
        'status_changed' => 'Durum güncellendi.',
        'done' => 'İşlem tamamlandı.',
    ],

    'validation' => [
        'code_taken' => 'Bu kod zaten kullanılıyor.',
        'duplicate' => 'Bu kayıt zaten var.',
    ],
];
