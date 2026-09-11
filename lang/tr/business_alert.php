<?php

return [
    'label' => 'İş uyarısı',
    'plural' => 'İş uyarıları',

    'fields' => [
        'alert_no' => 'Uyarı no',
        'severity' => 'Seviye',
        'title' => 'Konu',
        'due_at' => 'Son tarih',
        'owner' => 'Sorumlu',
        'state' => 'Durum',
        'opened_at' => 'Açıldı',
        'acknowledged_at' => 'Görüldü',
    ],

    'actions' => [
        'open' => 'Kaydı aç',
        'acknowledge' => 'Gördüm',
    ],

    'messages' => [
        'acknowledged' => 'Uyarı görüldü olarak işaretlendi.',
        'link_invalid' => 'Bağlantının süresi dolmuş ya da bağlantı geçersiz.',
        'link_not_yours' => 'Bu bağlantı başka bir personele gönderilmiş.',
    ],

    'notifications' => [
        'title' => [
            'warning' => 'Yaklaşan son tarih',
            'high' => 'Son tarih çok yakın',
            'critical' => 'ACİL: Son tarih geldi veya geçti',
        ],
        'body' => ':subject — son tarih :date',
    ],

    // Tarama kaynakları; :subject kayıt adı, :context proje / iş dosyası / kişi.
    'triggers' => [
        'deadline_project_issue' => 'Sorun son tarihi: :subject (:context)',
        'deadline_project_risk_review' => 'Risk gözden geçirme tarihi: :subject (:context)',
        'deadline_stage_requirement' => 'Gate şartı son tarihi: :subject (:context)',
        'deadline_stage_condition' => 'Koşullu geçiş şartı: :subject (:context)',
        'deadline_recovery_action' => 'Telafi aksiyonu son tarihi: :subject (:context)',
        'deadline_work_package' => 'İş paketi planlı bitiş: :subject (:context)',
        'deadline_project_finish' => 'Proje planlı bitiş: :subject',
        'deadline_tender' => 'İhale son tarihi: :subject (:context)',
        'deadline_contract_milestone' => 'Sözleşme kilometre taşı: :subject (:context)',
        'deadline_contract_obligation' => 'Sözleşme yükümlülüğü: :subject (:context)',
        'deadline_certification' => 'Sertifika geçerlilik sonu: :subject (:context)',
    ],

    'widget' => [
        'heading' => 'Yaklaşan tarihler ve uyarılar',
        'description' => 'Sorumlusu olduğunuz açık uyarılar; kritikler üstte.',
        'description_all' => 'Tüm açık uyarılar; kritikler üstte.',
        'empty' => 'Açık uyarı yok.',
    ],
];
