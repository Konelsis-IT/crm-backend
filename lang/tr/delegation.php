<?php

return [
    'label' => 'Vekalet',
    'plural' => 'Vekaletler',

    'sections' => [
        'parties' => 'Taraflar ve kapsam',
        'validity' => 'Geçerlilik',
    ],

    'fields' => [
        'grantor' => 'Vekalet veren',
        'delegate' => 'Vekil',
        'capability_code' => 'Devredilen yetki',
        'scope_type' => 'Kapsam',
        'scope_policy' => 'Onay politikası',
        'reason' => 'Gerekçe',
        'valid_from' => 'Başlangıç',
        'valid_until' => 'Bitiş',
        'period' => 'Süre',
        'status' => 'Durum',
        'approver' => 'Onaylayan',
        'revoked_at' => 'İptal tarihi',
        'revoker' => 'İptal eden',
        'revoke_reason' => 'İptal gerekçesi',
    ],

    'capabilities' => [
        'approval_decide' => 'Onay kararı verme',
    ],

    'help' => [
        'parties' => 'Vekalet veren, onay kararı yetkisini belirli bir süre için vekile devreder. Vekilin kararı, kimin adına verildiğiyle birlikte kaydedilir.',
        'scope_type' => '"Tümü": vekalet verenin bütün onay adımları. "Onay politikası": yalnız seçilen politikanın adımları.',
        'valid_until' => 'Bitiş zorunludur; süre dolunca vekalet kendiliğinden biter.',
        'list' => 'Kendi verdiğiniz ve aldığınız vekaletler; yöneticiler tümünü görür.',
        'open_list' => 'Geçerli ve başlayacak vekaletler. Geçmiş vekaletler ve düzenleme için Tüm vekaletler.',
    ],

    'actions' => [
        'revoke' => 'Vekaleti iptal et',
        'all' => 'Tüm vekaletler',
        'new' => 'Vekalet ver',
        'close' => 'Kapat',
    ],

    'empty_open' => 'Geçerli ya da başlayacak vekalet yok.',

    'messages' => [
        'revoked' => 'Vekalet iptal edildi.',
    ],
];
