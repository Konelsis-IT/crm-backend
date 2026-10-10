<?php

declare(strict_types=1);

namespace App\Enums\Platform;

use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\BusinessCaseChecklistAnswer;
use App\Models\Acquisition\Contract;
use App\Models\Acquisition\OperationHandoff;
use App\Models\Acquisition\ProjectReference;
use App\Models\Acquisition\ProjectReferenceScopeType;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\ProposalVersionScopeDocument;
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
use App\Models\Project\ProjectScope;
use App\Models\Project\ProjectTypeCoordinator;
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
    public const NEXT_RELEASE = '2.7';

    /**
     * Bekleme surumu (D-164, 6 Ekim 2026 kullanici karari: "Ben sana bunlari
     * ac dedigimi hatirlamiyorum, surum olarak simdilik bu ozellikleri direk 5
     * surumune ekle. Gunu geldikce hangilerini hangi surume dusurecegini
     * soyleyecegim."). Bu surumdeki ozellikler hicbir yayinla acilmaz; kullanici
     * bir ozelligi bir yayina aldiginda surumu o yayinin numarasina cekilir.
     * NEXT_RELEASE'ten buyuk tek izinli surum budur (safe-verify).
     */
    public const PARKED = '5.0';

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
    // D-176: kayitta birikmis belgelerin klasorlu ZIP'i (teklif, potansiyel is).
    case DocumentBundles = 'documents.bundles';
    // D-186: Excel / CSV / Word dosyalarini indirmeden onizleme (ek paket yok).
    case DocumentOfficePreview = 'documents.office_preview';

    // Is alim
    case Acquisition = 'acquisition';
    case Parties = 'acquisition.parties';
    case Associations = 'acquisition.associations';
    case BusinessCases = 'acquisition.business_cases';
    case DealMeetingNotes = 'acquisition.business_cases.meeting_notes';
    case BusinessCaseChecklist = 'acquisition.business_cases.checklist';
    case BusinessCaseDocuments = 'acquisition.business_cases.documents';
    case BusinessCaseStageTabs = 'acquisition.business_cases.stage_tabs';
    case DealTrack = 'acquisition.deal_track';
    case AcquisitionDrafts = 'acquisition.drafts';
    // D-178: teklif ve potansiyel is detayinda durum menusu (kapaliyken D-161 sabit dugme).
    case AcquisitionQuickStatus = 'acquisition.quick_status';
    case Proposals = 'acquisition.proposals';
    case ProposalStatusTabs = 'acquisition.proposals.status_tabs';
    // D-176: Dokumanlar'daki Genel katalog her teklifte otomatik (kopya yok, okumada iliski).
    case AutomaticDocuments = 'acquisition.proposals.automatic_documents';
    // D-181: teklif kapsaminda kapsam listesinin yaninda Maliyet listesi (B51).
    case ProposalCostLists = 'acquisition.proposals.cost_lists';
    case Contracts = 'acquisition.contracts';
    case OperationHandoffs = 'acquisition.operation_handoffs';
    case Tenders = 'acquisition.tenders';
    case MeetingPlans = 'acquisition.meeting_plans';
    case MeetingPlanQuickParty = 'acquisition.meeting_plans.quick_party';
    case ActivityAreas = 'acquisition.activity_areas';
    // D-177: Referanslar, teklifteki referans listesi, Otomasyon / Process tipi, proje tipi ekleme.
    // D-183: Referanslar Ayarlar'da sekmeli; teklifte kapsam bolumu basliginda Referanslar + indir.
    case References = 'acquisition.references';
    case ReferenceExcel = 'acquisition.references.excel';
    case ProposalReferences = 'acquisition.references.proposal_field';
    case ScopeAutomation = 'acquisition.scope_automation';
    case ScopeTypeAdd = 'acquisition.scope_type_add';

    // Projeler
    case Projects = 'projects';
    // D-174: kisa ad + Lisans adi, proje tipleri (B48 uygulanana kadar ekranda yok).
    case ProjectShortName = 'projects.short_name';
    case ProjectScopeTypes = 'projects.scope_types';
    // D-175: proje tipi koordinatorleri (B49 uygulanana kadar ekranda yok).
    case ProjectTypeCoordinators = 'projects.type_coordinators';
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
    // Arayuze ozel disa aktarim (D-167): genel Excel / PDF (tools.exports) kapali
    // kalirken her ekran kendi anahtariyla kademe kademe acilir; ilki raporlar.
    case ReportExports = 'reports.exports';
    case ReportPdf = 'reports.exports.pdf';
    case ReportExcel = 'reports.exports.excel';
    // D-179: gunluk / haftalik rapor yazilirken panodan is secme ve tek tikla
    // eklenen oneriler (is panosu kapaliysa ikisi de gorunmez).
    case ReportPickWorkItems = 'reports.pick_work_items';
    case ReportWorkSuggestions = 'reports.work_suggestions';

    // Is takibi
    case Work = 'work';
    case WorkItems = 'work.items';
    case WorkBoard = 'work.board';
    case WorkBoardCompact = 'work.board.compact';
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
    // Departman panolari denemesi (D-173, React): genel yerlesim + bilesen katalogu;
    // gorunen tablonun Excel / PDF'i arayuze ozel anahtarlarla (D-167).
    case UiDashboards = 'tools.ui_gallery.dashboards';
    case UiDashboardExports = 'tools.ui_gallery.dashboards.exports';
    case UiDashboardExcel = 'tools.ui_gallery.dashboards.exports.excel';
    case UiDashboardPdf = 'tools.ui_gallery.dashboards.exports.pdf';
    case UiDashboardComponents = 'tools.ui_gallery.dashboard_components';
    // Maliyet kalemleri denemesi (D-187, React): Excel maliyet listesinin Urun/Hizmet,
    // Idari Kadro, Genel Giderler sekmeleri, departman onaylari, katalog eslestirmesi, Icmal.
    case UiCostItems = 'tools.ui_gallery.cost_items';

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
            // Madde belgeleri de business_case_documents satiridir; Belgeler kapaliyken
            // kontrol listesi belgeleri reddedilmesin diye o kayit turu yazilmaz.
            self::BusinessCaseChecklist => [BusinessCaseChecklistAnswer::class],
            self::Proposals => [Proposal::class],
            self::Contracts => [Contract::class],
            self::OperationHandoffs => [OperationHandoff::class],
            self::Tenders => [TenderNotice::class, TenderSource::class],
            self::MeetingPlans => [MeetingPlan::class],
            self::ActivityAreas => [ActivityArea::class],
            self::References => [ProjectReference::class, ProjectReferenceScopeType::class],
            self::ProposalCostLists => [ProposalVersionScopeDocument::class],
            self::Projects => [Project::class],
            self::ProjectScopeTypes => [ProjectScope::class],
            self::ProjectTypeCoordinators => [ProjectTypeCoordinator::class],
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
            self::PersonnelActivities => ['Personel Hareketleri', 'Personel kartındaki Personel Hareketleri sekmesi. Hareketler kaydedilmeye devam eder.', 'D-47', '5.0'],

            self::Approvals => ['Onay motoru', 'Onay talepleri: dokümanda "Onaya gönder", Onaylar sekmeleri, onay bildirimleri. Alt özellikler de kapanır.', 'D-76', '1.0'],
            self::ApprovalRequests => ['Onaylar ekranı', 'Üst menüdeki Onaylar listesi (bana gelenler, taleplerim).', 'D-76', '1.0'],
            self::ApprovalPolicies => ['Onay politikaları', 'Ayarlar > Onay Politikaları, sürümleri ve adımları.', 'D-76', '1.0'],
            self::Delegations => ['Vekaletler', 'İdari > Vekaletler ekranı.', 'D-76', '5.0'],

            self::Documents => ['Doküman yönetimi', 'Dokümanlar ekranı, projedeki Dokümanlar sekmesi, belge indirme. Alt özellikler de kapanır.', 'D-66', '1.0'],
            self::LegalHolds => ['Hukuki tutmalar', 'Doküman > Hukuki Tutmalar ekranı.', 'D-66', '5.0'],
            self::Transmittals => ['Teslim tutanakları', 'Doküman > Teslim Tutanakları ekranı.', 'D-66', '5.0'],
            self::DocumentTypes => ['Doküman tipleri', 'Ayarlar > Doküman Tipleri ekranı.', 'D-66', '1.0'],
            self::DocumentTemplates => ['Doküman şablonları', 'Ayarlar > Şablonlar ekranı.', 'D-66', '1.0'],
            self::DocumentOfficePreview => ['Belge önizleme: Excel ve Word', 'Excel (xlsx, xlsm, ods), CSV ve Word (docx) dosyaları indirilmeden yeni sekmede önizlenir: Excel\'de sayfa sekmeleriyle tablo (ilk 500 satır, 50 sütun), Word\'de başlıklar, paragraflar ve tablolar. Belge kartlarında (teklif kapsam ve belge kutuları, potansiyel iş belgeleri) dosyaya tıklamak önizler, yanındaki küçük simge indirir; PDF ve görseller de yeni sekmede açılır. Teklifin Dokümanlar tablosunda "Önizle" simgesi. Kapanınca dosyalar eskisi gibi tıklayınca iner.', 'D-186', '2.6'],
            self::DocumentBundles => ['Tüm belgeleri indir','"Tüm belgeleri indir" düğmesi: teklif ve potansiyel iş sayfalarının ve düzenleme ekranlarının başlığında (simge), teklifin Dokümanlar sekmesinde ve potansiyel işin Belgeler kartında. Kayıttaki bütün belgeler (teklifte referans listesi Excel\'i dahil) klasörlere ayrılmış tek ZIP dosyası olarak iner. Kapanınca belgeler yine tek tek indirilir.', 'D-176, D-184', '2.6'],

            self::Acquisition => ['İş alım', 'İş Alım menüsünün tamamı: taraflar, potansiyel işler, teklifler, sözleşmeler, ihaleler, görüşme planı. Alt özellikler de kapanır.', 'D-67', '1.0'],
            self::Parties => ['Taraflar', 'İş Alım > Taraflar ekranı ve taraf kartı.', 'D-67', '1.0'],
            self::Associations => ['Dernekler', 'İş Alım > Dernekler ekranı.', 'D-107', '1.7'],
            self::BusinessCases => ['Potansiyel işler', 'İş Alım > Potansiyel İşler, sihirbaz ve diğer kartlardaki potansiyel iş sekmeleri.', 'D-101', '1.0'],
            self::DealMeetingNotes => ['Potansiyel iş ve teklif görüşme notları', 'Potansiyel iş ve teklif sayfalarındaki Görüşme notları sekmesi; taraf görüşme notunda potansiyel iş ve teklif seçimi.', 'D-137', '2.2'],
            self::BusinessCaseChecklist => ['Potansiyel iş: Teklif öncesi kontrol listesi', 'Potansiyel iş sihirbazındaki GES / TM kontrol listesi, proje durumu (lisans), madde belgeleri, kaydederken eksikler penceresi, teklif sıcaklığı ve 1.3 kuralı (çağrı mektubunun geçerliliği bittiyse teklif tipi Bütçesel). Kapanınca potansiyel iş kontrol listesiz açılır; kayıtlı cevaplar silinmez.', 'D-155', '2.4'],
            self::BusinessCaseDocuments => ['Potansiyel iş: Belgeler', 'Potansiyel iş sihirbazındaki belge yükleme alanı ve potansiyel iş sayfasındaki Belgeler kartı. Yüklenen belgeler Dokümanlar\'da kalır.', 'D-155', '2.4'],
            self::BusinessCaseStageTabs => ['İş Geliştirme: Durum sekmeleri', 'İş Geliştirme listesindeki Tümü / Yatırımcı Projeleri / Potansiyel İşler / Teklifte / Taslaklar sekmeleri; taslaklar yalnız kendi sekmesinde görünür. Kapanınca liste Tümü / Taslaklar sekmeleriyle gelir.', 'D-162', '2.4'],
            self::DealTrack => ['Bu iş nerede?', 'Teklif ve potansiyel iş sayfalarındaki "Bu iş nerede?" bölümü (potansiyel iş → teklif → proje hattı, özet etiketler, Projeye dönüştür durağı). Sayfanın kartları ve sekmeleri etkilenmez.', 'D-143', '2.3'],
            self::AcquisitionDrafts => ['Taslak kaydı: ihale, potansiyel iş, teklif', 'Sihirbaz adımlarındaki "Taslak olarak kaydet", listelerdeki Taslaklar sekmesi ve taslağın kaldığı adımdan açılması. Kapanınca kayıtlar normal kaydedilir.', 'D-155', '2.4'],
            self::AcquisitionQuickStatus => ['Detayda durum değiştirme: teklif, potansiyel iş', 'Teklif ve potansiyel iş detay sayfasının başındaki durum düğmesi (teklifte Teklif durumu: Verilecek / Verilen / Onaylandı / Kaçan fırsat): tıklayınca geçilebilecek durumlar kendi renkleri ve simgeleriyle açılır, seçilen durum hemen kaydedilir (yeni teklif sürümü açılmaz). Kapanınca durum detayda sabit düğme olarak görünür ve düzenleme ekranının başındaki durum düğmesinden değiştirilir.', 'D-178 / D-182', '2.6'],
            self::Proposals => ['Teklifler', 'İş Alım > Teklifler, teklif sürümleri ve maliyet tahminleri.', 'D-67', '1.0'],
            self::ProposalStatusTabs => ['Teklifler: Durum sekmeleri', 'Teklifler listesindeki Tümü / Verilen Teklifler / Verilecek Teklifler / Kaçan Fırsat sekmeleri. Kapanınca liste sekmesiz gelir.', 'D-136', '2.2'],
            self::AutomaticDocuments => ['Teklifler: Otomatik şirket belgeleri', 'Dokümanlar\'a yüklenen en yeni Genel katalog her teklifte kendiliğinden görünür: Teklif belgeleri bölümünün başlığındaki ve Dokümanlar sekmesindeki "Genel katalog" düğmesi ile Dokümanlar tablosundaki Genel katalog satırı PDF\'i yeni sekmede açar; katalog tüm belgeler ZIP\'ine de girer. Belge teklife kopyalanmaz. Kapanınca düğme ve satır görünmez, katalog teklife eskisi gibi "Genel kataloğu ekle" seçimiyle bağlanır.', 'D-176, D-184', '2.6'],
            self::ProposalCostLists => ['Teklif: Maliyet listesi', 'Teklif oluştur / düzenle ekranında her proje tipinin kapsam bölümünde, kapsam listesinin yanında "Maliyet listesi" yükleme alanı (Excel, birden fazla dosya; her dosya yeni belge, D-186); teklif sayfasındaki ve Sürümler penceresindeki kapsam kartında maliyet listeleri, tüm belgeler ZIP\'inde "Maliyet listeleri" klasörü ve doküman sayfasındaki bağlantısı. Kapanınca alan ve listeler görünmez; yüklenmiş belgeler Dokümanlar\'da kalır.', 'D-181', '2.8'],
            self::Contracts => ['Sözleşmeler', 'İş Alım > Sözleşmeler ve sözleşme sürümleri.', 'D-67', '2.4'],
            self::OperationHandoffs => ['Operasyona devirler', 'Tekliften operasyona devir kayıtları ve sürümleri.', 'D-67', '1.0'],
            self::Tenders => ['İhaleler', 'İhaleler menüsü: ihale ilanları ve ihale kaynakları.', 'D-107', '1.0'],
            self::MeetingPlans => ['Görüşme planı', 'İş Alım > Görüşme Planı, takvimi, hatırlatmaları ve taraf kartındaki görüşme sekmesi.', 'D-109', '1.7'],
            self::MeetingPlanQuickParty => ['Görüşme planı: Firma ekle', 'Görüşme planlarken firma listede yoksa firma alanının yanındaki "Firma ekle" penceresi; eklenen firma alanda seçili gelir. Kapanınca firma yalnız Taraflar ekranından eklenir.', 'D-156', '2.4'],
            self::ActivityAreas => ['Faaliyet alanları', 'Ayarlar > Faaliyet Alanları ekranı (pazar haritası).', 'D-107', '1.7'],
            self::References => ['Referanslar', 'Ayarlar > Referanslar ekranı: şirketin referans listesi proje tipine göre sekmelerde (Tümü, GES, HES, RES, BESS, TM, ENH/EİH, Otomasyon / Process; sayılarıyla); referans ekleme, düzenleme, arşivleme. Alt özellikler (Excel, teklifteki Referanslar düğmeleri) de kapanır; kayıtlı referanslar silinmez.', 'D-177, D-183', '2.6'],
            self::ReferenceExcel => ['Referanslar: Excel', 'Referans listesinin Excel çıktısı Excel\'deki biçimde (her proje tipi ayrı sayfa, "GES REFERANSLARIMIZ" başlığı, sıra no ve referans metni): Referanslar ekranında (açık sekmenin tipi), teklifteki referans penceresinde ve teklif kapsam bölümlerinin başlığındaki indir simgesinde.', 'D-177, D-183', '2.6'],
            self::ProposalReferences => ['Teklif: Referanslar', 'Teklif oluştur / düzenle adımında ve teklif sayfasındaki kapsam kartında her proje tipi bölümünün (GES kapsamı, BESS kapsamı…) başlığında "Referanslar" düğmesi ve yanında indir simgesi: düğme o tipin referans tablosunu (arama, süzgeç, Excel, Referans ekle) pencerede açar, simge o tipin referanslarını Excel olarak indirir. Teklifin Dokümanlar tablosunda da "Referans listesi" satırı (ör. "GES · 166 referans"): satır teklifin proje tiplerinin referanslarını Excel olarak indirir, referanslar teklife kopyalanmaz.', 'D-177, D-184', '2.6'],
            self::ScopeAutomation => ['Proje tipi: Otomasyon / Process', 'Potansiyel iş, teklif, proje, koordinatör ve firma faaliyet seçimlerinde "Otomasyon / Process" tipi; teklif kapsamında Toplam maliyet - Toplam satış ve kapsam belgesi. Kapanınca tip seçilemez; kayıtlı satırlar görünmeye devam eder.', 'D-177', '2.6'],
            self::ScopeTypeAdd => ['Yeni proje tipi eklemek istiyorum', 'Potansiyel iş düzenle, teklif oluştur ve teklif düzenle ekranında (teklifte Teklif bilgileri\'nin sağındaki Proje tipi bölümü, D-186) proje tipi seçimi bir onay kutusunun arkasında: kayıtlı tipler rozet olarak görünür, kutu işaretlenince yeni tip ve kapsam bölümleri eklenebilir (kayıtlı tip kaldırılmaz). Kapanınca potansiyel iş düzenlemede tip seçimi eskisi gibi açık, teklif düzenlemede tip seçimi yok.', 'D-177', '2.6'],

            self::Projects => ['Projeler', 'Projeler ekranı, proje kartı ve alt ekranları (workstream, iş kırılımı, iş paketleri, gecikmeler, departman devirleri). Alt özellikler de kapanır.', 'D-67', '1.0'],
            self::ProjectShortName => ['Projeler: Kısa ad ve Lisans adı', 'Projenin Kısa ad alanı; proje adı alanı "Lisans adı" olarak görünür. Listede kısa ad ve altında lisans adı, kartta ve diğer ekranlarda kısa ad. Kapanınca projeler eskisi gibi tek "Ad" ile görünür; kayıtlı kısa adlar silinmez.', 'D-174', '2.7'],
            self::ProjectScopeTypes => ['Projeler: Proje tipi', 'Proje oluştur / düzenle ekranında proje tipi seçimi (GES, RES, TM, HES, BESS, ENH/EİH) ve tip başına proje ölçüleri; listede proje tipi sütunu, proje sayfasında tipler ve kapsam kartı. Tekliften dönüşen projeye tipler yine kopyalanır; kapanınca yalnız ekranda görünmez.', 'D-174', '2.7'],
            self::ProjectTypeCoordinators => ['Projeler: Proje tipi koordinatörleri', 'Proje Grubu > Proje tipi koordinatörleri ekranı (her proje tipinin koordinatörü atanır, değiştirilir, kaldırılır); personel kartında ve detayında "GES koordinatörü" gibi rozet; proje sayfasında tiplerin koordinatörleri; proje listesinde koordinatör sütunu ve süzgeci. Kapanınca atamalar silinmez, yalnız görünmez.', 'D-175', '2.7'],
            self::SupplyItems => ['Tedarik kalemleri', 'Satın Alma > Tedarik Kalemleri ve projedeki tedarik sekmesi.', 'D-68', '1.0'],
            self::StageGates => ['Proje onay kapıları', 'Proje kartındaki onay kapıları (kanıt, inceleme, muafiyet).', 'D-67', '1.0'],
            self::ProjectCatalogs => ['Proje ayarları', 'Ayarlar altındaki proje tanımları: bileşenler, operasyon grupları, odak beklentileri, onay kapısı şablonları. Alt özellikler de kapanır.', 'D-68', '1.0'],
            self::ProjectComponents => ['Proje bileşenleri', 'Ayarlar > Proje Bileşenleri ekranı.', 'D-67', '5.0'],
            self::OperationGroups => ['Operasyon grupları', 'Ayarlar > Operasyon Grupları ekranı.', 'D-67', '1.0'],
            self::FocusExpectations => ['Odak beklentileri', 'Ayarlar > Odak Beklentileri ekranı.', 'D-68', '5.0'],
            self::StageTemplates => ['Onay kapısı şablonları', 'Ayarlar > Onay Kapısı Şablonları, şablon sürümleri ve kapı tanımları.', 'D-67', '5.0'],

            self::WorkRequests => ['Talepler', 'Talepler ekranı, onaya tabi talepler, talep dosyaları ve kartlardaki talep sekmeleri. Alt özellik (talep yazışması) de kapanır.', 'D-84', '1.0'],
            self::WorkRequestThread => ['Talep yazışması (talep sohbeti)', 'Talep sayfasındaki Yazışma bölümü: cevap yazma ve dosya ekleme. Talebin kendisi etkilenmez.', 'D-108', '1.7'],
            self::Reports => ['Raporlar', 'Raporlar ekranı ve kartlardaki rapor sekmeleri.', 'D-86', '1.2'],
            self::ReportExports => ['Raporlar: Dışa aktarım', 'Rapor listesi ve rapor detayındaki PDF ve Excel indirme. Genel dışa aktarımdan (Araçlar) bağımsızdır. Alt özellikler de kapanır.', 'D-167', '2.4'],
            self::ReportPdf => ['Raporlar: PDF', 'Rapora özel düzenli PDF çıktısı (kişi, tarih, biçimli rapor metni).', 'D-167', '2.4'],
            self::ReportExcel => ['Raporlar: Excel', 'Raporun Excel çıktısı (özet ve rapor satırları).', 'D-167', '2.4'],
            self::ReportPickWorkItems => ['Raporlar: Panodan iş ekleme', 'Günlük / haftalık rapor oluştur ve düzenle ekranında "Panodan iş ekle" seçim kutusu: kişinin iş panosunda daha önce oluşmuş işleri (dönemin işleri önce) aranıp rapora iş kalemi olarak eklenir. Kapanınca işler yine elle yazılır; dönemin kartları eskisi gibi kendiliğinden gelir.', 'D-179', '2.6'],
            self::ReportWorkSuggestions => ['Raporlar: Yazarken öneriler', 'Günlük / haftalık rapor oluştur ve düzenle ekranında Öneriler listesi: dönemin rapora girmemiş işleri ve iş panosu önerileri tek tıkla "Ekle" ile rapora eklenir; eklenenler "Eklendi" olarak işaretlenir. Kapanınca liste görünmez.', 'D-179', '2.6'],

            self::Work => ['İş takibi', 'İş kartları: İşler, İş panosu, Kontrol matrisi, Analizler. Alt özellikler de kapanır.', 'D-115', '1.9'],
            self::WorkItems => ['İşler ekranı', 'Raporlar > İşler listesi ve genel bakıştaki Görevlerim ve işlerim ile sayılar.', 'D-115', '1.9'],
            self::WorkBoard => ['İş panosu', 'İş panosu ekranı, üst çubuktaki İş panosu düğmesi, gün / hafta kapatma.', 'D-115', '1.9'],
            self::WorkBoardCompact => ['İş panosu: sade görünüm', 'İş panosunda süzgeçler süzgeç simgesinin arkasında (Yoksayılanlar dahil), Hızlı iş ekle düğmesi, okunaklı renkli sütun başlıkları. Kapanınca eski görünüm gelir.', 'D-167', '2.6'],
            self::ControlMatrix => ['Kontrol matrisi', 'Kontrol matrisi ekranı ve üst çubuktaki düğmesi. Alt özellikler (personel kartındaki parçalar) de kapanır.', 'D-116', '1.9'],
            self::ControlPersonnelTab => ['Personel kartı: Haftalık kontrol sekmesi', 'Personel kartındaki Haftalık kontrol sekmesi (kontrol matrisi kayıtları).', 'D-116', '5.0'],
            self::AttentionCard => ['Personel kartı: Dikkat kartı', 'Personel kartındaki Dikkat kartı (son 12 haftanın kontrol matrisi özeti).', 'D-115', '5.0'],
            self::WorkAnalysis => ['İş raporları', 'Analizler > İş raporları menüsü. Alt özellikler (analiz panosu, süre raporu) de kapanır.', 'D-116', '5.0'],
            self::AnalysisDashboard => ['İş raporları: Analiz panosu', 'Analizler > İş raporları > Analiz panosu.', 'D-116', '5.0'],
            self::DurationReport => ['İş raporları: Süre raporu', 'Analizler > İş raporları > Süre raporu.', 'D-116', '5.0'],

            self::SocialMedia => ['Sosyal medya', 'Sosyal Medya ekranı ve genel bakıştaki yaklaşan içerikler kutusu. Alt özellikler (sekmeler ve parçalar) de kapanır.', 'D-106', '1.5'],
            self::SocialFeed => ['Sosyal medya: Akış sekmesi', 'Akış sekmesi (içerik kartları, onay bekleyenler). Kapalıysa ekran ilk açık sekmeyle açılır.', 'D-106 / D-154', '2.3'],
            self::SocialWatchBox => ['Sosyal medya: Akış > Rakipler ve kurumlar kutusu', 'Akış sekmesinin yanındaki rakip ve kurum hesapları kutusu (rakip analiz).', 'D-106', '1.5'],
            self::SocialStorageBox => ['Sosyal medya: Akış > Depolama kutusu', 'Akış sekmesinin yanındaki disk / depolama durumu kutusu (donanım kontrolü).', 'D-106', '5.0'],
            self::SocialPlan => ['Sosyal medya: Plan sekmesi', 'Plan sekmesi (takvim ve ajanda).', 'D-106', '5.0'],
            self::SocialInsights => ['Sosyal medya: İlham ve Rakipler sekmesi', 'İlham ve Rakipler sekmesi (takip edilen hesaplar, şirket kataloğu).', 'D-106', '1.5'],
            self::SocialAnalytics => ['Sosyal medya: Analiz sekmesi', 'Analiz sekmesi ve platform istatistikleri girişi.', 'D-106', '5.0'],
            self::SocialBlog => ['Sosyal medya: Blog yazma', 'İçerik ekleme menüsündeki "Blog" türü. Kapalıyken yeni blog yazısı açılamaz; eski blog yazıları görünmeye devam eder.', 'D-106', '5.0'],
            self::SocialExecutiveProfiles => ['Sosyal medya: Yönetici hesabı (Hüseyin Güneş)', 'Yönetici hesapları (şu an Hüseyin Güneş Resmi Hesap): hesap seçicide görünmez, bu hesaba içerik açılamaz.', 'D-106', '5.0'],

            self::Chat => ['Kurum içi sohbet', 'Sağ alttaki sohbet düğmesi ve penceresi. Alt özellikler de kapanır.', 'D-83', '1.0'],
            self::ChatGroups => ['Sohbet: Grup açma', 'Sohbette "Yeni grup" düğmesi ve grup oluşturma. Var olan gruplar kullanılmaya devam eder.', 'D-83', '5.0'],
            self::ChatWorkRequests => ['Sohbet: Mesajdan talep açma', 'Sohbet mesajının yanındaki "Talep aç" düğmesi (mesajı talebe dönüştürür).', 'D-84', '5.0'],

            self::Notifications => ['Bildirimler', 'Filament bildirim zili ve bildirim gönderimi. Alt özellikler de kapanır.', 'D-49', '1.0'],
            self::NotificationsInbox => ['Tüm bildirimler tablosu', 'Zilin yanındaki "Tüm bildirimleri gör" düğmesi ve tablosu.', 'D-122', '5.0'],
            self::Announcements => ['Duyurular', 'Kullanıcı menüsündeki "Duyuru gönder" ve genel bakıştaki duyurular.', 'D-82 / D-148', '1.0'],
            self::BusinessAlerts => ['İş uyarıları', 'Genel bakıştaki uyarılar kutusu ve uyarıyı bildirimden görüldü yapma.', 'D-82', '1.0'],
            self::DesktopAlerts => ['Masaüstü uyarıları', 'Yeni bildirim ve sohbet mesajı yoklaması. Alt özellikler (ses, Windows bildirimi) de kapanır.', 'D-126', '5.0'],
            self::AlertSound => ['Bildirim sesi', 'Yeni bildirim ya da sohbet mesajı gelince çalan kısa ses.', 'D-126', '5.0'],
            self::AlertWindows => ['Windows bildirimi', 'Başka sekmedeyken ya da pencere arkadayken çıkan Windows bildirimi ve kullanıcı menüsündeki "Masaüstü bildirimlerini aç".', 'D-126', '5.0'],

            self::Tools => ['Genel araçlar', 'Hızlı işlemler, Excel / PDF dışa aktarım, sürüm notları, UI Deneme. Alt özellikler de kapanır.', null, '1.0'],
            self::QuickActions => ['Hızlı işlemler', 'Üst çubuktaki Hızlı işlemler düğmesi ve kişisel seçim ekranı.', 'D-122', '5.0'],
            self::Exports => ['Dışa aktarım', 'Excel ve PDF indirme. Alt özellikler de kapanır.', 'D-110', '5.0'],
            self::ExcelExport => ['Excel indirme', 'Listelerdeki Excel düğmesi ve detay sayfalarındaki "Excel" seçeneği.', 'D-110', '5.0'],
            self::PdfExport => ['PDF indirme', 'Detay sayfalarındaki "PDF" seçeneği.', 'D-110', '5.0'],
            self::ReleaseNotes => ['Sürüm notları', 'Kullanıcı menüsündeki sürüm notları penceresi. Notlar kodda yazılmaya devam eder; kapalıyken arayüzde görünmez.', 'D-91', '5.0'],
            self::UiGallery => ['UI Deneme', 'UI Deneme kataloğu (yalnız tam yetkili kişiler).', 'D-77', '5.0'],
            self::UiDashboards => ['UI Deneme: Departman panoları', 'UI Deneme > Departman panoları: departman seçicili genel pano yerleşimi (Genel / Teklif - İş Geliştirme / Yönetici), gerçek verilerle. Durum düğmeleri kayıt değiştirmez.', 'D-173', '5.0'],
            self::UiDashboardExports => ['UI Deneme: Departman panoları dışa aktarım', 'Departman panolarındaki tabloların Excel ve PDF indirme düğmeleri. Alt özellikler de kapanır.', 'D-173', '5.0'],
            self::UiDashboardExcel => ['UI Deneme: Departman panoları Excel', 'Görünen tablonun (seçili sütunlar ve süzgeçlerle) Excel çıktısı.', 'D-173', '5.0'],
            self::UiDashboardPdf => ['UI Deneme: Departman panoları PDF', 'Görünen tablonun (seçili sütunlar ve süzgeçlerle) PDF çıktısı.', 'D-173', '5.0'],
            self::UiDashboardComponents => ['UI Deneme: Pano bileşen kataloğu', 'UI Deneme > Pano bileşenleri: panolara konabilecek numaralı bileşen seçenekleri (gösterge bandı, birleşik grafikler, yoğun tablolar, huni, ısı haritası...).', 'D-173', '5.0'],
            self::UiCostItems => ['UI Deneme: Maliyet kalemleri', 'UI Deneme > Maliyet kalemleri: Excel maliyet listesinin Ürün/Hizmet, İdari Kadro ve Genel Giderler sekmeleri, kalem başına departman onayları, katalog eşleştirmesi ve İcmal için numaralı tasarım seçenekleri. Deneme; kayıt değiştirmez.', 'D-187', '5.0'],
        };
    }
}
