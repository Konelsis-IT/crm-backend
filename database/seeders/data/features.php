<?php

// Ozellik anahtarlari (D-128): FeatureSeeder bu dosyadaki durumlari canlidaki
// `features` tablosuna uygular. true = acik, false = kapali. Katalog sirasi.
// Uretim: php artisan konelsis:features:export (30.09.2026 16:16, yerel veritabani).
// 3 Ekim 2026 (D-153): canlidaki durum kullanicinin listesinden islendi; 31 ozellik
// canlida kapali ve surumu 2.4 (sira gelen guncellemeyle acilir).
// 5 Ekim 2026 (D-154): Akis sekmesi (social_media.feed) canlida acildi, surumu 2.3;
// canlida kapali ozellik sayisi 30.
// 5 Ekim 2026 (D-155): kontrol listesi, potansiyel is belgeleri ve taslak kaydi yeni
// (surum 2.4, acik eklenir; canlida 2.4 yayinlanana kadar surumden dolayi gorunmez).
// 5 Ekim 2026 (D-156): Gorusme planinda "Firma ekle" yeni (surum 2.4).
// Elle de duzenlenebilir; katalogda olmayan kod atlanir.

return [
    'dashboard' => true, // Genel bakış
    'dashboard.stats' => true, // Genel bakış: Sayılar
    'dashboard.agenda' => true, // Genel bakış: Görevlerim ve işlerim
    'dashboard.send_notification' => true, // Genel bakış: Duyuru gönder düğmesi
    'dashboard.alerts' => true, // Genel bakış: Yaklaşan tarihler ve uyarılar
    'dashboard.announcements' => true, // Genel bakış: Duyurular
    'dashboard.social' => true, // Genel bakış: Yaklaşan sosyal medya paylaşımları
    'personnel' => true, // Personel ve organizasyon
    'personnel.org_units' => true, // Organizasyon birimleri
    'personnel.positions' => true, // Pozisyonlar
    'personnel.competencies' => true, // Yetkinlikler
    'personnel.certifications' => true, // Sertifikalar
    'personnel.trainings' => true, // Eğitimler
    'personnel.activities' => false, // Personel Hareketleri
    'approvals' => true, // Onay motoru
    'approvals.requests' => true, // Onaylar ekranı
    'approvals.policies' => true, // Onay politikaları
    'approvals.delegations' => false, // Vekaletler
    'documents' => true, // Doküman yönetimi
    'documents.legal_holds' => false, // Hukuki tutmalar
    'documents.transmittals' => false, // Teslim tutanakları
    'documents.types' => true, // Doküman tipleri
    'documents.templates' => true, // Doküman şablonları
    'acquisition' => true, // İş alım
    'acquisition.parties' => true, // Taraflar
    'acquisition.associations' => true, // Dernekler
    'acquisition.business_cases' => true, // Potansiyel işler
    'acquisition.business_cases.meeting_notes' => true, // Potansiyel iş ve teklif görüşme notları
    'acquisition.business_cases.checklist' => true, // Potansiyel iş: Teklif öncesi kontrol listesi (2.4)
    'acquisition.business_cases.documents' => true, // Potansiyel iş: Belgeler (2.4)
    'acquisition.deal_track' => true, // Bu iş nerede?
    'acquisition.drafts' => true, // Taslak kaydı: ihale, potansiyel iş, teklif (2.4)
    'acquisition.proposals' => true, // Teklifler
    'acquisition.proposals.status_tabs' => true, // Teklifler: Durum sekmeleri
    'acquisition.contracts' => false, // Sözleşmeler
    'acquisition.operation_handoffs' => true, // Operasyona devirler
    'acquisition.tenders' => true, // İhaleler
    'acquisition.meeting_plans' => true, // Görüşme planı
    'acquisition.meeting_plans.quick_party' => true, // Görüşme planı: Firma ekle (2.4)
    'acquisition.activity_areas' => true, // Faaliyet alanları
    'projects' => true, // Projeler
    'projects.supply_items' => true, // Tedarik kalemleri
    'projects.stage_gates' => true, // Proje onay kapıları
    'projects.catalogs' => true, // Proje ayarları
    'projects.catalogs.components' => false, // Proje bileşenleri
    'projects.catalogs.operation_groups' => true, // Operasyon grupları
    'projects.catalogs.focus_expectations' => false, // Odak beklentileri
    'projects.catalogs.stage_templates' => false, // Onay kapısı şablonları
    'work_requests' => true, // Talepler
    'work_requests.thread' => true, // Talep yazışması (talep sohbeti)
    'reports' => true, // Raporlar
    'work' => true, // İş takibi
    'work.items' => true, // İşler ekranı
    'work.board' => true, // İş panosu
    'work.control_matrix' => true, // Kontrol matrisi
    'work.control_matrix.personnel_tab' => false, // Personel kartı: Haftalık kontrol sekmesi
    'work.control_matrix.attention_card' => false, // Personel kartı: Dikkat kartı
    'work.analysis' => false, // İş raporları
    'work.analysis.dashboard' => false, // İş raporları: Analiz panosu
    'work.analysis.duration' => false, // İş raporları: Süre raporu
    'social_media' => true, // Sosyal medya
    'social_media.feed' => true, // Sosyal medya: Akış sekmesi
    'social_media.feed.watch' => true, // Sosyal medya: Akış > Rakipler ve kurumlar kutusu
    'social_media.feed.storage' => false, // Sosyal medya: Akış > Depolama kutusu
    'social_media.plan' => false, // Sosyal medya: Plan sekmesi
    'social_media.insights' => true, // Sosyal medya: İlham ve Rakipler sekmesi
    'social_media.analytics' => false, // Sosyal medya: Analiz sekmesi
    'social_media.blog' => false, // Sosyal medya: Blog yazma
    'social_media.executive_profiles' => false, // Sosyal medya: Yönetici hesabı (Hüseyin Güneş)
    'chat' => true, // Kurum içi sohbet
    'chat.groups' => false, // Sohbet: Grup açma
    'chat.work_requests' => false, // Sohbet: Mesajdan talep açma
    'notifications' => true, // Bildirimler
    'notifications.inbox' => false, // Tüm bildirimler tablosu
    'notifications.announcements' => true, // Duyurular
    'notifications.business_alerts' => true, // İş uyarıları
    'notifications.desktop_alerts' => false, // Masaüstü uyarıları
    'notifications.desktop_alerts.sound' => false, // Bildirim sesi
    'notifications.desktop_alerts.windows' => false, // Windows bildirimi
    'tools' => true, // Genel araçlar
    'tools.quick_actions' => false, // Hızlı işlemler
    'tools.exports' => false, // Dışa aktarım
    'tools.exports.excel' => false, // Excel indirme
    'tools.exports.pdf' => false, // PDF indirme
    'tools.release_notes' => false, // Sürüm notları
    'tools.ui_gallery' => false, // UI Deneme
];
