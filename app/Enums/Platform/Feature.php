<?php

declare(strict_types=1);

namespace App\Enums\Platform;

use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\OperationHandoff;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderNotice;
use App\Models\Acquisition\TenderSource;
use App\Models\Activity\PersonnelActivity;
use App\Models\Approval\ApprovalPolicy;
use App\Models\Approval\ApprovalRequest;
use App\Models\Approval\Delegation;
use App\Models\Document\Document;
use App\Models\Document\DocumentTemplate;
use App\Models\Document\LegalHold;
use App\Models\Document\Transmittal;
use App\Models\Notification\Announcement;
use App\Models\Notification\BusinessAlert;
use App\Models\Notification\PanelNotification;
use App\Models\Party\ActivityArea;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\Certification;
use App\Models\Personnel\Competency;
use App\Models\Personnel\PersonnelCertification;
use App\Models\Personnel\PersonnelCompetency;
use App\Models\Personnel\Training;
use App\Models\Personnel\TrainingAttendance;
use App\Models\Project\Project;
use App\Models\Project\ProjectStageInstance;
use App\Models\Project\ProjectSupplyItem;
use App\Models\Project\StageTemplate;
use App\Models\Report\Report;
use App\Models\Report\WorkItem;
use App\Models\SocialMedia\SocialContent;
use App\Models\WorkRequest\WorkRequest;

/**
 * Ozellik katalogu (D-128, 25 Eylul 2026 kullanici karari): gelistirilen her
 * ozellik burada bir satirdir; acik / kapali durumu YALNIZ veritabanindaki
 * `features` tablosunda (`is_active`) tutulur. Arayuzde ve .env'de ayari yoktur.
 *
 * - Kod noktalidir ve hiyerarsiyi tasir: `acquisition.proposals` ozelligi
 *   `acquisition` modulunun altindadir. Ust ozellik kapaliysa alttakiler de
 *   kapalidir.
 * - Katalog tabloyla kendiliginden esitlenir (FeatureRegistry): yeni bir
 *   ozellik ilk istekte `defaultActive()` durumuyla eklenir; ad / aciklama / ust
 *   ozellik degisirse guncellenir. Mevcut satirin `is_active` degerine
 *   uygulama asla dokunmaz.
 * - `models()` listesindeki kayit turleri, ozellik kapaliyken butun yetki
 *   kontrollerinde reddedilir (Gate::before): kaynak menusu, alt sekmeler,
 *   baglantilar ve dosya uclari birlikte kapanir. Yalniz bir ozellige ait
 *   kayit turleri yazilir; ortak kullanilanlar (Personel, Taraf, Organizasyon
 *   birimi) yazilmaz, onlarin ekranlari kendi canAccess kontroluyle kapanir.
 *
 * - Her ozelligin bir surumu vardir (D-151, version()). Canlida yayin surumu
 *   kayitliysa (`konelsis:release`) ondan buyuk surumdeki ozellikler gorunmez;
 *   kod canliya dogrudan gider, yeni ozellik surumu yayinlanana kadar bekler.
 *
 * Yeni ozellik gelistirirken: buraya bir durum ekle (tanim satirinda bir
 * sonraki yayinin surumu), ekranin canAccess / canView / visible kontrolunde
 * ve arka plan islerinde FeatureFlags::enabled(Feature::...) kullan.
 */
enum Feature: string
{
    /**
     * Sira gelen guncelleme (D-152, 3 Ekim 2026 kullanici karari: "Her
     * gelistirme bekletilebiliyorsa versiyonlanacak ve sirasi gelen
     * guncellemeyle acilacak. Kodla gidenler kodla gidecek."). Bugunden sonra
     * eklenen her bekletilebilir ozellik bu surumle kaydolur; yeni surum notu
     * maddeleri de bu numarali kayda yazilir. Kullanici bu surumu canlida
     * yayinladigini soyleyince bir artirilir (2.4 -> 2.5). Hicbir ozelligin
     * surumu bundan buyuk olamaz (safe-verify).
     */
    public const NEXT_RELEASE = '2.4';

