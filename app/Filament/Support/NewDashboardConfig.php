<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Projects\ProjectNames;
use App\Query\Acquisition\ProposalQueries;
use App\Models\Project\Project;
use App\Enums\Acquisition\ProjectScopeType;
use App\Enums\Report\WorkItemPriority;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Filament\Resources\PurchaseQuotations\PurchaseQuotationResource;
use App\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Models\Acquisition\BusinessCase;
use App\Models\Acquisition\Proposal;
use App\Models\Acquisition\TenderDeadline;
use App\Models\Acquisition\TenderNotice;
use App\Models\Approval\ApprovalRequest;
use App\Models\Document\Document;
use App\Models\Notification\Announcement;
use App\Models\Party\MeetingPlan;
use App\Models\Personnel\OrgUnit;
use App\Models\Personnel\Personnel;
use App\Models\Procurement\PurchaseNeed;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Report\WorkItem;
use App\Query\Approval\ApprovalQueries;
use App\Query\Notification\WarningTargetQueries;
use App\Query\Personnel\OrganizationQueries;
use App\Query\Ui\NewDashboardQueries;
use App\Services\Notification\UrgentWarningService;
use App\Support\Dashboard\DepartmentDashboards;
use App\Support\Dashboard\Departments;
use App\Support\Dashboard\UrgencyRule;
use App\Support\DisplayTime;
use App\Support\Money;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Yeni pano React uygulamasinin verisi (D-207; kullanici 14 Ekim 2026:
 * "react ile yap. Daha cok alan yonetimi daha fazla bilgi ama yine ayni
 * boyutlara sigacak ... Acil koduyla ustte duyuru paneli uyarisi olur ...
 * acil yapilacak islem bildirilir, belirli periyotlarla ekranda yeniden
 * cikar"). Gercek veriden okunur; baglantilar, metinler ve acil uyari
 * seviyeleri burada hazirlanir, React yalniz cizer.
 *
 * D-208 (14 Ekim 2026): her panonun sablon alani (departmanlarin SAB turundeki
 * belgeleri, tek tikla indir / yeni sekmede ac), yonetici islemleri icin hedefler
 * (onay talebi, acil uyarisi gidecek kayit, seridin departmani) ve kisinin
 * bunlari yapip yapamayacagi. Islemleri sayfanin Filament eylemleri yapar.
 */
final class NewDashboardConfig
{
    /** Seritlerin departmani (organizasyon birimi kodu). */
    private const LANE_DEPARTMENTS = [
        'executive' => [
            // D-217: Is gelistirme'den once Ihaleler seridi.
            'tenders' => Departments::BUSINESS_DEVELOPMENT,
            'cases' => Departments::BUSINESS_DEVELOPMENT,
            'offers' => Departments::PROPOSALS,
            'approvals' => Departments::PROJECTS,
            'purchasing' => Departments::PURCHASING,
        ],
        'purchasing' => [
            'entry' => Departments::PURCHASING,
            'projects' => Departments::PURCHASING,
            'orders' => Departments::PURCHASING,
            'production' => Departments::PURCHASING,
        ],
    ];

    /** Seridin "Listeyi ac" baglantisi. */
    private const LANE_LISTS = [
        'tenders' => TenderNoticeResource::class,
        'investors' => BusinessCaseResource::class,
        'potentials' => BusinessCaseResource::class,
        'to_submit' => ProposalResource::class,
        'awaiting' => ProposalResource::class,
        'concluded' => ProposalResource::class,
        'cases' => BusinessCaseResource::class,
        'offers' => ProposalResource::class,
        // D-217: Proje - Operasyon seridi projelere gore gruplu; liste Projeler.
        'approvals' => ProjectResource::class,
        'purchasing' => PurchaseRequestResource::class,
        'entry' => PurchaseRequestResource::class,
        'projects' => ProjectResource::class,
        'orders' => PurchaseOrderResource::class,
        'production' => PurchaseOrderResource::class,
    ];

    /**
     * Seritlerin yumusak renk tonu. Yonetici panelinde her departman kendi
     * tonunda (D-217; kullanici 15 Ekim 2026: "her bir departman alanı farklı
     * bir soft bir renk tonunda olmalı ki kolayca seçilebilsin"). Departman
     * panolari ayni renk dilini kullanir (D-227, 19 Ekim 2026: "İş geliştirme
     * paneli'nde de Yönetici bekleyenlerdeki alanların renk tonları gibi renk
     * tonları verilmeli. Ortak renk benimsenmeli"): ihale camgobegi, is
     * gelistirme mavi, teklif mor, proje yesil, satin alma kehribar.
     */
    private const LANE_TONES = [
        'executive' => [
            'tenders' => 'teal',
            'cases' => 'blue',
            'offers' => 'violet',
            'approvals' => 'green',
            'purchasing' => 'amber',
        ],
        'acquisition' => [
            'tenders' => 'teal',
            'investors' => 'blue',
            'potentials' => 'blue',
            'to_submit' => 'violet',
            'awaiting' => 'violet',
            'concluded' => 'violet',
        ],
        'purchasing' => [
            'entry' => 'amber',
            'projects' => 'green',
            'orders' => 'amber',
            'production' => 'amber',
        ],
    ];

    /**
     * Departman panolarinin sablon alanindaki departmanlar (departmansiz sablon
     * her panoda). Yonetici panelinde sablon alani yok (15 Ekim 2026 kullanici:
     * "yonetici panelinde sablonlara gerek yok").
     */
    private const PANEL_DEPARTMENTS = [
        'acquisition' => [Departments::BUSINESS_DEVELOPMENT, Departments::PROPOSALS],
        'purchasing' => [Departments::PURCHASING],
    ];

    /**
     * "Yeni kayit" menusu panele gore (15 Ekim 2026 kullanici: "hangi panele
     * gecersem o panele uygun veri girisi alani olmali. Yonetici kisminda hepsi
     * olur."). Kayit turu => panolar; yonetici panelinde hepsi.
     */
    private const ENTRY_PANELS = [
        'tender' => ['acquisition'],
        'case' => ['acquisition'],
        'proposal' => ['acquisition'],
        'meeting' => ['acquisition'],
        'document' => ['acquisition', 'purchasing'],
        'purchase_request' => ['purchasing'],
        'purchase_quotation' => ['purchasing'],
    ];

    /**
     * Bekleme yasi dilimleri (gun) panele gore: tekliflerin bekleyisi aylarla,
     * yonetici ve satin alma bekleyenleri gunlerle olculur.
     */
    private const PANEL_AGE_BUCKETS = [
        DepartmentDashboards::ACQUISITION => [[0, 30], [31, 90], [91, 180], [181, null]],
        DepartmentDashboards::EXECUTIVE => [[0, 7], [8, 30], [31, 90], [91, null]],
        DepartmentDashboards::PURCHASING => [[0, 3], [4, 7], [8, 30], [31, null]],
    ];

    /** Acil uyarinin ve Dun / Bugun / Yarin kaydinin hangi panelde gorundugu (yonetici hepsini gorur). */
    private const KIND_PANELS = [
        'purchase_request' => DepartmentDashboards::PURCHASING,
        'purchase_need' => DepartmentDashboards::PURCHASING,
        'order_approval' => DepartmentDashboards::PURCHASING,
        'delivery' => DepartmentDashboards::PURCHASING,
        'tender_deadline' => DepartmentDashboards::ACQUISITION,
        'proposal_submitted' => DepartmentDashboards::ACQUISITION,
        'proposal_created' => DepartmentDashboards::ACQUISITION,
        'meeting' => DepartmentDashboards::ACQUISITION,
        'case_created' => DepartmentDashboards::ACQUISITION,
        'work_item' => DepartmentDashboards::EXECUTIVE,
    ];

    private ?Personnel $viewer = null;

    /** @var list<string> */
    private array $panels = DepartmentDashboards::ALL;

    /** @var array<int, list<array{value: string, label: string, color: string}>> */
    private array $projectTypeCache = [];

    public function __construct(
        private readonly NewDashboardQueries $queries,
        private readonly ApprovalQueries $approvals,
        private readonly WarningTargetQueries $targets,
        private readonly OrganizationQueries $organization,
        private readonly UrgentWarningService $warnings,
    ) {}

    /**
     * Panonun verisi. Yalniz kisinin panellerinin verisi uretilir (D-212):
     * departman calisaninin sayfasina yonetici seritleri ya da baska
     * departmanin kayitlari yazilmaz. Kisinin goremedigi kayit listelere girmez.
     *
     * @param  list<string>  $panels  DepartmentDashboards sabitleri, sekme sirasiyla
     * @return array<string, mixed>
     */
    public function make(array $panels = DepartmentDashboards::ALL, ?string $title = null, bool $withDocuments = true): array
    {
        $this->viewer = auth()->user() instanceof Personnel ? auth()->user() : null;
        $this->panels = array_values(array_intersect(DepartmentDashboards::ALL, $panels));
        $acquisition = $this->has(DepartmentDashboards::ACQUISITION);
        $executive = $this->has(DepartmentDashboards::EXECUTIVE);
        $toSubmit = $acquisition || $executive
            ? $this->visible($this->queries->proposalsToSubmit())->map(fn (Proposal $p): array => $this->proposal($p, 'created'))->values()
            : collect();

        return [
            'labels' => (array) __('new_dashboard.app'),
            'title' => $title ?? (string) __('new_dashboard.app.title'),
            'panels' => $this->panels,
            'today' => DisplayTime::today()->translatedFormat('j F Y, l'),
            'entry' => $this->entry(),
            'can' => [
                'warn' => $this->warnings->canWarn($this->viewer),
                'departments' => $this->warnings->canWarn($this->viewer, true),
            ],
            'announcements' => $this->queries->urgentAnnouncements()->map(fn (Announcement $a): array => [
                'id' => (int) $a->getKey(),
                'title' => (string) $a->title,
                'body' => Str::limit(Str::squish(strip_tags((string) $a->body)), 220),
                'meta' => trim(($a->sender?->full_name ?? '').' · '.DisplayTime::format($a->sent_at, 'd.m.Y H:i'), ' ·'),
                'days' => $a->sent_at instanceof Carbon ? (int) $a->sent_at->diffInDays(Carbon::now()) : null,
                'url' => filled($a->action_url) ? (string) $a->action_url : null,
            ])->values()->all(),
            'urgent' => $this->urgent(),
            // D-226: sablonlar ust cubuktaysa pano alani bos gelir (React alani cizmez).
            'documents' => $withDocuments ? $this->documents() : ['items' => [], 'panels' => [], 'createUrl' => null],
            'days' => $this->days(),
            'meetings' => $acquisition ? $this->meetings() : null,
            'aging' => array_map(fn (array $buckets): array => $this->buckets($buckets), array_intersect_key(self::PANEL_AGE_BUCKETS, array_flip($this->panels))),
            'acquisition' => $acquisition ? $this->acquisition($toSubmit) : null,
            'executive' => $executive ? ['lanes' => $this->executiveLanes($toSubmit)] : null,
            'purchasing' => $this->has(DepartmentDashboards::PURCHASING) ? $this->purchasing() : null,
        ];
    }

    /**
     * Departman uyarisinin konusu: seridin adi, kayit sayisi, en eski bekleme,
     * liste baglantisi ve departmani. Sunucu yeniden hesaplar (D-208).
     *
     * @return array{label: string, count: int, oldest: ?int, url: ?string, unit: ?OrgUnit}|null
     */
    public function laneFacts(string $tab, string $key): ?array
    {
        $this->viewer = auth()->user() instanceof Personnel ? auth()->user() : null;

        $lanes = match ($tab) {
            'executive' => $this->executiveLanes($this->visible($this->queries->proposalsToSubmit())->map(fn (Proposal $p): array => $this->proposal($p, 'created'))->values()),
            'purchasing' => $this->purchasing()['lanes'],
            default => [],
        };

        foreach ($lanes as $lane) {
            if ($lane['key'] === $key) {
                $code = self::LANE_DEPARTMENTS[$tab][$key] ?? null;

                return [
                    'label' => (string) $lane['label'],
                    'count' => (int) $lane['count'],
                    'oldest' => isset($lane['oldest']) && $lane['oldest'] !== null ? (int) $lane['oldest'] : null,
                    'url' => $lane['listUrl'] ?? null,
                    'unit' => $code !== null ? $this->organization->unitByCode($code) : null,
                ];
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entry(): array
    {
        $targets = [
            ['tender', TenderNoticeResource::class, 'megaphone'],
            ['case', BusinessCaseResource::class, 'briefcase'],
            ['proposal', ProposalResource::class, 'clipboard'],
            ['meeting', MeetingPlanResource::class, 'calendar'],
            ['document', DocumentResource::class, 'document'],
            ['purchase_request', PurchaseRequestResource::class, 'cart'],
            ['purchase_quotation', PurchaseQuotationResource::class, 'receipt'],
        ];
        $entry = [];

        foreach ($targets as [$key, $resource, $icon]) {
            $panels = [...(self::ENTRY_PANELS[$key] ?? []), DepartmentDashboards::EXECUTIVE];

            if (array_intersect($panels, $this->panels) !== [] && $this->canCreate($resource)) {
                $entry[] = [
                    'key' => $key,
                    'label' => (string) __('new_dashboard.entry.'.$key),
                    'icon' => $icon,
                    'url' => $resource::getUrl('create'),
                    'panels' => $panels,
                ];
            }
        }

        return $entry;
    }

    /**
     * Sablon alani (D-208, kullanici 14 Ekim 2026: "Tum departmanlar dashboardda
     * indirebilecegi standart sablonlarina erisebilmelidir. Tek tusla
     * indirebilmeli veya yan sekmede acabilmelidir."). Departman panolarinda
     * (yonetici panelinde yok, D-210): SAB belgeleri sahip birimine gore
     * (departmansiz = her departman panosu); Is gelistirme panelinde ayrica
     * standart belgeler (D-212: "Standart formlar alanini ... sablonlar alanina
     * koyduk"). Her belgede gorundugu paneller (`panels`); kisinin goremedigi
     * belge yok.
     *
     * @return array<string, mixed>
     */
    private function documents(): array
    {
        $departmentPanels = array_values(array_intersect(array_keys(self::PANEL_DEPARTMENTS), $this->panels));
        $panels = [];

        foreach ($departmentPanels as $panel) {
            $units = array_values(array_filter(array_map(fn (string $code): ?OrgUnit => $this->organization->unitByCode($code), self::PANEL_DEPARTMENTS[$panel])));
            $panels[$panel] = [
                'unitIds' => array_map(fn (OrgUnit $unit): int => (int) $unit->getKey(), $units),
                'units' => array_map(fn (OrgUnit $unit): string => (string) $unit->name, $units),
            ];
        }

        $items = [];

        if ($departmentPanels === []) {
            return ['items' => [], 'panels' => [], 'createUrl' => null];
        }

        foreach ($this->queries->templates() as $document) {
            /** @var Document $document */
            $unitId = $document->owner_org_unit_id !== null ? (int) $document->owner_org_unit_id : null;
            $shownIn = array_values(array_filter($departmentPanels, static fn (string $panel): bool => $unitId === null || in_array($unitId, $panels[$panel]['unitIds'], true)));
            $item = $shownIn !== [] ? $this->documentItem($document, $shownIn, $document->ownerOrgUnit?->name) : null;

            if ($item !== null) {
                $items[] = $item;
            }
        }

        if (in_array(DepartmentDashboards::ACQUISITION, $departmentPanels, true)) {
            foreach ($this->queries->standardDocuments() as $document) {
                /** @var Document $document */
                $item = $this->documentItem($document, [DepartmentDashboards::ACQUISITION], $document->documentType?->name);

                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return [
            'items' => $items,
            'panels' => $panels,
            'createUrl' => $this->canCreate(DocumentResource::class) ? DocumentResource::getUrl('create') : null,
        ];
    }

    /**
     * Sablon alaninin bir satiri; dosyasi yoksa ya da kisi belgeyi goremiyorsa null.
     *
     * @param  list<string>  $panels
     * @return array<string, mixed>|null
     */
    private function documentItem(Document $document, array $panels, ?string $group): ?array
    {
        if (! Gate::allows('view', $document)) {
            return null;
        }

        $revision = $document->displayRevision();
        $download = $revision !== null ? FileLinks::revisionOriginal($revision) : null;

        if ($revision === null || $download === null) {
            return null;
        }

        $file = $revision->originalFile();

        return [
            'id' => (int) $document->getKey(),
            'title' => (string) $document->title,
            'code' => $document->document_no,
            'unit' => $group,
            'panels' => $panels,
            'ext' => $file !== null ? Str::upper((string) ($file->extension ?: pathinfo((string) $file->original_name, PATHINFO_EXTENSION))) : null,
            'size' => $file !== null && $file->byte_size !== null ? $this->bytes((int) $file->byte_size) : null,
            'updated' => $document->updated_at instanceof Carbon ? DisplayTime::format($document->updated_at, 'd.m.Y') : null,
            'download' => $download,
            'open' => FileLinks::bestPreview($revision),
            'url' => RecordLinks::detailUrl($document, checkRecord: false),
        ];
    }

    /**
     * Acil uyarilar: seviye, yeniden gosterme araligi ve neden (UrgencyRule).
     *
     * @return list<array<string, mixed>>
     */
    private function urgent(): array
    {
        $candidates = $this->queries->urgentCandidates();
        $items = [];

        foreach ($candidates['requests'] as $request) {
            /** @var PurchaseRequest $request */
            $items[] = $this->urgentItem('purchase_request', 'pr-'.$request->getKey(), (string) $request->getAttribute('request_no'),
                $request->getAttribute('needed_by'), (bool) $request->getAttribute('is_urgent'), $request, $request->requestedBy?->full_name, 'purchase_request');
        }

        foreach ($candidates['needs'] as $need) {
            /** @var PurchaseNeed $need */
            $items[] = $this->urgentItem('purchase_need', 'pn-'.$need->getKey(), (string) $need->getAttribute('title'),
                $need->getAttribute('needed_by'), (bool) $need->getAttribute('is_urgent'), $need, $need->purchaseRequest?->getAttribute('request_no'), 'purchase_need');
        }

        foreach ($candidates['work'] as $work) {
            /** @var WorkItem $work */
            $items[] = $this->urgentItem('work_item', 'wi-'.$work->getKey(), (string) $work->title,
                $work->due_on, (bool) $work->is_critical, $work, $work->personnel?->full_name, 'work_item');
        }

        foreach ($candidates['deadlines'] as $deadline) {
            /** @var TenderDeadline $deadline */
            $notice = $deadline->version?->notice;
            $items[] = $this->urgentItem('tender_deadline', 'td-'.$deadline->getKey(), (string) ($notice?->title ?? '-'),
                $deadline->local_due_date, false, $notice, $deadline->deadline_type?->getLabel(), 'tender_notice');
        }

        foreach ($candidates['approvals'] as $order) {
            /** @var PurchaseOrder $order */
            $items[] = $this->urgentItem('order_approval', 'oa-'.$order->getKey(), $order->supplierLabel(),
                null, true, $order, (string) $order->getAttribute('order_no'), 'purchase_order');
        }

        foreach ($candidates['deliveries'] as $order) {
            /** @var PurchaseOrder $order */
            $items[] = $this->urgentItem('delivery', 'dl-'.$order->getKey(), $order->supplierLabel(),
                $order->getAttribute('expected_delivery_on'), false, $order, (string) $order->getAttribute('order_no'), 'purchase_order');
        }

        $items = array_values(array_filter($items, fn (?array $item): bool => $item !== null && $this->inPanels($item['kind'])));
        usort($items, fn (array $a, array $b): int => [UrgencyRule::ORDER[$a['level']], $a['days'] ?? 999] <=> [UrgencyRule::ORDER[$b['level']], $b['days'] ?? 999]);

        return array_slice($items, 0, 20);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function urgentItem(string $kind, string $id, string $title, mixed $due, bool $flagged, ?Model $record, ?string $meta, string $targetType): ?array
    {
        $days = $due instanceof Carbon ? (int) DisplayTime::today()->diffInDays($due->copy()->startOfDay(), false) : null;
        $level = UrgencyRule::level($days, $flagged);

        if ($level === null || ! $this->inPanels($kind) || ($record instanceof Model && ! $this->canView($record))) {
            return null;
        }

        $approve = $record instanceof PurchaseOrder ? $this->approveFor($record) : null;

        return [
            'id' => $id,
            'kind' => $kind,
            'kindLabel' => (string) __('new_dashboard.urgent_kinds.'.$kind),
            'title' => $title,
            'meta' => $meta,
            'due' => $due instanceof Carbon ? $due->format('d.m.Y') : null,
            'days' => $days,
            'flagged' => $flagged,
            'level' => $level,
            'interval' => UrgencyRule::interval($level),
            'url' => $record instanceof Model ? RecordLinks::detailUrl($record, checkRecord: false) : null,
            'target' => $record instanceof Model ? $this->target($targetType, $record) : null,
            'approve' => $approve,
            // D-222: isin / kalemin projesinin tipleri (biliniyorsa).
            'types' => $record instanceof Model && ! $record instanceof TenderNotice ? $this->projectTypes($record->getRelationValue('project')) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposal(Proposal $proposal, string $mode): array
    {
        $case = $proposal->businessCase;
        $version = $proposal->currentVersion;
        $since = match ($mode) {
            'submitted' => $version?->submitted_at ?? $proposal->created_at,
            'concluded' => $proposal->updated_at,
            default => $proposal->created_at,
        };

        return [
            'id' => 'p-'.$proposal->getKey(),
            'title' => (string) ($proposal->title ?? $proposal->proposal_no),
            // 19 Ekim 2026 kullanici: "verilen tekliflerin siralandigi dashboarddaki kartlarda potansiyel
            // is'in kodu yazilmasin" - yalniz teklif kodu.
            'codes' => array_values(array_filter([$proposal->proposal_no])),
            'party' => $case?->primaryParty?->display_name,
            'owner' => $proposal->owner?->full_name,
            'grade' => NewDashboardQueries::grade($case?->heat_score),
            'heat' => $case?->heat_score !== null ? (int) $case->heat_score : null,
            'when' => $since instanceof Carbon ? DisplayTime::format($since, 'd.m.Y') : null,
            'days' => $since instanceof Carbon ? (int) $since->diffInDays(Carbon::now()) : null,
            'amount' => $version?->total_price !== null ? Money::format($version->total_price, $version->currency_code) : null,
            'amountValue' => $version?->total_price !== null ? (float) $version->total_price : null,
            'status' => $mode === 'concluded' ? ($proposal->currentOfferStatus()?->value === 'approved' ? 'won' : 'lost') : ($proposal->is_draft ? 'draft' : null),
            'kind' => (string) __('new_dashboard.app.card.kinds.proposal'),
            'types' => self::typeBadges(app(ProposalQueries::class)->scopeTypes($proposal)),
            'url' => RecordLinks::detailUrl($proposal, checkRecord: false),
            'target' => $mode === 'concluded' ? null : $this->target('proposal', $proposal),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tender(TenderNotice $notice): array
    {
        $deadline = NewDashboardQueries::nextDeadline($notice);
        $due = $deadline?->local_due_date;

        return [
            'id' => 't-'.$notice->getKey(),
            'title' => (string) $notice->title,
            'codes' => array_values(array_filter([$notice->external_notice_id])),
            'party' => $notice->issuerParty?->display_name,
            'when' => $due instanceof Carbon ? $due->format('d.m.Y') : null,
            'days' => $due instanceof Carbon ? (int) DisplayTime::today()->diffInDays($due, false) : null,
            'status' => $deadline?->deadline_type?->getLabel(),
            'kind' => (string) __('new_dashboard.app.card.kinds.tender_notice'),
            'url' => RecordLinks::detailUrl($notice, checkRecord: false),
            'target' => $this->target('tender_notice', $notice),
            // D-217: aciliyet rengi (son tarihe kalan gun).
            'level' => UrgencyRule::level($due instanceof Carbon ? (int) DisplayTime::today()->diffInDays($due, false) : null, false),
        ];
    }

    /**
     * @param  array{kind: string, at: mixed, model: mixed}  $item
     * @return array<string, mixed>
     */
    private function day(array $item): array
    {
        $model = $item['model'];
        $at = $item['at'] instanceof Carbon ? $item['at'] : null;

        [$title, $url, $code] = match ($item['kind']) {
            'proposal_submitted', 'proposal_created' => [(string) $model->title, RecordLinks::detailUrl($model, checkRecord: false), $model->proposal_no],
            'tender_deadline' => [(string) ($model->version?->notice?->title ?? '-'), $model->version?->notice instanceof Model ? RecordLinks::detailUrl($model->version->notice, checkRecord: false) : null, null],
            'meeting' => [(string) ($model->party?->display_name ?? $model->subject ?? '-'), RecordLinks::detailUrl($model, checkRecord: false), null],
            'case_created' => [(string) $model->title, RecordLinks::detailUrl($model, checkRecord: false), $model->caseCode()?->formatted_code],
            'delivery' => [$model->supplierLabel(), RecordLinks::detailUrl($model, checkRecord: false), $model->getAttribute('order_no')],
            default => ['-', null, null],
        };

        return [
            'kind' => $item['kind'],
            'kindLabel' => (string) __('new_dashboard.day_kinds.'.$item['kind']),
            'title' => $title,
            'code' => $code,
            'time' => $at !== null && $at->format('H:i:s') !== '00:00:00' ? DisplayTime::format($at, 'H:i') : null,
            'url' => $url,
        ];
    }

    /**
     * Bekleme yasi dilimleri (sayimi React yapar: panelin "aging" seritlerindeki
     * kayitlarin yasi; dilime tiklamak o seritleri suzer).
     *
     * @param  list<array{0: int, 1: ?int}>  $definitions
     * @return list<array{key: string, label: string, min: int, max: ?int}>
     */
    private function buckets(array $definitions): array
    {
        return array_map(static fn (array $range): array => [
            'key' => $range[0].'-'.($range[1] ?? 'up'),
            'label' => $range[1] === null ? (string) __('new_dashboard.app.age_over', ['days' => $range[0] - 1]) : (string) __('new_dashboard.app.age_range', ['min' => $range[0], 'max' => $range[1]]),
            'min' => $range[0],
            'max' => $range[1],
        ], $definitions);
    }

    /**
     * Is gelistirme paneli (D-212, kullanici: "Is gelistirme paneli kismini Satin
     * alma paneli formatinda olustur. Ihaleler, Yatirimci Projeleri, Potansiyel
     * Isler, Verilecek Teklifler, Verilen sonuc bekleyen teklifler, Sonuclanan
     * ... Derece diye bir kolon yok. Sicaklik var."): sayi seridi ve alti serit.
     *
     * @param  Collection<int, array<string, mixed>>  $toSubmit
     * @return array<string, mixed>
     */
    private function acquisition(Collection $toSubmit): array
    {
        $stats = $this->queries->acquisitionStats();
        $awaiting = $this->visible($this->queries->proposalsAwaitingResult())->map(fn (Proposal $p): array => $this->proposal($p, 'submitted'))->values();
        $concluded = $this->visible($this->queries->proposalsConcluded())->map(fn (Proposal $p): array => $this->proposal($p, 'concluded'))->values();

        return [
            'kpis' => [
                'to_submit' => $stats['to_submit'],
                'awaiting' => $stats['awaiting'],
                'oldest' => $stats['oldest_waiting_days'],
                'won' => $stats['won'],
                'lost' => $stats['lost'],
                'win_rate' => ($stats['won'] + $stats['lost']) > 0 ? (int) round($stats['won'] * 100 / ($stats['won'] + $stats['lost'])) : null,
                'pipeline' => $this->totals($this->queries->proposalsAwaitingResult(), fn (Proposal $p): array => [$p->currentVersion?->total_price, $p->currentVersion?->currency_code]),
            ],
            'lanes' => [
                $this->lane('acquisition', 'tenders', $this->visible($this->queries->openTenders())->map(fn (TenderNotice $t): array => $this->tender($t)), 'left'),
                $this->lane('acquisition', 'investors', $this->visible($this->queries->casesInDevelopment(false))->map(fn (BusinessCase $c): array => $this->case($c)), 'keep', false),
                $this->lane('acquisition', 'potentials', $this->visible($this->queries->casesInDevelopment(true))->map(fn (BusinessCase $c): array => $this->case($c)), 'keep', false),
                $this->lane('acquisition', 'to_submit', $toSubmit, 'keep', false),
                $this->lane('acquisition', 'awaiting', $awaiting),
                $this->lane('acquisition', 'concluded', $concluded, 'keep', false),
            ],
        ];
    }

    /**
     * Gorusulecekler (D-212): bugunden ay sonuna planli gorusmeler; React
     * Bugun / Bu hafta / Bu ay diye suzer.
     *
     * @return array<string, mixed>
     */
    private function meetings(): array
    {
        $today = DisplayTime::today();

        return [
            'today' => $today->format('Y-m-d'),
            'weekEnd' => $today->copy()->endOfWeek()->format('Y-m-d'),
            'monthEnd' => $today->copy()->endOfMonth()->format('Y-m-d'),
            'items' => $this->visible($this->queries->meetingsAhead())->map(fn (MeetingPlan $plan): array => [
                'id' => (int) $plan->getKey(),
                'title' => (string) ($plan->party?->display_name ?? $plan->subject ?? '-'),
                'subject' => $plan->party !== null ? $plan->subject : null,
                'owner' => $plan->personnel?->full_name,
                'on' => $plan->planned_on instanceof Carbon ? $plan->planned_on->format('Y-m-d') : null,
                'when' => $plan->planned_on instanceof Carbon ? $plan->planned_on->translatedFormat('d.m · D') : null,
                'url' => RecordLinks::detailUrl($plan, checkRecord: false),
            ])->values()->all(),
        ];
    }

    /**
     * Dun / Bugun / Yarin: kisinin panellerine giren kayitlar.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function days(): array
    {
        return collect($this->queries->days())
            ->map(fn (array $items): array => array_values(array_map($this->day(...), array_filter($items, fn (array $item): bool => $this->inPanels($item['kind'])
                && (! $item['model'] instanceof Model || $this->canView($item['model']))))))
            ->all();
    }

    /** Kisinin panellerinden biri bu turu gosteriyor mu (yonetici hepsini). */
    private function inPanels(string $kind): bool
    {
        return $this->has(DepartmentDashboards::EXECUTIVE) || $this->has(self::KIND_PANELS[$kind] ?? DepartmentDashboards::EXECUTIVE);
    }

    private function has(string $panel): bool
    {
        return in_array($panel, $this->panels, true);
    }

    /**
     * Kisinin gorebildigi kayitlar (gorunum politikasi; D-212: pano departman
     * calisanlarina da acildi).
     *
     * @template T of Model
     *
     * @param  Collection<int, T>  $models
     * @return Collection<int, T>
     */
    private function visible(Collection $models): Collection
    {
        return $models->filter(fn (Model $model): bool => $this->canView($model))->values();
    }

    private function canView(Model $model): bool
    {
        return $this->viewer instanceof Personnel && $this->viewer->can('view', $model);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $toSubmit
     * @return list<array<string, mixed>>
     */
    private function executiveLanes(Collection $toSubmit): array
    {
        // D-217 (15 Ekim 2026 kullanici: "Yönetici dashboardunda İş Geliştirme'den
        // önce bir kolon olmalı ve o alanda İhaleler listelenmelidir").
        $lanes = [
            $this->lane('executive', 'tenders', $this->visible($this->queries->openTenders())->map(fn (TenderNotice $n): array => $this->tender($n)), 'left'),
            $this->lane('executive', 'cases', $this->visible($this->queries->casesInOfferPreparation())->map(fn (BusinessCase $c): array => $this->case($c))),
            $this->lane('executive', 'offers', $toSubmit),
            $this->projectLane(),
        ];

        // D-215 / D-229: satin alma akisi (Satin alinacaklar, 2.9) kapaliyken Satin alma seridi yok.
        if (app(DepartmentDashboards::class)->purchasingOpen()) {
            $lanes[] = $this->purchasingLane();
        }

        return $lanes;
    }

    /**
     * Proje - Operasyon seridi (D-217; kullanici 15 Ekim 2026: "Projeler toogle
     * olarak açılıp kapatılabilir olmalı ve içinde de aynı arayüzde işler gelir.
     * Yani aslında aynı tasarım sadece bir katman daha eklenip, x projesi bilgisi
     * eklenip kategorize edilmiş olacak"): projenin bekleyen onaylari ve acik
     * isleri, projeye gore gruplu; projesiz onaylar ayri grupta.
     *
     * @return array<string, mixed>
     */
    private function projectLane(): array
    {
        $approvals = $this->visible($this->queries->openApprovals());
        $projects = $this->queries->approvalProjects($approvals);
        $groups = [];

        // Gruplar kayit eslenirken birikir (kapanis $groups'u referansla tasir).
        $items = $approvals->map(function (ApprovalRequest $a) use (&$groups, $projects): array {
            return [...$this->approval($a), 'group' => $this->group($groups, $projects[(int) $a->getKey()] ?? null, 'none')];
        })->concat($this->visible($this->queries->openProjectWorkItems())->map(function (WorkItem $w) use (&$groups): array {
            return [...$this->work($w), 'group' => $this->group($groups, $w->project, 'none')];
        }));

        return $this->groupedLane('approvals', $items, $groups);
    }

    /**
     * Yonetici panelinin Satin alma seridi (D-217: "Satın almada da aynı şekilde
     * kategorize edilir"): talepler ve onay bekleyen siparisler projeye gore,
     * projesizler "Şirket içi" grubunda.
     *
     * @return array<string, mixed>
     */
    private function purchasingLane(): array
    {
        $groups = [];
        $items = $this->visible($this->queries->openPurchaseRequests())->map(function (PurchaseRequest $r) use (&$groups): array {
            return [...$this->request($r), 'group' => $this->group($groups, $r->project, 'internal')];
        })->concat($this->visible($this->queries->pendingOrders())->map(function (PurchaseOrder $o) use (&$groups): array {
            return [...$this->order($o, false), 'group' => $this->group($groups, $o->project, 'internal')];
        }));

        return $this->groupedLane('purchasing', $items, $groups);
    }

    /**
     * Gruplu yonetici seridi: once aciliyet (kritik, yuksek, orta), sonra en
     * uzun bekleyen; gruplar icinde en acil kaydi olan once, projesiz en sonda.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  array<string, array{key: string, title: string, url: ?string, none: bool}>  $groups
     * @return array<string, mixed>
     */
    private function groupedLane(string $key, Collection $items, array $groups): array
    {
        $rank = fn (array $item): int => UrgencyRule::ORDER[$item['level'] ?? ''] ?? 3;
        $sorted = $items->sortBy([
            fn (array $a, array $b): int => $rank($a) <=> $rank($b),
            fn (array $a, array $b): int => ($b['days'] ?? -1) <=> ($a['days'] ?? -1),
        ])->values();

        $lane = $this->lane('executive', $key, $sorted, 'keep', true, 200);
        $lane['oldest'] = $items->filter(fn (array $item): bool => ($item['mode'] ?? 'age') === 'age')->max('days');
        $lane['groups'] = collect($groups)
            ->sortBy([
                fn (array $a, array $b): int => (int) $a['none'] <=> (int) $b['none'],
                fn (array $a, array $b): int => ($sorted->where('group', $a['key'])->map($rank)->min() ?? 3) <=> ($sorted->where('group', $b['key'])->map($rank)->min() ?? 3),
                fn (array $a, array $b): int => strcmp(mb_strtolower($a['title']), mb_strtolower($b['title'])),
            ])
            ->values()
            ->all();

        return $lane;
    }

    /**
     * Kaydin grubu: projesi, yoksa $none grubu ("Projesiz" / "Şirket içi").
     *
     * @param  array<string, array{key: string, title: string, url: ?string, none: bool}>  $groups
     */
    private function group(array &$groups, ?Model $project, string $none): string
    {
        $key = $project instanceof Model ? 'p-'.$project->getKey() : $none;

        $groups[$key] ??= [
            'key' => $key,
            'title' => $project instanceof Model ? (string) ($project->getAttribute('display_name') ?? $project->getAttribute('name')) : (string) __('new_dashboard.app.groups.'.$none),
            'url' => $project instanceof Model ? RecordLinks::detailUrl($project, checkRecord: false) : null,
            'none' => ! $project instanceof Model,
        ];

        return $key;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function lane(string $tab, string $key, Collection $items, string $sort = 'age', bool $aging = true, int $limit = 40): array
    {
        $sorted = match ($sort) {
            'age' => $items->sortByDesc('days'),
            'left' => $items->sortBy(fn (array $item): int => $item['days'] ?? PHP_INT_MAX),
            default => $items,
        };

        return [
            'key' => $key,
            'label' => (string) __('new_dashboard.app.lanes.'.$key),
            'count' => $items->count(),
            'oldest' => $sort === 'age' ? $items->max('days') : null,
            // "days" kaydin bekleme yasi mi (bekleme yasi suzgeci ve kartlari bu seritleri sayar)?
            'age' => $sort !== 'left',
            'aging' => $aging && $sort !== 'left',
            'items' => $sorted->values()->take($limit)->all(),
            'department' => $this->department(self::LANE_DEPARTMENTS[$tab][$key] ?? null),
            'listUrl' => $this->listUrl(self::LANE_LISTS[$key] ?? null),
            // D-217 / D-227: seridin renk tonu (her panelde ayni renk dili).
            'tone' => self::LANE_TONES[$tab][$key] ?? null,
        ];
    }

    /** Bekleyen kaydin aciliyeti (onay, siparis; D-217): 14+ gun kritik, 7+ yuksek, 3+ orta. */
    private static function ageLevel(?int $days): ?string
    {
        return match (true) {
            $days === null => null,
            $days >= 14 => UrgencyRule::CRITICAL,
            $days >= 7 => UrgencyRule::HIGH,
            $days >= 3 => UrgencyRule::MEDIUM,
            default => null,
        };
    }

    /** Bugunden tarihe kalan gun (gecmisse eksi); tarih yoksa null. */
    private static function daysLeft(mixed $date): ?int
    {
        return $date instanceof Carbon ? (int) DisplayTime::today()->diffInDays($date->copy()->startOfDay(), false) : null;
    }

    /**
     * Projenin acik isi (D-217): son tarihi varsa kalan gun, yoksa bekleme yasi.
     *
     * @return array<string, mixed>
     */
    private function work(WorkItem $item): array
    {
        $left = self::daysLeft($item->due_on);
        $critical = (bool) $item->is_critical;
        $priority = $item->priorityLevel();
        $level = UrgencyRule::level($left, $critical);
        // D-219: dort seviyeli kritiklik en az kendi tonunu verir (Kritik kirmizi, Yuksek turuncu).
        $floor = match ($priority) {
            WorkItemPriority::Critical => UrgencyRule::CRITICAL,
            WorkItemPriority::High => UrgencyRule::HIGH,
            default => null,
        };

        if ($floor !== null && ($level === null || UrgencyRule::ORDER[$floor] < UrgencyRule::ORDER[$level])) {
            $level = $floor;
        }

        return [
            'id' => 'w-'.$item->getKey(),
            'title' => (string) $item->title,
            'codes' => array_values(array_filter([(string) $item->getAttribute('item_no')])),
            'owner' => $item->personnel?->full_name,
            'days' => $left ?? ($item->work_at instanceof Carbon ? (int) $item->work_at->diffInDays(Carbon::now()) : null),
            'mode' => $left !== null ? 'left' : 'age',
            'when' => $item->due_on?->format('d.m.Y'),
            'urgent' => $critical,
            'status' => $item->status?->getLabel(),
            'kind' => (string) __('new_dashboard.app.card.kinds.work_item'),
            'project' => $item->project?->getAttribute('display_name'),
            'types' => $this->projectTypes($item->project),
            'url' => RecordLinks::detailUrl($item, checkRecord: false),
            'target' => $this->target('work_item', $item),
            'level' => $level,
            'priority' => $priority->value,
            'priority_label' => $priority->getLabel(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function case(BusinessCase $case): array
    {
        return [
            'id' => 'c-'.$case->getKey(),
            'title' => (string) $case->title,
            'codes' => array_values(array_filter([$case->caseCode()?->formatted_code])),
            'party' => $case->primaryParty?->display_name,
            'owner' => $case->owner?->full_name,
            'heat' => $case->heat_score !== null ? (int) $case->heat_score : 0,
            'days' => $case->updated_at instanceof Carbon ? (int) $case->updated_at->diffInDays(Carbon::now()) : null,
            'kind' => (string) __('new_dashboard.app.card.kinds.business_case'),
            'types' => $this->caseTypes($case),
            'url' => RecordLinks::detailUrl($case, checkRecord: false),
            'target' => $this->target('business_case', $case),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function approval(ApprovalRequest $request): array
    {
        return [
            'id' => 'a-'.$request->getKey(),
            'title' => (string) $request->subject_label,
            'codes' => [],
            'owner' => $request->requester?->full_name,
            'days' => $request->created_at instanceof Carbon ? (int) $request->created_at->diffInDays(Carbon::now()) : null,
            'amount' => $request->getAttribute('amount') !== null ? Money::format($request->getAttribute('amount'), $request->getAttribute('currency_code')) : null,
            'status' => (string) __('new_dashboard.app.status.pending'),
            'kind' => (string) __('new_dashboard.app.card.kinds.approval'),
            'url' => RecordLinks::detailUrl($request, checkRecord: false),
            'target' => $this->target('approval_request', $request),
            'approve' => $this->canDecide($request) ? (int) $request->getKey() : null,
            // D-217: aciliyet rengi (bekleme yasi).
            'level' => self::ageLevel($request->created_at instanceof Carbon ? (int) $request->created_at->diffInDays(Carbon::now()) : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function request(PurchaseRequest $request): array
    {
        $needed = $request->getAttribute('needed_by');

        return [
            'id' => 'r-'.$request->getKey(),
            'title' => (string) $request->getAttribute('request_no'),
            'codes' => [],
            'party' => $request->project?->getAttribute('display_name'),
            'owner' => $request->requestedBy?->full_name,
            'days' => $request->created_at instanceof Carbon ? (int) $request->created_at->diffInDays(Carbon::now()) : null,
            'when' => $needed instanceof Carbon ? $needed->format('d.m.Y') : null,
            'urgent' => (bool) $request->getAttribute('is_urgent'),
            'status' => $request->status?->getLabel(),
            'kind' => (string) __('new_dashboard.app.card.kinds.purchase_request'),
            'types' => $this->projectTypes($request->project),
            'url' => RecordLinks::detailUrl($request, checkRecord: false),
            'target' => $this->target('purchase_request', $request),
            // D-217: aciliyet rengi (gerektigi tarih ve acil isareti).
            'level' => UrgencyRule::level(self::daysLeft($needed), (bool) $request->getAttribute('is_urgent')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function need(PurchaseNeed $need): array
    {
        $needed = $need->getAttribute('needed_by');

        return [
            'id' => 'n-'.$need->getKey(),
            'title' => (string) $need->getAttribute('title'),
            'codes' => array_values(array_filter([$need->purchaseRequest?->getAttribute('request_no')])),
            'party' => $need->project?->getAttribute('display_name'),
            'days' => $need->created_at instanceof Carbon ? (int) $need->created_at->diffInDays(Carbon::now()) : null,
            'when' => $needed instanceof Carbon ? $needed->format('d.m.Y') : null,
            'urgent' => (bool) $need->getAttribute('is_urgent'),
            'status' => $need->status?->getLabel(),
            'kind' => (string) __('new_dashboard.app.card.kinds.purchase_need'),
            'types' => $this->projectTypes($need->project),
            'url' => RecordLinks::detailUrl($need, checkRecord: false),
            'target' => $this->target('purchase_need', $need),
            'level' => UrgencyRule::level(self::daysLeft($needed), (bool) $need->getAttribute('is_urgent')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function order(PurchaseOrder $order, bool $production): array
    {
        $expected = $order->getAttribute('expected_delivery_on');

        return [
            'id' => 'o-'.$order->getKey(),
            'title' => $order->supplierLabel(),
            'codes' => array_values(array_filter([(string) $order->getAttribute('order_no')])),
            'party' => $order->project?->getAttribute('display_name'),
            'days' => $production
                ? ($expected instanceof Carbon ? (int) DisplayTime::today()->diffInDays($expected, false) : null)
                : ($order->created_at instanceof Carbon ? (int) $order->created_at->diffInDays(Carbon::now()) : null),
            'when' => $expected instanceof Carbon ? $expected->format('d.m.Y') : null,
            'amount' => $order->getAttribute('total_amount') !== null ? Money::format($order->getAttribute('total_amount'), $order->getAttribute('currency_code')) : null,
            'status' => $order->status?->getLabel(),
            'carrier' => $order->getAttribute('carrier'),
            'kind' => (string) __('new_dashboard.app.card.kinds.purchase_order'),
            'types' => $this->projectTypes($order->project),
            'url' => RecordLinks::detailUrl($order, checkRecord: false),
            'target' => $this->target('purchase_order', $order),
            'approve' => $this->approveFor($order),
            // D-217: uretimde teslime kalan gun, beklemede bekleme yasi.
            'level' => $production
                ? UrgencyRule::level(self::daysLeft($expected), false)
                : self::ageLevel($order->created_at instanceof Carbon ? (int) $order->created_at->diffInDays(Carbon::now()) : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchasing(): array
    {
        $requests = $this->visible($this->queries->openPurchaseRequests())->map(fn (PurchaseRequest $r): array => $this->request($r));
        $needs = $this->visible($this->queries->needsInEntry())->map(fn (PurchaseNeed $n): array => $this->need($n));
        $projects = $this->queries->projectsWithOpenPurchasing()->map(fn (array $row): array => [
            'id' => 'pj-'.($row['project'] instanceof Model ? $row['project']->getKey() : 'x'),
            'title' => $row['project'] instanceof Model ? (string) $row['project']->getAttribute('display_name') : '-',
            'codes' => [],
            'open' => $row['open'],
            'ordered' => $row['ordered'],
            'progress' => $row['open'] > 0 ? (int) round($row['ordered'] * 100 / $row['open']) : 0,
            'kind' => (string) __('new_dashboard.app.card.kinds.project'),
            'types' => $this->projectTypes($row['project'] instanceof Model ? $row['project'] : null),
            'url' => $row['project'] instanceof Model ? RecordLinks::detailUrl($row['project'], checkRecord: false) : null,
        ]);
        $pending = $this->visible($this->queries->pendingOrders());
        $production = $this->visible($this->queries->ordersInProduction());

        return [
            'kpis' => [
                'entry' => $requests->count() + $needs->count(),
                'urgent' => $requests->where('urgent', true)->count() + $needs->where('urgent', true)->count(),
                'projects' => $projects->count(),
                'pending' => $pending->count(),
                'pending_total' => $this->totals($pending, fn (PurchaseOrder $o): array => [$o->getAttribute('total_amount'), $o->getAttribute('currency_code')]),
                'production' => $production->count(),
                'late' => $production->filter(fn (PurchaseOrder $o): bool => $o->getAttribute('expected_delivery_on') instanceof Carbon && $o->getAttribute('expected_delivery_on')->lt(DisplayTime::today()))->count(),
            ],
            'lanes' => [
                $this->lane('purchasing', 'entry', $requests->concat($needs), 'keep'),
                $this->lane('purchasing', 'projects', $projects, 'keep', false),
                $this->lane('purchasing', 'orders', $pending->map(fn (PurchaseOrder $o): array => $this->order($o, false))),
                $this->lane('purchasing', 'production', $production->map(fn (PurchaseOrder $o): array => $this->order($o, true)), 'left'),
            ],
        ];
    }

    /**
     * Proje tipi rozetleri (D-222; kullanici 19 Ekim 2026: "kartlarin uzerinde
     * projenin turu eksik onu da badge olarak kendi renginde ekle"): deger, ad
     * ve Filament rengi; simge React'te ayni Heroicon (ProjectScopeType::getIcon).
     *
     * @param  iterable<mixed>  $types
     * @return list<array{value: string, label: string, color: string}>
     */
    private static function typeBadges(iterable $types): array
    {
        $badges = [];

        foreach ($types as $type) {
            $enum = $type instanceof ProjectScopeType ? $type : ProjectScopeType::tryFrom((string) ($type instanceof \BackedEnum ? $type->value : $type));

            if ($enum !== null && ! isset($badges[$enum->value])) {
                $badges[$enum->value] = ['value' => $enum->value, 'label' => $enum->getLabel(), 'color' => $enum->getColor()];
            }
        }

        return array_values($badges);
    }

    /**
     * Projenin tipleri (B48 proje kapsamlari); proje ekranda tip gostermiyorsa bos.
     * Ayni proje bir kez okunur.
     *
     * @return list<array{value: string, label: string, color: string}>
     */
    private function projectTypes(mixed $project): array
    {
        if (! $project instanceof Project || ! ProjectNames::scopeTypesEnabled()) {
            return [];
        }

        $key = (int) $project->getKey();

        return $this->projectTypeCache[$key] ??= self::typeBadges($project->scopes->pluck('scope_type'));
    }

    /**
     * Potansiyel isin tipleri (kapsamlar).
     *
     * @return list<array{value: string, label: string, color: string}>
     */
    private function caseTypes(BusinessCase $case): array
    {
        return self::typeBadges($case->scopes->pluck('scope_type'));
    }

    /**
     * Uyari hedefi: tur ve id (alicilari sunucu cozer; D-208).
     *
     * @return array{type: string, id: int}|null
     */
    private function target(string $type, Model $record): ?array
    {
        return in_array($type, WarningTargetQueries::TYPES, true) ? ['type' => $type, 'id' => (int) $record->getKey()] : null;
    }

    /** Onay bekleyen siparisin onay talebi; kisi karar verebiliyorsa id. */
    private function approveFor(PurchaseOrder $order): ?int
    {
        if ($order->statusEnum() !== PurchaseOrderStatus::AwaitingApproval || $order->getAttribute('approval_request_id') === null) {
            return null;
        }

        $request = $this->queries->approvalRequests([(int) $order->getAttribute('approval_request_id')])->first();

        return $request instanceof ApprovalRequest && $this->canDecide($request) ? (int) $request->getKey() : null;
    }

    private function canDecide(ApprovalRequest $request): bool
    {
        return $this->viewer instanceof Personnel && $this->approvals->canDecide($request, (int) $this->viewer->getKey());
    }

    /**
     * @return array{id: int, name: string, members: int}|null
     */
    private function department(?string $code): ?array
    {
        $unit = $code !== null ? $this->organization->unitByCode($code) : null;

        if (! $unit instanceof OrgUnit) {
            return null;
        }

        return [
            'id' => (int) $unit->getKey(),
            'name' => (string) $unit->name,
            'members' => $this->targets->memberCount((int) $unit->getKey()),
        ];
    }

    /** @param  class-string<resource>|null  $resource */
    private function listUrl(?string $resource): ?string
    {
        if ($resource === null) {
            return null;
        }

        try {
            return $resource::canAccess() ? $resource::getUrl('index') : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function bytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1, ',', '.').' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', '.').' KB',
            default => $bytes.' B',
        };
    }

    /**
     * Para birimine gore toplam ("12.500.000 ₺", "3.100.000 $").
     *
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @param  callable(T): array{0: mixed, 1: mixed}  $pick
     * @return list<string>
     */
    private function totals(Collection $items, callable $pick): array
    {
        $sums = [];

        foreach ($items as $item) {
            [$amount, $currency] = $pick($item);

            if ($amount === null) {
                continue;
            }

            $code = (string) ($currency ?: 'TRY');
            $sums[$code] = ($sums[$code] ?? 0) + (float) $amount;
        }

        arsort($sums);

        return array_values(array_map(fn (string $code): string => Money::format($sums[$code], $code, maxDecimals: 0), array_keys($sums)));
    }

    /** @param  class-string<resource>  $resource */
    private function canCreate(string $resource): bool
    {
        try {
            return $resource::canAccess() && $resource::hasPage('create') && $resource::canCreate();
        } catch (Throwable) {
            return false;
        }
    }
}
