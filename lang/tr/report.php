<?php

return [
    'label' => 'Rapor',
    'plural' => 'Raporlar',

    'sections' => [
        'side' => 'Durum ve inceleme',
        'report' => 'Rapor',
        'answers' => 'Rapor içeriği',
        'board' => 'İş panosu',
        'metrics' => 'Sayısal özet',
        'review' => 'İnceleme',
        'history' => 'Geçmiş',
    ],

    'fields' => [
        'report_no' => 'Rapor no',
        'template' => 'Rapor taslağı',
        'template_help' => 'Taslak hakkında',
        'title' => 'Başlık',
        'kind' => 'Tür',
        'status' => 'Durum',
        'author' => 'Yazan',
        'author_org_unit' => 'Departman',
        'subject' => 'İlgili kayıt',
        'subject_personnel' => 'İlgili personel',
        'subject_project' => 'İlgili proje',
        'subject_component' => 'İlgili ürün / bileşen',
        'subject_proposal' => 'İlgili teklif',
        'subject_business_case' => 'İlgili iş dosyası',
        'period' => 'Dönem',
        'period_none' => 'Dönem',
        'period_day' => 'Tarih',
        'period_week' => 'Hafta',
        'period_month' => 'Ay',
        'period_range' => 'Başlangıç',
        'period_end' => 'Bitiş',
        'submitted_at' => 'Gönderim',
        'reviewer' => 'İnceleyen',
        'reviewed_at' => 'İnceleme tarihi',
        'forward_to' => 'İletilecek yönetici',
        'forward_note' => 'İletme notu',
        'review_comment' => 'İnceleme notu',
        'revision_count' => 'Revizyon sayısı',
        'confidential' => 'Gizlilik',
        'created_at' => 'Oluşturulma',
        'items' => 'İş kalemleri',
        'metric_key' => 'Ölçüm',
        'metric_value' => 'Değer',
    ],

    'items' => [
        'tag' => 'Etiket',
        'title' => 'İş',
        'status' => 'Durum',
        'project' => 'Proje',
        'work_hours' => 'Saat',
        'description' => 'Not',
    ],

    'help' => [
        'forward' => 'Rapor seçtiğiniz yöneticiye iletilir; yeniden karar bekler ve ona bildirim gider. Önceki karar geçmişte kalır.',
        'list' => 'Raporlarım: yazdığım raporlar. İnceleme kutum: bana gönderilen raporlar. Ekibim: astlarımın ve departmanımın raporları.',
        'report' => 'Önce rapor taslağını seçin; taslak, doldurulacak alanları ve raporun görünümünü belirler.',
        'title' => 'Boş bırakılırsa taslak adı, ilgili kayıt ve dönemden otomatik oluşturulur.',
        'answers' => 'Bu alanlar seçtiğiniz taslağa özeldir.',
        'board' => 'Dönemde yaptığınız işler; her satır bir iş kalemidir, durumu panoda sütun olarak görünür. Önceki raporun bitmemiş işleri otomatik taşınır.',
        'period_week' => 'Haftanın herhangi bir gününü seçin; dönem Pazartesi–Pazar olarak kaydedilir.',
        'period_month' => 'Ayın herhangi bir gününü seçin; dönem ayın tamamı olarak kaydedilir.',
        'metrics' => 'Taslağın sayısal göstergeleri; gönderimde hesaplanır.',
        'submit' => 'Gönderilen rapor inceleme başlayana kadar geri çekilebilir; inceleme gerektiren taslaklarda inceleyen bildirim alır.',
        'review_comment' => 'Revizyon ve ret için açıklama zorunludur; yazara gösterilir.',
        'delete' => 'Taslak rapor kalemleriyle birlikte silinir. Bu işlem geri alınamaz.',
    ],

    'tabs' => [
        'mine' => 'Raporlarım',
        'review' => 'İnceleme kutum',
        'team' => 'Ekibim',
        'all' => 'Tümü',
    ],

    'actions' => [
        'forward' => 'İlet',
        'forward_submit' => 'Raporu ilet',

        'create' => 'Rapor yaz',
        'today' => 'Bugünün raporu',
        'open' => 'Aç',
        'edit' => 'Düzenle',
        'submit' => 'Gönder',
        'withdraw' => 'Geri çek',
        'approve' => 'Onayla',
        'request_revision' => 'Revizyon iste',
        'reject' => 'Reddet',
        'delete' => 'Taslağı sil',
        'add_item' => 'İş ekle',
        'add_measurement' => 'Ölçüm ekle',
    ],

    'values' => [
        'no_reviewer' => 'İnceleme gerekmiyor',
        'no_history' => 'Henüz hareket yok.',
        'no_items' => 'Bu sütunda iş yok.',
        'item_count' => ':count iş',
        'hours' => 'saat',
        'carried_over' => 'Önceki dönemden',
        'added_late' => 'Sonradan eklendi',
        'confidential' => 'Gizli rapor',
        'template_missing' => 'Bu raporun taslağı artık tanımlı değil; içerik gösterilemiyor.',
        'week_of' => ':date haftası',
    ],

    'relation' => [
        'authored' => 'Yazdığı raporlar',
        'authored_help' => 'Bu kişinin yazdığı raporlar; yalnız görme yetkiniz olanlar listelenir.',
        'authored_empty' => 'Bu kişi henüz rapor yazmamış.',
        'help' => 'Bu kayıtla ilgili raporlar; yalnız görme yetkiniz olanlar listelenir.',
        'empty' => 'Henüz rapor yok.',
    ],

    'messages' => [
        'forwarded' => 'Rapor iletildi.',
        'created' => 'Rapor taslak olarak kaydedildi.',
        'updated' => 'Rapor güncellendi.',
        'submitted' => 'Rapor gönderildi.',
        'withdrawn' => 'Rapor geri çekildi; yeniden düzenleyebilirsiniz.',
        'approved' => 'Rapor onaylandı.',
        'revision_required' => 'Rapor revizyon için yazara döndü.',
        'rejected' => 'Rapor reddedildi.',
        'deleted' => 'Taslak silindi.',
    ],

    'notifications' => [
        'forwarded' => [
            'title' => 'Rapor size iletildi: :no',
            'body' => ':title',
        ],
        'submitted' => ['title' => 'İncelemenizi bekleyen rapor: :no', 'body' => ':title — :author'],
        'approved' => ['title' => 'Raporunuz onaylandı: :no', 'body' => ':title — :reviewer'],
        'revision_required' => ['title' => 'Raporunuz için revizyon istendi: :no', 'body' => ':title — açıklama rapor kartında.'],
        'rejected' => ['title' => 'Raporunuz reddedildi: :no', 'body' => ':title — açıklama rapor kartında.'],
    ],

    'rating' => [
        '1' => '1 — Yetersiz',
        '2' => '2 — Geliştirilmeli',
        '3' => '3 — Beklenen düzeyde',
        '4' => '4 — İyi',
        '5' => '5 — Çok iyi',
    ],

    // Hesaplanan (alan olmayan) metriklerin ortak etiketleri
    'metrics' => [
        'total_hours' => 'Toplam çalışma (saat)',
        'done_count' => 'Tamamlanan iş',
        'open_count' => 'Açık iş',
        'progress_deviation' => 'Plandan sapma',
        'defect_rate' => 'Hata oranı',
        'overall_score' => 'Genel puan',
    ],

    /*
    | Kodda tanımlı taslaklar (App\Reports\Templates). Her taslağın adı,
    | açıklaması, alan etiketleri, yardım metinleri ve seçenek adları burada.
    */
    'templates' => [
        'daily_work' => [
            'name' => 'Günlük çalışma raporu',
            'description' => 'Günün işleri pano olarak (planlandı / devam ediyor / tamamlandı / engellendi), kısa özet ve yarın planı.',
            'fields' => [
                'summary' => 'Günün özeti',
                'blockers' => 'Engeller ve ihtiyaçlar',
                'tomorrow_plan' => 'Yarın planı',
            ],
        ],
        'weekly_work' => [
            'name' => 'Haftalık çalışma raporu',
            'description' => 'Haftanın iş panosu, öne çıkanlar, engeller ve gelecek hafta planı; doğrudan amir inceler.',
            'fields' => [
                'summary' => 'Haftanın özeti',
                'achievements' => 'Öne çıkanlar',
                'blockers' => 'Engeller ve ihtiyaçlar',
                'next_week_plan' => 'Gelecek hafta planı',
            ],
        ],
        'monthly_work' => [
            'name' => 'Aylık çalışma raporu',
            'description' => 'Ayın iş panosu, başarılar, gelecek ay hedefleri ve öz değerlendirme; departman yöneticisi inceler.',
            'fields' => [
                'summary' => 'Ayın özeti',
                'achievements' => 'Başarılar',
                'blockers' => 'Engeller ve ihtiyaçlar',
                'next_month_targets' => 'Gelecek ay hedefleri',
                'self_score' => 'Öz değerlendirme',
            ],
        ],
        'project_status' => [
            'name' => 'Proje durum raporu',
            'description' => 'Seçilen proje için ilerleme yüzdeleri, genel durum, riskler, sonraki adımlar ve karar bekleyen konular.',
            'fields' => [
                'progress_pct' => 'Fiziksel ilerleme (%)',
                'planned_pct' => 'Planlanan ilerleme (%)',
                'health' => 'Genel durum',
                'open_issue_count' => 'Açık sorun sayısı',
                'summary' => 'Durum özeti',
                'risks' => 'Riskler ve sorunlar',
                'next_steps' => 'Sonraki adımlar',
                'decisions_needed' => 'Karar bekleyen konular',
            ],
            'options' => [
                'health' => [
                    'on_track' => 'Yolunda',
                    'at_risk' => 'Riskli',
                    'delayed' => 'Gecikmede',
                ],
            ],
        ],
        'component_performance' => [
            'name' => 'Ürün / bileşen raporu',
            'description' => 'Seçilen ürün ya da bileşen için teslim ve hata sayıları, birim maliyet, kalite ve tedarikçi değerlendirmesi.',
            'fields' => [
                'delivered_qty' => 'Teslim edilen adet',
                'defect_count' => 'Hata / arıza sayısı',
                'unit_cost' => 'Birim maliyet',
                'quality_note' => 'Kalite değerlendirmesi',
                'supplier_note' => 'Tedarikçi notu',
                'improvement' => 'İyileştirme önerisi',
            ],
        ],
        'proposal_assessment' => [
            'name' => 'Teklif değerlendirme raporu',
            'description' => 'Seçilen teklif için kazanma olasılığı, rakip ve fiyat konumu, güçlü / zayıf yanlar ve öneri; departman yöneticisi inceler.',
            'fields' => [
                'win_probability_pct' => 'Kazanma olasılığı (%)',
                'competitor_count' => 'Rakip sayısı',
                'price_position' => 'Fiyat konumu',
                'recommendation' => 'Öneri',
                'summary' => 'Değerlendirme',
                'strengths' => 'Güçlü yanlar',
                'weaknesses' => 'Zayıf yanlar',
            ],
            'options' => [
                'price_position' => [
                    'low' => 'Düşük',
                    'competitive' => 'Rekabetçi',
                    'high' => 'Yüksek',
                ],
                'recommendation' => [
                    'submit' => 'Teklifi ver',
                    'revise' => 'Gözden geçir',
                    'withdraw' => 'Vazgeç',
                ],
            ],
        ],
        'business_case_review' => [
            'name' => 'İş dosyası değerlendirme raporu',
            'description' => 'Seçilen iş dosyası için ticari ve teknik risk, müşteri ilişkisi, öneri ve aksiyonlar; departman yöneticisi inceler.',
            'fields' => [
                'commercial_risk' => 'Ticari risk',
                'technical_risk' => 'Teknik risk',
                'recommendation' => 'Öneri',
                'summary' => 'Genel değerlendirme',
                'customer_relationship' => 'Müşteri ilişkisi',
                'actions' => 'Aksiyonlar',
            ],
            'options' => [
                'commercial_risk' => ['low' => 'Düşük', 'medium' => 'Orta', 'high' => 'Yüksek'],
                'technical_risk' => ['low' => 'Düşük', 'medium' => 'Orta', 'high' => 'Yüksek'],
                'recommendation' => [
                    'pursue' => 'Devam et',
                    'hold' => 'Beklet',
                    'drop' => 'Vazgeç',
                ],
            ],
        ],
        'personnel_manager_evaluation' => [
            'name' => 'Yönetici değerlendirmesi',
            'description' => 'Doğrudan amir ya da departman yöneticisi, ekibindeki bir personeli belirli bir dönem için puanlar ve görüş yazar. Gizlidir; personelin kendisi görmez.',
            'fields' => [
                'performance' => 'İş sonuçları',
                'quality' => 'İş kalitesi',
                'collaboration' => 'Ekip çalışması',
                'discipline' => 'Disiplin ve devamlılık',
                'initiative' => 'İnisiyatif',
                'recommendation' => 'Öneri',
                'overall' => 'Genel görüş',
                'strengths' => 'Güçlü yanlar',
                'development_areas' => 'Gelişim alanları',
            ],
            'options' => [
                'recommendation' => [
                    'retain' => 'Mevcut görevde devam',
                    'promote' => 'Terfi değerlendirilsin',
                    'develop' => 'Gelişim planı',
                    'warn' => 'Uyarı',
                ],
            ],
        ],
        'personnel_hr_evaluation' => [
            'name' => 'İK görüşü',
            'description' => 'İnsan Kaynakları yetkisi olan personel, bir personel hakkında devam, uyum, eğitim ve disiplin notu yazar. Gizlidir.',
            'fields' => [
                'attendance' => 'Devam ve zaman yönetimi',
                'compliance' => 'Kurallara uyum',
                'recommendation' => 'Öneri',
                'overall' => 'İK görüşü',
                'training_status' => 'Eğitim ve gelişim durumu',
                'disciplinary_note' => 'Disiplin notu',
            ],
            'options' => [
                'recommendation' => [
                    'none' => 'Aksiyon gerekmiyor',
                    'training' => 'Eğitim planlansın',
                    'warning' => 'Uyarı',
                    'promotion_review' => 'Terfi değerlendirmesi',
                ],
            ],
        ],
        'daily_control' => [
            'name' => 'Günlük kontrol raporu',
            'description' => 'İnsan Kaynakları her gün personeli bölümünün kriterlerine göre işaretler (Kontrol matrisi). Haftalık görünüm bu günlük kayıtların toplamıdır. Gizlidir; yalnız üst yönetim görür.',
            'fields' => [
                'section_label' => 'Bölüm',
                'results' => 'Kriterler',
                'note' => 'Açıklama',
            ],
            'metrics' => [
                'control_ok_count' => 'Uygun kriter',
                'control_checked_count' => 'İşaretlenen kriter',
                'control_compliance_pct' => 'Uygunluk (%)',
            ],
        ],
        'coordination_board' => [
            'name' => 'Koordinasyon panosu',
            'description' => 'Yönetim panosunun o günkü hali: tüm aktif projelerin kartları, dondurulmuş.',
            'fields' => [
                'summary' => 'Özet',
            ],
        ],
        'system_data' => [
            'name' => 'Sistem verileri raporu',
            'description' => 'Bir sistemden alınan ölçümler (ad ve değer), gözlemler ve anormallikler. Sayısal ölçümler gösterge olarak kaydedilir.',
            'fields' => [
                'data_source' => 'Veri kaynağı',
                'measurements' => 'Ölçümler',
                'observations' => 'Gözlemler',
                'anomalies' => 'Anormallikler',
            ],
            'help' => [
                'measurements' => 'Her satır bir ölçüm: sol tarafa adı (örn. "Üretim kWh"), sağ tarafa değeri.',
            ],
        ],
    ],
];