    // Genel bakis (D-146, D-147)
    case Dashboard = 'dashboard';
    case DashboardStats = 'dashboard.stats';
    case DashboardAgenda = 'dashboard.agenda';
    case DashboardSendNotification = 'dashboard.send_notification';
    case DashboardAlerts = 'dashboard.alerts';
    case DashboardAnnouncements = 'dashboard.announcements';
    case DashboardSocial = 'dashboard.social';

    // Personel ve organizasyon
    case Personnel = 'personnel';
    case OrgUnits = 'personnel.org_units';
    case Positions = 'personnel.positions';
    case Competencies = 'personnel.competencies';
    case Certifications = 'personnel.certifications';
    case Trainings = 'personnel.trainings';
    case PersonnelActivities = 'personnel.activities';

    // Onay
    case Approvals = 'approvals';
    case ApprovalRequests = 'approvals.requests';
    case ApprovalPolicies = 'approvals.policies';
    case Delegations = 'approvals.delegations';

    // Dokuman yonetimi
    case Documents = 'documents';
    case LegalHolds = 'documents.legal_holds';
    case Transmittals = 'documents.transmittals';
    case DocumentTypes = 'documents.types';
    case DocumentTemplates = 'documents.templates';

    // Is alim
    case Acquisition = 'acquisition';
    case Parties = 'acquisition.parties';
    case Associations = 'acquisition.associations';
    case BusinessCases = 'acquisition.business_cases';
    case DealMeetingNotes = 'acquisition.business_cases.meeting_notes';
    case DealTrack = 'acquisition.deal_track';
    case Proposals = 'acquisition.proposals';
    case ProposalStatusTabs = 'acquisition.proposals.status_tabs';
    case Contracts = 'acquisition.contracts';
    case OperationHandoffs = 'acquisition.operation_handoffs';
    case Tenders = 'acquisition.tenders';
    case MeetingPlans = 'acquisition.meeting_plans';
    case ActivityAreas = 'acquisition.activity_areas';

    // Projeler
    case Projects = 'projects';
    case SupplyItems = 'projects.supply_items';
    case StageGates = 'projects.stage_gates';
    case ProjectCatalogs = 'projects.catalogs';
    case ProjectComponents = 'projects.catalogs.components';
    case OperationGroups = 'projects.catalogs.operation_groups';
    case FocusExpectations = 'projects.catalogs.focus_expectations';
    case StageTemplates = 'projects.catalogs.stage_templates';

    // Talepler ve raporlar
    case WorkRequests = 'work_requests';
    case WorkRequestThread = 'work_requests.thread';
    case Reports = 'reports';

    // Is takibi
    case Work = 'work';
    case WorkItems = 'work.items';
    case WorkBoard = 'work.board';
    case ControlMatrix = 'work.control_matrix';
    case ControlPersonnelTab = 'work.control_matrix.personnel_tab';
    case AttentionCard = 'work.control_matrix.attention_card';
    case WorkAnalysis = 'work.analysis';
    case AnalysisDashboard = 'work.analysis.dashboard';
    case DurationReport = 'work.analysis.duration';

    // Sosyal medya (sekmeler ve parcalar; React ekraninda boot.features ile gizlenir)
    case SocialMedia = 'social_media';
    case SocialFeed = 'social_media.feed';
    case SocialWatchBox = 'social_media.feed.watch';
    case SocialStorageBox = 'social_media.feed.storage';
    case SocialPlan = 'social_media.plan';
    case SocialInsights = 'social_media.insights';
    case SocialAnalytics = 'social_media.analytics';
    case SocialBlog = 'social_media.blog';
    case SocialExecutiveProfiles = 'social_media.executive_profiles';

    // Sohbet
    case Chat = 'chat';
    case ChatGroups = 'chat.groups';
    case ChatWorkRequests = 'chat.work_requests';

    // Bildirimler
    case Notifications = 'notifications';
    case NotificationsInbox = 'notifications.inbox';
    case Announcements = 'notifications.announcements';
    case BusinessAlerts = 'notifications.business_alerts';
    case DesktopAlerts = 'notifications.desktop_alerts';
    case AlertSound = 'notifications.desktop_alerts.sound';
    case AlertWindows = 'notifications.desktop_alerts.windows';

