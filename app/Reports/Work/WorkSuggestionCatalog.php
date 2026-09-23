<?php

declare(strict_types=1);

namespace App\Reports\Work;

use App\Enums\Report\WorkItemLinkKind;
use App\Support\ActivityLabels;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Sistemden gelen oneriler (B36, D-115): personelin KENDI hareketlerinden
 * (Personel Hareketleri) hangilerinin panoya kart onerisi olacagi kodda
 * tanimlidir. Her islem kodu; hareketin konusunu, kartin bagli kayit turunu,
 * modul adini ve departman setinde varsa secilecek kategoriyi bildirir.
 *
 * Metinler lang/{tr,en}/work_item.php `suggestions` altindadir:
 * `labels.{kod}` (oneri satiri), `titles.{kod}` (kart adinin basi),
 * `modules.{modul}` (kaynak adi). Kodlardaki nokta alt cizgiye doner.
 */
final class WorkSuggestionCatalog
{
    /** Kac gun geriye bakilir. */
    public const WINDOW_DAYS = 7;

    /**
     * Islem kodu => tanim.
     *
     * @var array<string, array{link: string, module: string, categories: list<string>}>
     */
    private const ACTIONS = [
        'proposal.created' => ['link' => 'proposal', 'module' => 'proposals', 'categories' => ['new_proposal', 'costing']],
        'proposal_version.created' => ['link' => 'proposal', 'module' => 'proposals', 'categories' => ['revised_proposal', 'new_proposal']],
        'business_case.created' => ['link' => 'business_case', 'module' => 'business_cases', 'categories' => ['business_development', 'new_proposal']],
        'tender_notice.created' => ['link' => 'tender_notice', 'module' => 'tenders', 'categories' => ['tender_tracking']],
        'document.created' => ['link' => 'document', 'module' => 'documents', 'categories' => ['drawing', 'calculation']],
        'document_revision.created' => ['link' => 'document', 'module' => 'documents', 'categories' => ['drawing', 'calculation']],
        'project_supply_item.created' => ['link' => 'supply_item', 'module' => 'supply', 'categories' => ['order', 'quote_collection', 'material']],
        'project_supply_item.status_changed' => ['link' => 'supply_item', 'module' => 'supply', 'categories' => ['order', 'shipment', 'material']],
        'work_request.created' => ['link' => 'work_request', 'module' => 'work_requests', 'categories' => ['support', 'coordination']],
        'work_request.completed' => ['link' => 'work_request', 'module' => 'work_requests', 'categories' => ['support', 'coordination']],
        'meeting_plan.completed' => ['link' => 'meeting_plan', 'module' => 'meetings', 'categories' => ['business_development', 'meeting']],
        'party_meeting_note.created' => ['link' => 'meeting_plan', 'module' => 'meetings', 'categories' => ['business_development', 'meeting']],
        'report.created' => ['link' => 'none', 'module' => 'reports', 'categories' => ['coordination', 'quality', 'general']],
        'contract.created' => ['link' => 'none', 'module' => 'contracts', 'categories' => ['contract', 'existing_work']],
        'project.opened' => ['link' => 'none', 'module' => 'projects', 'categories' => ['existing_work', 'coordination']],
        'project.converted' => ['link' => 'none', 'module' => 'projects', 'categories' => ['existing_work', 'coordination']],
        'project_photo.created' => ['link' => 'none', 'module' => 'projects', 'categories' => ['field_support', 'site_work']],
    ];

    /**
     * @return list<string>
     */
    public function actionCodes(): array
    {
        return array_keys(self::ACTIONS);
    }

    public function supports(string $actionCode): bool
    {
        return array_key_exists($actionCode, self::ACTIONS);
    }

    public function linkKind(string $actionCode): WorkItemLinkKind
    {
        return WorkItemLinkKind::tryFrom(self::ACTIONS[$actionCode]['link'] ?? 'none') ?? WorkItemLinkKind::None;
    }

    public function module(string $actionCode): string
    {
        return self::ACTIONS[$actionCode]['module'] ?? 'system';
    }

    /** Departman setinde bulunan ilk uygun kategori. */
    public function category(string $actionCode, ?string $unitCode, WorkCategoryCatalog $categories): ?string
    {
        foreach (self::ACTIONS[$actionCode]['categories'] ?? [] as $candidate) {
            if ($categories->has($unitCode, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Oneri satirindaki islem adi ("Teklif olusturuldu"). */
    public function label(string $actionCode): string
    {
        return $this->text('labels', $actionCode);
    }

    /** Kart adinin basi ("Teklif hazirlandi"). */
    public function titlePrefix(string $actionCode): string
    {
        return $this->text('titles', $actionCode);
    }

    public function moduleLabel(string $actionCode): string
    {
        $module = $this->module($actionCode);
        $key = 'work_item.suggestions.modules.'.$module;

        return Lang::has($key) ? (string) __($key) : Str::headline($module);
    }

    private function text(string $group, string $actionCode): string
    {
        $key = 'work_item.suggestions.'.$group.'.'.str_replace('.', '_', $actionCode);

        return Lang::has($key) ? (string) __($key) : ActivityLabels::action($actionCode);
    }
}
