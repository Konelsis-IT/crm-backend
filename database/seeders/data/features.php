<?php

// Ozellik anahtarlari (D-128): FeatureSeeder bu dosyadaki durumlari canlidaki
// `features` tablosuna uygular. true = acik, false = kapali. Katalog sirasi.
// Uretim: php artisan konelsis:features:export (25.09.2026 16:32, yerel veritabani).
// Elle de duzenlenebilir; katalogda olmayan kod atlanir.

return [
    'personnel' => true, // Personel ve organizasyon
    'personnel.org_units' => true, // Organizasyon birimleri
    'personnel.positions' => true, // Pozisyonlar
    'personnel.competencies' => true, // Yetkinlikler
    'personnel.certifications' => true, // Sertifikalar
    'personnel.trainings' => true, // Eğitimler
    'personnel.activities' => true, // Personel Hareketleri
    'approvals' => true, // Onay motoru
    'approvals.requests' => true, // Onaylar ekranı
    'approvals.policies' => true, // Onay politikaları
    'approvals.delegations' => true, // Vekaletler
    'documents' => true, // Doküman yönetimi
    'documents.legal_holds' => true, // Hukuki tutmalar
    'documents.transmittals' => true, // Teslim tutanakları
    'documents.types' => true, // Doküman tipleri
    'documents.templates' => true, // Doküman şablonları
    'acquisition' => true, // İş alım
    'acquisition.parties' => true, // Taraflar
    'acquisition.associations' => true, // Dernekler
    'acquisition.business_cases' => true, // İş dosyaları
    'acquisition.proposals' => true, // Teklifler
    'acquisition.contracts' => true, // Sözleşmeler
    'acquisition.operation_handoffs' => true, // Operasyona devirler
    'acquisition.tenders' => true, // İhaleler
    'acquisition.meeting_plans' => true, // Görüşme planı
    'acquisition.activity_areas' => true, // Faaliyet alanları
    'projects' => true, // Projeler
    'projects.supply_items' => true, // Tedarik kalemleri
    'projects.stage_gates' => true, // Proje onay kapıları
    'projects.catalogs' => true, // Proje ayarları
    'projects.catalogs.components' => true, // Proje bileşenleri
    'projects.catalogs.operation_groups' => true, // Operasyon grupları
    'projects.catalogs.focus_expectations' => true, // Odak beklentileri
    'projects.catalogs.stage_templates' => true, // Onay kapısı şablonları
    'work_requests' => true, // Talepler
    'work_requests.thread' => true, // Talep yazışması (talep sohbeti)
    'reports' => true, // Raporlar
    'work' => true, // İş takibi
    'work.items' => true, // İşler ekranı
    'work.board' => true, // İş panosu
    'work.control_matrix' => true, // Kontrol matrisi
    'work.control_matrix.personnel_tab' => true, // Personel kartı: Haftalık kontrol sekmesi
    'work.control_matrix.attention_card' => true, // Personel kartı: Dikkat kartı
    'work.analysis' => true, // İş raporları
    'work.analysis.dashboard' => true, // İş raporları: Analiz panosu
    'work.analysis.duration' => true, // İş raporları: Süre raporu
    'social_media' => true, // Sosyal medya
    'social_media.feed' => true, // Sosyal medya: Akış sekmesi
    'social_media.feed.watch' => true, // Sosyal medya: Akış > Rakipler ve kurumlar kutusu
    'social_media.feed.storage' => true, // Sosyal medya: Akış > Depolama kutusu
    'social_media.plan' => true, // Sosyal medya: Plan sekmesi
    'social_media.insights' => true, // Sosyal medya: İlham ve Rakipler sekmesi
    'social_media.analytics' => true, // Sosyal medya: Analiz sekmesi
    'social_media.blog' => true, // Sosyal medya: Blog yazma
    'social_media.executive_profiles' => true, // Sosyal medya: Yönetici hesabı (Hüseyin Güneş)
    'chat' => true, // Kurum içi sohbet
    'chat.groups' => true, // Sohbet: Grup açma
    'chat.work_requests' => true, // Sohbet: Mesajdan talep açma
    'notifications' => true, // Bildirimler
    'notifications.inbox' => true, // Tüm bildirimler tablosu
    'notifications.announcements' => true, // Duyurular
    'notifications.business_alerts' => true, // İş uyarıları
    'notifications.desktop_alerts' => true, // Masaüstü uyarıları
    'notifications.desktop_alerts.sound' => true, // Bildirim sesi
    'notifications.desktop_alerts.windows' => true, // Windows bildirimi
    'tools' => true, // Genel araçlar
    'tools.quick_actions' => true, // Hızlı işlemler
    'tools.exports' => true, // Dışa aktarım
    'tools.exports.excel' => true, // Excel indirme
    'tools.exports.pdf' => true, // PDF indirme
    'tools.release_notes' => false, // Sürüm notları
    'tools.ui_gallery' => true, // UI Deneme
];