    // Genel araclar
    case Tools = 'tools';
    case QuickActions = 'tools.quick_actions';
    case Exports = 'tools.exports';
    case ExcelExport = 'tools.exports.excel';
    case PdfExport = 'tools.exports.pdf';
    case ReleaseNotes = 'tools.release_notes';
    case UiGallery = 'tools.ui_gallery';

    /** Ust ozellik; kod noktasindan once gelen kisim. */
    public function parent(): ?self
    {
        $position = strrpos($this->value, '.');

        return $position === false ? null : self::tryFrom(substr($this->value, 0, $position));
    }

    /** Tabloya yazilan ad (yalniz veritabaninda okunur). */
    public function title(): string
    {
        return $this->definition()[0];
    }

    /** Tabloya yazilan aciklama: ozellik kapaninca ne kaybolur. */
    public function description(): string
    {
        return $this->definition()[1];
    }

    /** Ozelligi getiren karar (docs/planning/17). */
    public function decision(): ?string
    {
        return $this->definition()[2];
    }

    /**
     * Ozelligin yayinlandigi surum (D-151, 2 Ekim 2026 kullanici karari):
     * kullanicinin ozelligi ilk gordugu commit'in surumu (git gecmisinde
     * "v1.5" -> "1.5"; ilk commit 1.0). Canlida bir yayin surumu kayitliysa,
     * ondan buyuk surumdeki ozellik anahtari acik olsa bile gorunmez
     * (FeatureRegistry); o surum yayinlaninca kendiliginden acilir. Yeni
     * gelistirilen ozellik bir sonraki yayinin surumunu tasir.
     */
    public function version(): string
    {
        return $this->definition()[3];
    }

    /**
     * Tabloya ilk yazildigindaki durum. Surum notlari kapali baslar (25 Eylul
     * 2026 kullanici karari: arka planda yazilmaya devam eder, arayuzde gorunmez).
     */
    public function defaultActive(): bool
    {
        return $this !== self::ReleaseNotes;
    }

    /** Katalogdaki sira (tabloda sort_order). */
    public function sortOrder(): int
    {
        return (int) array_search($this, self::cases(), true) + 1;
    }

    /**
     * Ozellik kapaliyken yetki kontrollerinde reddedilen kayit turleri.
     *
     * @return list<class-string>
     */
    public function models(): array
    {
        return match ($this) {
            self::Competencies => [Competency::class, PersonnelCompetency::class],
            self::Certifications => [Certification::class, PersonnelCertification::class],
            self::Trainings => [Training::class, TrainingAttendance::class],
            self::PersonnelActivities => [PersonnelActivity::class],
            self::Approvals => [ApprovalRequest::class],
            self::ApprovalPolicies => [ApprovalPolicy::class],
            self::Delegations => [Delegation::class],
            self::Documents => [Document::class],
            self::LegalHolds => [LegalHold::class],
            self::Transmittals => [Transmittal::class],
            self::DocumentTemplates => [DocumentTemplate::class],
            self::BusinessCases => [BusinessCase::class],
            self::Proposals => [Proposal::class],
            self::Contracts => [Contract::class],
            self::OperationHandoffs => [OperationHandoff::class],
            self::Tenders => [TenderNotice::class, TenderSource::class],
            self::MeetingPlans => [MeetingPlan::class],
            self::ActivityAreas => [ActivityArea::class],
            self::Projects => [Project::class],
            self::SupplyItems => [ProjectSupplyItem::class],
            self::StageGates => [ProjectStageInstance::class],
            self::StageTemplates => [StageTemplate::class],
            self::WorkRequests => [WorkRequest::class],
            self::Reports => [Report::class],
            self::Work => [WorkItem::class],
            self::SocialMedia => [SocialContent::class],
            self::NotificationsInbox => [PanelNotification::class],
            self::Announcements => [Announcement::class],
            self::BusinessAlerts => [BusinessAlert::class],
            default => [],
        };
    }

