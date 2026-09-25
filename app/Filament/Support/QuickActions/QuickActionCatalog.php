<?php

declare(strict_types=1);

namespace App\Filament\Support\QuickActions;

use App\Filament\Resources\ApprovalRequests\ApprovalRequestResource;
use App\Filament\Resources\BusinessCases\BusinessCaseResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\MeetingPlans\MeetingPlanResource;
use App\Filament\Resources\Parties\PartyResource;
use App\Filament\Resources\Personnel\PersonnelResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Proposals\ProposalResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\SocialContents\SocialContentResource;
use App\Filament\Resources\TenderNotices\TenderNoticeResource;
use App\Filament\Resources\WorkItems\WorkItemResource;
use App\Filament\Resources\WorkRequests\WorkRequestResource;
use App\Query\Personnel\QuickActionQueries;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Throwable;

/**
 * Hizli islemler katalogu (D-122, 24 Eylul 2026 kullanici karari: "Kisiye
 * ozel en kolay erismesi gereken isler orada olacak. Kisi isterse kendisine
 * gore ... degistirebilecek. Sabit zorunlu olanlar eklenmis olabilir.
 * Ornegin 3 sabit 3 kendi eklemesi").
 *
 * Islemler kodda tanimlidir; kisi yalniz secer (B38 `personnel_quick_actions`).
 * Her islem oturumdaki kisinin yetkisine gore suzulur: acamayacagi sayfaya
 * giden islem ne dugmede ne secim listesinde gorunur.
 */
final class QuickActionCatalog
{
    /** Kisinin ekleyebilecegi en fazla islem (sabitlerin disinda). */
    public const MAX_PERSONAL = 3;

    /** Herkeste bulunan, kaldirilamayan islemler. */
    public const FIXED = ['work_item.create', 'report.create', 'work_request.create'];

    public function __construct(private readonly QuickActionQueries $queries) {}

    /**
     * Kod => [kaynak, sayfa, simge]. Sira, secim listesindeki siradir.
     *
     * @return array<string, array{0: class-string<Resource>, 1: string, 2: Heroicon}>
     */
    private function definitions(): array
    {
        return [
            'work_item.create' => [WorkItemResource::class, 'create', Heroicon::OutlinedSquaresPlus],
            'report.create' => [ReportResource::class, 'create', Heroicon::OutlinedPencilSquare],
            'work_request.create' => [WorkRequestResource::class, 'create', Heroicon::OutlinedPaperAirplane],
            'meeting_plan.create' => [MeetingPlanResource::class, 'create', Heroicon::OutlinedCalendarDays],
            'meeting_plan.index' => [MeetingPlanResource::class, 'index', Heroicon::OutlinedCalendar],
            'business_case.create' => [BusinessCaseResource::class, 'create', Heroicon::OutlinedBriefcase],
            'proposal.create' => [ProposalResource::class, 'create', Heroicon::OutlinedDocumentCurrencyDollar],
            'tender_notice.create' => [TenderNoticeResource::class, 'create', Heroicon::OutlinedScale],
            'party.create' => [PartyResource::class, 'create', Heroicon::OutlinedBuildingOffice2],
            'document.create' => [DocumentResource::class, 'create', Heroicon::OutlinedArrowUpTray],
            'project.create' => [ProjectResource::class, 'create', Heroicon::OutlinedFolderPlus],
            'approval_request.index' => [ApprovalRequestResource::class, 'index', Heroicon::OutlinedCheckBadge],
            'work_item.index' => [WorkItemResource::class, 'index', Heroicon::OutlinedQueueList],
            'report.index' => [ReportResource::class, 'index', Heroicon::OutlinedDocumentChartBar],
            'work_request.index' => [WorkRequestResource::class, 'index', Heroicon::OutlinedInbox],
            'project.index' => [ProjectResource::class, 'index', Heroicon::OutlinedFolderOpen],
            'party.index' => [PartyResource::class, 'index', Heroicon::OutlinedBuildingOffice],
            'personnel.index' => [PersonnelResource::class, 'index', Heroicon::OutlinedUsers],
            'social_content.index' => [SocialContentResource::class, 'index', Heroicon::OutlinedMegaphone],
        ];
    }

    /**
     * Dugmede gorunecek islemler: once sabitler, sonra kisinin secimleri.
     *
     * @return list<QuickAction>
     */
    public function forPersonnel(int $personnelId): array
    {
        $actions = [];

        foreach ([...self::FIXED, ...$this->personalCodes($personnelId)] as $code) {
            $action = $this->resolve($code);

            if ($action instanceof QuickAction) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Kisinin kayitli secimleri (katalogda olmayanlar ve sabitler elenir).
     *
     * @return list<string>
     */
    public function personalCodes(int $personnelId): array
    {
        $definitions = $this->definitions();

        $codes = array_values(array_filter(
            $this->queries->codesFor($personnelId),
            fn (string $code): bool => ! in_array($code, self::FIXED, true) && array_key_exists($code, $definitions),
        ));

        return array_slice($codes, 0, self::MAX_PERSONAL);
    }

    /**
     * Kisinin ekleyebilecegi islemler (sabitler haric, yetkisi olanlar).
     *
     * @return array<string, string> kod => ad
     */
    public function selectableOptions(): array
    {
        $options = [];

        foreach (array_keys($this->definitions()) as $code) {
            if (in_array($code, self::FIXED, true)) {
                continue;
            }

            $action = $this->resolve($code);

            if ($action instanceof QuickAction) {
                $options[$code] = $action->label;
            }
        }

        return $options;
    }

    /**
     * Sabit islemler (yetkisi olanlar).
     *
     * @return list<QuickAction>
     */
    public function fixed(): array
    {
        $actions = [];

        foreach (self::FIXED as $code) {
            $action = $this->resolve($code);

            if ($action instanceof QuickAction) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * Kayittan once secimi gecerli kodlara indirger.
     *
     * @param  array<mixed>  $codes
     * @return list<string>
     */
    public function sanitize(array $codes): array
    {
        $allowed = $this->selectableOptions();

        $codes = array_values(array_unique(array_filter(
            $codes,
            fn ($code): bool => is_string($code) && array_key_exists($code, $allowed),
        )));

        return array_slice($codes, 0, self::MAX_PERSONAL);
    }

    /** Islem, oturumdaki kisinin acabilecegi bir sayfaya gidiyorsa cozulur. */
    private function resolve(string $code): ?QuickAction
    {
        $definition = $this->definitions()[$code] ?? null;

        if ($definition === null) {
            return null;
        }

        [$resource, $page, $icon] = $definition;

        try {
            if (! $resource::hasPage($page)) {
                return null;
            }

            $allowed = $page === 'create' ? $resource::canCreate() : $resource::canAccess();

            if (! $allowed) {
                return null;
            }

            return new QuickAction(
                code: $code,
                label: __('quick_action.items.'.str_replace('.', '_', $code)),
                icon: $icon,
                url: $resource::getUrl($page),
                fixed: in_array($code, self::FIXED, true),
            );
        } catch (Throwable) {
            // Semasi uygulanmamis ya da kapali bir modul: islem gosterilmez.
            return null;
        }
    }
}
