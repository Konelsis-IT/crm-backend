<?php

return [
    'label' => 'Onay adımı',
    'plural' => 'Onay Adımları',

    'relation' => [
        'title' => 'Adımlar',
        'empty' => 'Henüz adım yok.',
    ],

    'sections' => [
        'identity' => 'Adım',
        'resolver' => 'Onaycı',
    ],

    'fields' => [
        'step_code' => 'Adım kodu',
        'sequence_no' => 'Sıra',
        'decision_rule' => 'Karar kuralı',
        'name_tr' => 'Ad (TR)',
        'name_en' => 'Ad (EN)',
        'sla_minutes' => 'Karar süresi (dk)',
        'resolver_type' => 'Onaycı nasıl bulunur',
        'target_personnel' => 'Personel',
        'target_position' => 'Pozisyon',
        'role_code' => 'Rol',
        'is_optional' => 'İsteğe bağlı adım',
        'allows_delegation' => 'Vekalet kabul eder',
    ],

    'help' => [
        'step_code' => 'Büyük harf; sürüm içinde tekil (örn. CHECK, APPROVE).',
        'sequence_no' => 'Boş bırakılırsa sona eklenir. Sıralı modda adımlar bu sıraya göre işler.',
        'decision_rule' => 'Adımda birden fazla onaycı bulunursa: herhangi biri yeter / hepsi onaylamalı / çoğunluk.',
        'sla_minutes' => 'Boşsa sürümün genel süresi kullanılır.',
        'resolver' => 'Onaycı talep anında bulunur: doğrudan kişi/pozisyon, talep sahibinin amiri, birim yöneticisi, konunun projesindeki rol ya da RBAC rolü.',
        'is_optional' => 'Onaycı bulunamazsa adım atlanır; zorunlu adımda talep hiç açılmaz.',
        'allows_delegation' => 'Onaycının geçerli vekili bu adımda onun adına karar verebilir.',
        'relation' => 'Adımlar yalnız taslak sürümde düzenlenir.',
        'empty' => 'Yayımlamadan önce en az bir adım ekleyin.',
    ],
];