    /**
     * [ad, aciklama, karar, surum]. Yeni ozellik satiri dort ogeyle yazilir;
     * surum bir sonraki yayinin numarasidir (safe-verify denetler).
     *
     * @return array{0: string, 1: string, 2: string|null, 3: string}
     */
    private function definition(): array
    {
        return match ($this) {
            self::Dashboard => ['Genel bakış', 'Genel bakış sayfasındaki bütün bölümler: sayılar, Görevlerim ve işlerim, Duyuru gönder düğmesi, yaklaşan tarihler, duyurular, sosyal medya. Kapanınca sayfada yalnız başlık kalır; alt özellikler de kapanır.', 'D-146', '1.0'],
            self::DashboardStats => ['Genel bakış: Sayılar', 'Başlığın yanındaki Bugün, Geciken ve Yaklaşan iş sayıları.', 'D-146', '2.3'],
            self::DashboardAgenda => ['Genel bakış: Görevlerim ve işlerim', 'Kişinin kendi işlerinin tablosu (Geciken / Bugün / Yarın sekmeleri).', 'D-147', '1.9'],
            self::DashboardSendNotification => ['Genel bakış: Duyuru gönder düğmesi', 'Genel bakıştaki turuncu "Duyuru gönder" düğmesi. Sağ üst menüdeki "Duyuru gönder" Duyurular özelliğine bağlı kalır.', 'D-146 / D-148', '1.0'],
            self::DashboardAlerts => ['Genel bakış: Yaklaşan tarihler ve uyarılar', 'Genel bakıştaki uyarı kartları. Uyarılar üretilmeye ve zile düşmeye devam eder.', 'D-146', '1.0'],
            self::DashboardAnnouncements => ['Genel bakış: Duyurular', 'Genel bakıştaki son 3 duyuru. Duyuru gönderme ve zil bildirimleri etkilenmez.', 'D-146', '1.0'],
            self::DashboardSocial => ['Genel bakış: Yaklaşan sosyal medya paylaşımları', 'Genel bakıştaki sosyal medya bölümü. Sosyal Medya ekranı etkilenmez.', 'D-146', '1.5'],

            self::Personnel => ['Personel ve organizasyon', 'Personel listesi ve personel kartı. Kapanınca Personel menüsü ve personel sayfaları kaybolur; alt özellikler de kapanır. Giriş ve profil sayfası etkilenmez.', 'D-42', '1.0'],
            self::OrgUnits => ['Organizasyon birimleri', 'İdari > Organizasyon Birimleri ekranı.', 'D-62', '1.0'],
            self::Positions => ['Pozisyonlar', 'İdari > Pozisyonlar ekranı.', 'D-62', '1.0'],
            self::Competencies => ['Yetkinlikler', 'Ayarlar > Yetkinlikler ekranı ve personel kartındaki yetkinlik sekmesi.', null, '1.0'],
            self::Certifications => ['Sertifikalar', 'Ayarlar > Sertifikalar ekranı ve personel kartındaki sertifika sekmesi.', 'D-60', '1.0'],
            self::Trainings => ['Eğitimler', 'Ayarlar > Eğitimler ekranı ve personel kartındaki eğitim sekmesi.', 'D-60', '1.0'],
            self::PersonnelActivities => ['Personel Hareketleri', 'Personel kartındaki Personel Hareketleri sekmesi. Hareketler kaydedilmeye devam eder.', 'D-47', '2.4'],

            self::Approvals => ['Onay motoru', 'Onay talepleri: dokümanda "Onaya gönder", Onaylar sekmeleri, onay bildirimleri. Alt özellikler de kapanır.', 'D-76', '1.0'],
            self::ApprovalRequests => ['Onaylar ekranı', 'Üst menüdeki Onaylar listesi (bana gelenler, taleplerim).', 'D-76', '1.0'],
            self::ApprovalPolicies => ['Onay politikaları', 'Ayarlar > Onay Politikaları, sürümleri ve adımları.', 'D-76', '1.0'],
            self::Delegations => ['Vekaletler', 'İdari > Vekaletler ekranı.', 'D-76', '2.4'],

            self::Documents => ['Doküman yönetimi', 'Dokümanlar ekranı, projedeki Dokümanlar sekmesi, belge indirme. Alt özellikler de kapanır.', 'D-66', '1.0'],
            self::LegalHolds => ['Hukuki tutmalar', 'Doküman > Hukuki Tutmalar ekranı.', 'D-66', '2.4'],
            self::Transmittals => ['Teslim tutanakları', 'Doküman > Teslim Tutanakları ekranı.', 'D-66', '2.4'],
            self::DocumentTypes => ['Doküman tipleri', 'Ayarlar > Doküman Tipleri ekranı.', 'D-66', '1.0'],
            self::DocumentTemplates => ['Doküman şablonları', 'Ayarlar > Şablonlar ekranı.', 'D-66', '1.0'],

            self::Acquisition => ['İş alım', 'İş Alım menüsünün tamamı: taraflar, potansiyel işler, teklifler, sözleşmeler, ihaleler, görüşme planı. Alt özellikler de kapanır.', 'D-67', '1.0'],
            self::Parties => ['Taraflar', 'İş Alım > Taraflar ekranı ve taraf kartı.', 'D-67', '1.0'],
            self::Associations => ['Dernekler', 'İş Alım > Dernekler ekranı.', 'D-107', '1.7'],
            self::BusinessCases => ['Potansiyel işler', 'İş Alım > Potansiyel İşler, sihirbaz ve diğer kartlardaki potansiyel iş sekmeleri.', 'D-101', '1.0'],
            self::DealMeetingNotes => ['Potansiyel iş ve teklif görüşme notları', 'Potansiyel iş ve teklif sayfalarındaki Görüşme notları sekmesi; taraf görüşme notunda potansiyel iş ve teklif seçimi.', 'D-137', '2.2'],
            self::DealTrack => ['Bu iş nerede?', 'Teklif ve potansiyel iş sayfalarındaki "Bu iş nerede?" bölümü (potansiyel iş → teklif → proje hattı, özet etiketler, Projeye dönüştür durağı). Sayfanın kartları ve sekmeleri etkilenmez.', 'D-143', '2.3'],
            self::Proposals => ['Teklifler', 'İş Alım > Teklifler, teklif sürümleri ve maliyet tahminleri.', 'D-67', '1.0'],
            self::ProposalStatusTabs => ['Teklifler: Durum sekmeleri', 'Teklifler listesindeki Tümü / Verilen Teklifler / Verilecek Teklifler / Kaçan Fırsat sekmeleri. Kapanınca liste sekmesiz gelir.', 'D-136', '2.2'],
            self::Contracts => ['Sözleşmeler', 'İş Alım > Sözleşmeler ve sözleşme sürümleri.', 'D-67', '2.4'],
            self::OperationHandoffs => ['Operasyona devirler', 'Tekliften operasyona devir kayıtları ve sürümleri.', 'D-67', '1.0'],
            self::Tenders => ['İhaleler', 'İhaleler menüsü: ihale ilanları ve ihale kaynakları.', 'D-107', '1.0'],
            self::MeetingPlans => ['Görüşme planı', 'İş Alım > Görüşme Planı, takvimi, hatırlatmaları ve taraf kartındaki görüşme sekmesi.', 'D-109', '1.7'],
            self::ActivityAreas => ['Faaliyet alanları', 'Ayarlar > Faaliyet Alanları ekranı (pazar haritası).', 'D-107', '1.7'],

            self::Projects => ['Projeler', 'Projeler ekranı, proje kartı ve alt ekranları (workstream, iş kırılımı, iş paketleri, gecikmeler, departman devirleri). Alt özellikler de kapanır.', 'D-67', '1.0'],
            self::SupplyItems => ['Tedarik kalemleri', 'Satın Alma > Tedarik Kalemleri ve projedeki tedarik sekmesi.', 'D-68', '1.0'],
            self::StageGates => ['Proje onay kapıları', 'Proje kartındaki onay kapıları (kanıt, inceleme, muafiyet).', 'D-67', '1.0'],
            self::ProjectCatalogs => ['Proje ayarları', 'Ayarlar altındaki proje tanımları: bileşenler, operasyon grupları, odak beklentileri, onay kapısı şablonları. Alt özellikler de kapanır.', 'D-68', '1.0'],
            self::ProjectComponents => ['Proje bileşenleri', 'Ayarlar > Proje Bileşenleri ekranı.', 'D-67', '2.4'],
            self::OperationGroups => ['Operasyon grupları', 'Ayarlar > Operasyon Grupları ekranı.', 'D-67', '1.0'],
            self::FocusExpectations => ['Odak beklentileri', 'Ayarlar > Odak Beklentileri ekranı.', 'D-68', '2.4'],
            self::StageTemplates => ['Onay kapısı şablonları', 'Ayarlar > Onay Kapısı Şablonları, şablon sürümleri ve kapı tanımları.', 'D-67', '2.4'],

            self::WorkRequests => ['Talepler', 'Talepler ekranı, onaya tabi talepler, talep dosyaları ve kartlardaki talep sekmeleri. Alt özellik (talep yazışması) de kapanır.', 'D-84', '1.0'],
            self::WorkRequestThread => ['Talep yazışması (talep sohbeti)', 'Talep sayfasındaki Yazışma bölümü: cevap yazma ve dosya ekleme. Talebin kendisi etkilenmez.', 'D-108', '1.7'],
            self::Reports => ['Raporlar', 'Raporlar ekranı ve kartlardaki rapor sekmeleri.', 'D-86', '1.2'],

            self::Work => ['İş takibi', 'İş kartları: İşler, İş panosu, Kontrol matrisi, Analizler. Alt özellikler de kapanır.', 'D-115', '1.9'],
            self::WorkItems => ['İşler ekranı', 'Raporlar > İşler listesi ve genel bakıştaki Görevlerim ve işlerim ile sayılar.', 'D-115', '1.9'],
            self::WorkBoard => ['İş panosu', 'İş panosu ekranı, üst çubuktaki İş panosu düğmesi, gün / hafta kapatma.', 'D-115', '1.9'],
            self::ControlMatrix => ['Kontrol matrisi', 'Kontrol matrisi ekranı ve üst çubuktaki düğmesi. Alt özellikler (personel kartındaki parçalar) de kapanır.', 'D-116', '1.9'],
            self::ControlPersonnelTab => ['Personel kartı: Haftalık kontrol sekmesi', 'Personel kartındaki Haftalık kontrol sekmesi (kontrol matrisi kayıtları).', 'D-116', '2.4'],
            self::AttentionCard => ['Personel kartı: Dikkat kartı', 'Personel kartındaki Dikkat kartı (son 12 haftanın kontrol matrisi özeti).', 'D-115', '2.4'],
            self::WorkAnalysis => ['İş raporları', 'Analizler > İş raporları menüsü. Alt özellikler (analiz panosu, süre raporu) de kapanır.', 'D-116', '2.4'],
            self::AnalysisDashboard => ['İş raporları: Analiz panosu', 'Analizler > İş raporları > Analiz panosu.', 'D-116', '2.4'],
            self::DurationReport => ['İş raporları: Süre raporu', 'Analizler > İş raporları > Süre raporu.', 'D-116', '2.4'],

            self::SocialMedia => ['Sosyal medya', 'Sosyal Medya ekranı ve genel bakıştaki yaklaşan içerikler kutusu. Alt özellikler (sekmeler ve parçalar) de kapanır.', 'D-106', '1.5'],
            self::SocialFeed => ['Sosyal medya: Akış sekmesi', 'Akış sekmesi (içerik kartları, onay bekleyenler). Kapalıysa ekran ilk açık sekmeyle açılır.', 'D-106', '2.4'],
            self::SocialWatchBox => ['Sosyal medya: Akış > Rakipler ve kurumlar kutusu', 'Akış sekmesinin yanındaki rakip ve kurum hesapları kutusu (rakip analiz).', 'D-106', '1.5'],
            self::SocialStorageBox => ['Sosyal medya: Akış > Depolama kutusu', 'Akış sekmesinin yanındaki disk / depolama durumu kutusu (donanım kontrolü).', 'D-106', '2.4'],
            self::SocialPlan => ['Sosyal medya: Plan sekmesi', 'Plan sekmesi (takvim ve ajanda).', 'D-106', '2.4'],
            self::SocialInsights => ['Sosyal medya: İlham ve Rakipler sekmesi', 'İlham ve Rakipler sekmesi (takip edilen hesaplar, şirket kataloğu).', 'D-106', '1.5'],
            self::SocialAnalytics => ['Sosyal medya: Analiz sekmesi', 'Analiz sekmesi ve platform istatistikleri girişi.', 'D-106', '2.4'],
            self::SocialBlog => ['Sosyal medya: Blog yazma', 'İçerik ekleme menüsündeki "Blog" türü. Kapalıyken yeni blog yazısı açılamaz; eski blog yazıları görünmeye devam eder.', 'D-106', '2.4'],
            self::SocialExecutiveProfiles => ['Sosyal medya: Yönetici hesabı (Hüseyin Güneş)', 'Yönetici hesapları (şu an Hüseyin Güneş Resmi Hesap): hesap seçicide görünmez, bu hesaba içerik açılamaz.', 'D-106', '2.4'],

            self::Chat => ['Kurum içi sohbet', 'Sağ alttaki sohbet düğmesi ve penceresi. Alt özellikler de kapanır.', 'D-83', '1.0'],
            self::ChatGroups => ['Sohbet: Grup açma', 'Sohbette "Yeni grup" düğmesi ve grup oluşturma. Var olan gruplar kullanılmaya devam eder.', 'D-83', '2.4'],
            self::ChatWorkRequests => ['Sohbet: Mesajdan talep açma', 'Sohbet mesajının yanındaki "Talep aç" düğmesi (mesajı talebe dönüştürür).', 'D-84', '2.4'],

            self::Notifications => ['Bildirimler', 'Filament bildirim zili ve bildirim gönderimi. Alt özellikler de kapanır.', 'D-49', '1.0'],
            self::NotificationsInbox => ['Tüm bildirimler tablosu', 'Zilin yanındaki "Tüm bildirimleri gör" düğmesi ve tablosu.', 'D-122', '2.4'],
            self::Announcements => ['Duyurular', 'Kullanıcı menüsündeki "Duyuru gönder" ve genel bakıştaki duyurular.', 'D-82 / D-148', '1.0'],
            self::BusinessAlerts => ['İş uyarıları', 'Genel bakıştaki uyarılar kutusu ve uyarıyı bildirimden görüldü yapma.', 'D-82', '1.0'],
            self::DesktopAlerts => ['Masaüstü uyarıları', 'Yeni bildirim ve sohbet mesajı yoklaması. Alt özellikler (ses, Windows bildirimi) de kapanır.', 'D-126', '2.4'],
            self::AlertSound => ['Bildirim sesi', 'Yeni bildirim ya da sohbet mesajı gelince çalan kısa ses.', 'D-126', '2.4'],
            self::AlertWindows => ['Windows bildirimi', 'Başka sekmedeyken ya da pencere arkadayken çıkan Windows bildirimi ve kullanıcı menüsündeki "Masaüstü bildirimlerini aç".', 'D-126', '2.4'],

            self::Tools => ['Genel araçlar', 'Hızlı işlemler, Excel / PDF dışa aktarım, sürüm notları, UI Deneme. Alt özellikler de kapanır.', null, '1.0'],
            self::QuickActions => ['Hızlı işlemler', 'Üst çubuktaki Hızlı işlemler düğmesi ve kişisel seçim ekranı.', 'D-122', '2.4'],
            self::Exports => ['Dışa aktarım', 'Excel ve PDF indirme. Alt özellikler de kapanır.', 'D-110', '2.4'],
            self::ExcelExport => ['Excel indirme', 'Listelerdeki Excel düğmesi ve detay sayfalarındaki "Excel" seçeneği.', 'D-110', '2.4'],
            self::PdfExport => ['PDF indirme', 'Detay sayfalarındaki "PDF" seçeneği.', 'D-110', '2.4'],
            self::ReleaseNotes => ['Sürüm notları', 'Kullanıcı menüsündeki sürüm notları penceresi. Notlar kodda yazılmaya devam eder; kapalıyken arayüzde görünmez.', 'D-91', '2.4'],
            self::UiGallery => ['UI Deneme', 'UI Deneme kataloğu (yalnız tam yetkili kişiler).', 'D-77', '2.4'],
        };
    }
}
